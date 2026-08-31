<?php

namespace Tests\Feature\NajmBahar;

use App\Helpers\BaharMoney;
use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Services\AccountNumberService;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExternalFlowSemanticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dim_activation_is_not_counted_as_income_or_expense_in_report_summary(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('najm-bahar.agreement.process'), [
            'agreement_accepted' => '1',
        ])->assertRedirect(route('najm-bahar.dashboard'));

        $this->actingAs($user)->post(route('najm-bahar.membership-fee.pay'), [
            'payment_source' => 'dim',
        ])->assertRedirect(route('najm-bahar.dashboard'));

        $response = $this->actingAs($user)->get(route('najm-bahar.reports'));

        $response->assertOk();
        $response->assertViewHas('summary', function (array $summary): bool {
            return (int) $summary['totalIn'] === BaharMoney::toGolFromBahar(10_000)
                && (int) $summary['totalOut'] === BaharMoney::toGolFromBahar(12)
                && (int) $summary['net'] === BaharMoney::toGolFromBahar(9_988)
                && (int) $summary['count'] === 4;
        });
        $response->assertViewHas('transactions', function ($transactions) use ($user): bool {
            $accountIds = app(TransactionService::class)->getUserAccountIds($user->id);

            return $transactions->count() === 4
                && $transactions->every(function ($transaction) use ($accountIds): bool {
                    $fromOwned = $transaction->from_account_id !== null
                        && in_array((int) $transaction->from_account_id, $accountIds, true);
                    $toOwned = $transaction->to_account_id !== null
                        && in_array((int) $transaction->to_account_id, $accountIds, true);

                    return $fromOwned xor $toOwned;
                });
        });
    }

    public function test_direction_filters_only_return_external_flows(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('najm-bahar.agreement.process'), [
            'agreement_accepted' => '1',
        ]);
        $this->actingAs($user)->post(route('najm-bahar.membership-fee.pay'), [
            'payment_source' => 'dim',
        ]);

        $incoming = $this->actingAs($user)->get(route('najm-bahar.reports', ['type' => 'in']));
        $outgoing = $this->actingAs($user)->get(route('najm-bahar.reports', ['type' => 'out']));

        $incoming->assertOk()->assertViewHas('transactions', fn ($transactions): bool =>
            $transactions->count() === 1
            && ($transactions->first()?->metadata['type'] ?? null) === 'initial_funding'
        );

        $outgoing->assertOk()->assertViewHas('transactions', fn ($transactions): bool =>
            $transactions->count() === 3
            && $transactions->every(fn ($transaction): bool => ($transaction->metadata['type'] ?? null) === 'membership_fee')
        );
    }

    public function test_transfer_between_main_and_owned_subaccount_does_not_change_external_flow_totals_or_list(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('najm-bahar.agreement.process'), [
            'agreement_accepted' => '1',
        ]);

        $main = Account::where('user_id', $user->id)->where('type', 'user')->firstOrFail();
        $sub = SubAccount::create([
            'account_id' => $main->id,
            'sub_account_code' => AccountNumberService::makeSubAccountCode($main->account_number, 9),
            'name' => 'Internal report test',
            'balance' => 0,
            'balance_active' => 0,
            'balance_faded' => 0,
            'status' => 1,
        ]);
        $subAccount = app(AccountService::class)->ensureSubAccountAccount($sub);

        Transaction::create([
            'from_account_id' => $main->id,
            'to_account_id' => $subAccount->id,
            'amount' => BaharMoney::toGolFromBahar(25),
            'type' => 'immediate',
            'status' => 'completed',
            'metadata' => ['type' => 'internal_test'],
            'description' => 'internal ownership movement',
        ]);

        $response = $this->actingAs($user)->get(route('najm-bahar.reports'));
        $response->assertOk()->assertViewHas('summary', function (array $summary): bool {
            return (int) $summary['totalIn'] === BaharMoney::toGolFromBahar(10_000)
                && (int) $summary['totalOut'] === 0
                && (int) $summary['net'] === BaharMoney::toGolFromBahar(10_000)
                && (int) $summary['count'] === 1;
        });
        $response->assertViewHas('transactions', fn ($transactions): bool =>
            $transactions->count() === 1
            && ($transactions->first()?->metadata['type'] ?? null) === 'initial_funding'
        );

        $this->actingAs($user)->get(route('najm-bahar.reports', ['type' => 'in']))
            ->assertViewHas('transactions', fn ($transactions): bool => $transactions->count() === 1);
        $this->actingAs($user)->get(route('najm-bahar.reports', ['type' => 'out']))
            ->assertViewHas('transactions', fn ($transactions): bool => $transactions->count() === 0);
    }
}
