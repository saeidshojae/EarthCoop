<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\NajmBahar\Models\MonetaryPolicyVersion;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\MonetaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NajmBaharMembershipConsentContractTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        [$user, $token, $device] = $this->nativeSession();
        $main = $this->memberAccount($user);
        $this->policy();
        $this->bearer($token, $device);
        $info = $this->getJson('/api/v1/najm-bahar/membership-fee')->assertOk()->json('data');
        $expected = array_intersect_key($info, array_flip(['payment_year','fee_gol','breakdown','policy_version_id']));
        $expected['account_number'] = $main->account_number;
        return [$user, $main, $expected, $token, $device];
    }

    private function submit(array $expected, string $bucket = 'dim', ?int $subId = null, string $key = 'consent-payment-0001')
    {
        return $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/najm-bahar/membership-fee/pay',
            ['payment_source'=>$bucket,'sub_account_id'=>$subId,'expected'=>$expected]);
    }

    public function test_capability_sources_are_read_only_and_individually_eligible(): void
    {
        [, $main] = $this->fixture();
        $sub = SubAccount::create(['account_id'=>$main->id,'sub_account_code'=>$main->account_number.'-099',
            'name'=>'No mirror','balance'=>700,'balance_active'=>700,'balance_faded'=>0,'status'=>1]);
        $main->forceFill(['balance_active'=>700,'balance_faded'=>500,'committed_dim'=>200,'balance'=>1400])->save();
        $count = \App\Modules\NajmBahar\Models\Account::count();
        $before = Transaction::count();
        $data = $this->getJson('/api/v1/najm-bahar/membership-fee')->assertOk()
            ->assertJsonPath('data.payment_contract_version',1)->json('data');
        $this->assertCount(2,$data['payment_sources']);
        $this->assertFalse($data['payment_sources'][0]['can_pay_active']);
        $this->assertFalse($data['payment_sources'][0]['can_pay_dim']);
        $this->assertSame(700,$data['payment_sources'][1]['active_available_gol']);
        $this->assertFalse($data['payment_sources'][1]['can_pay_active']);
        $this->assertFalse($data['payment_sources'][1]['can_pay_dim']);
        $this->assertSame($count,\App\Modules\NajmBahar\Models\Account::count());
        $this->assertSame($before,Transaction::count());
    }

    public function test_dim_consent_returns_actual_source_and_replays_after_policy_change(): void
    {
        [, $main, $expected] = $this->fixture();
        $paid = $this->submit($expected)->assertCreated()->assertJsonPath('data.has_paid',true)
            ->assertJsonPath('data.payment_account_number',$main->account_number)->json();
        $policy = MonetaryPolicyVersion::first();
        $policy->parameters = array_merge($policy->parameters,['membership_fee_gol'=>1234,'membership_burn_gol'=>334]); $policy->save();
        $this->assertSame($paid,$this->submit($expected)->assertCreated()->json());
        $changed=$expected; $changed['fee_gol']=1234;
        $this->submit($changed)->assertStatus(409)->assertJsonPath('error.code','idempotency_key_reused');
        $this->assertSame(3,Transaction::where('metadata->type','membership_fee')->count());
    }

    public function test_changed_terms_reject_with_zero_financial_effects(): void
    {
        [, $main, $expected] = $this->fixture();
        foreach (['payment_year','fee_gol','policy_version_id','breakdown'] as $index=>$field) {
            $stale=$expected;
            if($field==='breakdown') { $stale[$field]['central_insurance_gol']--; $stale[$field]['operations_salary_gol']++; }
            else { $stale[$field]++; if ($field === 'fee_gol') { $stale['breakdown']['money_destruction_gol']++; } }
            $balance=(int)$main->fresh()->balance; $transactions=Transaction::count(); $ledgers=\App\Modules\NajmBahar\Models\LedgerEntry::count(); $points=\App\Models\UserPointTransaction::count();
            $this->submit($stale,'dim',null,'consent-stale-'.$index)->assertStatus(409)
                ->assertJsonPath('error.code','membership_fee_terms_changed');
            $this->assertSame($balance,(int)$main->fresh()->balance);
            $this->assertSame($transactions,Transaction::count());
            $this->assertSame($ledgers,\App\Modules\NajmBahar\Models\LedgerEntry::count());
            $this->assertSame($points,\App\Models\UserPointTransaction::count());
        }
    }

    public function test_new_main_intent_does_not_fall_back_but_legacy_call_does(): void
    {
        [, $main, $expected] = $this->fixture();
        $main->forceFill(['balance_active'=>500,'balance_faded'=>0,'balance'=>500])->save();
        $sub=SubAccount::create(['account_id'=>$main->id,'sub_account_code'=>$main->account_number.'-088',
            'name'=>'Funded','balance'=>2000,'balance_active'=>2000,'balance_faded'=>0,'status'=>1]);
        $this->submit($expected,'active')->assertStatus(409)->assertJsonPath('error.code','insufficient_available_funds');
        $this->assertSame(2000,(int)$sub->fresh()->balance_active);
        $this->withHeader('Idempotency-Key','consent-legacy-fallback')->postJson('/api/v1/najm-bahar/membership-fee/pay',
            ['payment_source'=>'active'])->assertCreated()->assertJsonPath('data.has_paid',true);
    }

    public function test_selected_owned_subaccount_active_payment_preserves_main(): void
    {
        [, $main, $expected] = $this->fixture();
        $sub=SubAccount::create(['account_id'=>$main->id,'sub_account_code'=>$main->account_number.'-087',
            'name'=>'Selected','balance'=>2000,'balance_active'=>2000,'balance_faded'=>0,'status'=>1]);
        $before=(int)$main->balance; $expected['account_number']=$sub->sub_account_code;
        $this->submit($expected,'active',$sub->id)->assertCreated()->assertJsonPath('data.payment_account_number',$sub->sub_account_code);
        $this->assertSame(800,(int)$sub->fresh()->balance_active);
        $this->assertSame($before,(int)$main->fresh()->balance);
    }

    public function test_dim_rejects_subaccount_selector_without_mutation(): void
    {
        [, $main, $expected] = $this->fixture(); $before=Transaction::count();
        $this->submit($expected,'dim',987654)->assertStatus(422);
        $this->assertSame($before,Transaction::count());
    }

    public function test_partial_null_and_extra_expected_fields_are_invalid(): void
    {
        [, $main, $expected] = $this->fixture(); $before=Transaction::count();
        $bad=$expected; unset($bad['policy_version_id']);
        $this->submit($bad,'dim',null,'consent-missing-version')->assertStatus(422);
        $bad=$expected; $bad['fee_gol']=null;
        $this->submit($bad,'dim',null,'consent-null-fee')->assertStatus(422);
        $bad=$expected; $bad['other']=1;
        $this->submit($bad,'dim',null,'consent-extra-key')->assertStatus(422);
        $this->assertSame($before,Transaction::count());
    }

    public function test_main_active_consent_and_available_reservation_limit(): void
    {
        [, $main, $expected] = $this->fixture();
        $main->forceFill(['balance_active'=>1500,'balance_faded'=>0,'balance'=>1500])->save();
        app(\App\Modules\NajmBahar\Services\ActiveBaharReservationService::class)
            ->reserve($main->account_number,400,'consent-reservation','test',1);
        $this->getJson('/api/v1/najm-bahar/membership-fee')->assertOk()
            ->assertJsonPath('data.payment_sources.0.active_available_gol',1100)
            ->assertJsonPath('data.payment_sources.0.can_pay_active',false);
        $before=Transaction::count();
        $this->submit($expected,'active')->assertStatus(409);
        $this->assertSame($before,Transaction::count());
        app(\App\Modules\NajmBahar\Services\ActiveBaharReservationService::class)
            ->release('consent-reservation','consent-release');
        $this->submit($expected,'active',null,'consent-main-after-release')->assertCreated()
            ->assertJsonPath('data.payment_account_number',$main->account_number);
        $this->assertSame(300,(int)$main->fresh()->balance_active);
    }

    public function test_foreign_disabled_and_mismatched_account_are_rejected(): void
    {
        [, $main, $expected] = $this->fixture();
        $other=User::factory()->create(); $otherMain=$this->memberAccount($other);
        foreach ([$main,$otherMain] as $index=>$parent) {
            $sub=SubAccount::create(['account_id'=>$parent->id,'sub_account_code'=>$parent->account_number.'-086',
                'name'=>'Unavailable','balance'=>2000,'balance_active'=>2000,'balance_faded'=>0,'status'=>$index]);
            $e=$expected; $e['account_number']=$sub->sub_account_code;
            $before=Transaction::count();
            $this->submit($e,'active',$sub->id,'consent-owner-'.$index)->assertStatus(404);
            $this->assertSame(2000,(int)$sub->fresh()->balance_active);
            $this->assertSame($before,Transaction::count());
        }
        $e=$expected; $e['account_number']='unowned-account';
        $this->submit($e,'dim',null,'consent-account-mismatch')->assertStatus(409);
    }

    public function test_anniversary_rollover_rejects_old_consent(): void
    {
        [$user,$main,$expected] = $this->fixture();
        $user->forceFill(['created_at'=>now()->subYear()->addMinute()])->save();
        $expected['payment_year']=now()->year-1;
        $this->travel(2)->minutes();
        $before=Transaction::count();
        $this->submit($expected)->assertStatus(409)->assertJsonPath('error.code','membership_fee_terms_changed');
        $this->assertSame($before,Transaction::count());
    }

    private function memberAccount(User $user)
    {
        $account = app(AccountService::class)->createMainAccountForUser((int) $user->id, 'Member '.$user->id);
        app(MonetaryService::class)->issueMembershipCredit($account, (int) $user->id);

        return $account->fresh();
    }

    private function policy(int $fee = 1_200, int $operations = 600, int $insurance = 300, int $burn = 300): void
    {
        MonetaryPolicyVersion::create([
            'version' => 901,
            'status' => 'active',
            'effective_from' => now()->subMinute(),
            'approved_at' => now(),
            'parameters' => [
                'membership_fee_gol' => $fee,
                'membership_operations_gol' => $operations,
                'membership_insurance_gol' => $insurance,
                'membership_burn_gol' => $burn,
            ],
        ]);
    }

    private function nativeSession(): array
    {
        $password = 'secret-password';
        $user = User::factory()->create([
            'email' => 'bahar-membership-'.bin2hex(random_bytes(4)).'@example.test',
            'password' => Hash::make($password),
            'is_system' => false,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $login = $this->postJson('/api/v1/auth/session', [
            'email' => $user->email,
            'password' => $password,
            'platform' => 'android',
            'app_version' => '1.0.0',
            'locale' => 'fa',
            'timezone' => 'Asia/Tehran',
            'push_capable' => false,
        ])->assertCreated();

        return [$user, (string) $login->json('data.token'), (string) $login->json('data.device.id')];
    }

    private function bearer(string $token, string $deviceId): self
    {
        Auth::forgetGuards();

        return $this->withToken($token)->withHeader('X-Device-ID', $deviceId);
    }
}
