<?php
namespace Tests\Feature\NajmBahar;

use App\Models\User;
use App\Models\UserPointTransaction;
use App\Models\ReputationRule;
use App\Modules\NajmBahar\Models\MonetaryPolicyVersion;
use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\MonetaryService;
use App\Modules\NajmBahar\Services\Api\NajmBaharMembershipFeeApplicationService;
use App\Services\MembershipFeeStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipFeeCompletionEvidenceTest extends TestCase
{
    use RefreshDatabase;
    private function member(): array {
        $user=User::factory()->create();
        $account=app(AccountService::class)->createMainAccountForUser($user->id,'Evidence member');
        app(MonetaryService::class)->issueMembershipCredit($account,$user->id);
        return [$user,$account->fresh()];
    }
    private function policy(int $operations=600,int $insurance=300,int $burn=300): MonetaryPolicyVersion {
        return MonetaryPolicyVersion::create(['version'=>990,'status'=>'active','effective_from'=>now()->subMinute(),'approved_at'=>now(),
            'parameters'=>['membership_fee_gol'=>1200,'membership_operations_gol'=>$operations,'membership_insurance_gol'=>$insurance,'membership_burn_gol'=>$burn]]);
    }
    public function test_zero_burn_split_is_paid_once(): void {
        $this->policy(900,300,0); [$user,$account]=$this->member();
        ReputationRule::updateOrCreate(['key'=>'membership_fee_paid'],['label'=>'Membership fee paid','weight'=>12,'active'=>true,'daily_cap'=>null,'dimension'=>'participation','convertible'=>true,'repeat_policy'=>'once_per_context']);
        $before=(int)$account->balance;
        $result=app(NajmBaharMembershipFeeApplicationService::class)->pay($user,'dim');
        $this->assertTrue($result['has_paid']);
        $this->assertSame($before-1200,(int)$account->fresh()->balance);
        $this->assertSame(2,Transaction::where('metadata->type','membership_fee')->count());
        try { app(NajmBaharMembershipFeeApplicationService::class)->pay($user,'dim'); } catch (\App\Modules\NajmBahar\Services\Api\NajmBaharMembershipFeeException $e) { $this->assertSame('already_paid',$e->errorCode); }
        $this->assertSame($before-1200,(int)$account->fresh()->balance);
        $this->assertSame(1,UserPointTransaction::where('user_id',$user->id)->where('action','membership_fee_paid')->count());
    }
    public function test_recorded_split_remains_paid_after_policy_change(): void {
        $policy=$this->policy(900,300,0); [$user]=$this->member();
        app(NajmBaharMembershipFeeApplicationService::class)->pay($user,'dim');
        $policy->parameters=['membership_fee_gol'=>1234,'membership_operations_gol'=>600,'membership_insurance_gol'=>300,'membership_burn_gol'=>334]; $policy->save();
        $this->assertTrue(app(MembershipFeeStatusService::class)->hasPaidCurrentMembershipFee($user));
    }
    public function test_malformed_new_proof_cannot_fall_through_to_legacy_sets(): void {
        $this->policy(); [$user]=$this->member();
        app(NajmBaharMembershipFeeApplicationService::class)->pay($user,'dim');
        foreach(Transaction::where('metadata->type','membership_fee')->get() as $transaction) {
            $meta=$transaction->metadata;
            $meta['expected_breakdown_gol']=['operations_salary_gol'=>900,'central_insurance_gol'=>300,'money_destruction_gol'=>0];
            $meta['membership_fee_total_gol']=1200; $transaction->metadata=$meta; $transaction->save();
        }
        $this->assertFalse(app(MembershipFeeStatusService::class)->hasPaidCurrentMembershipFee($user));
    }
    public function test_incomplete_completed_proof_is_not_paid(): void {
        $this->policy(); [$user]=$this->member();
        app(NajmBaharMembershipFeeApplicationService::class)->pay($user,'dim');
        foreach(Transaction::where('metadata->type','membership_fee')->get() as $transaction) {
            $meta=$transaction->metadata;
            $meta['expected_breakdown_gol']=['operations_salary_gol'=>600,'central_insurance_gol'=>300,'money_destruction_gol'=>300];
            $meta['membership_fee_total_gol']=1200; $transaction->metadata=$meta; $transaction->save();
        }
        Transaction::where('metadata->split','money_destruction')->update(['status'=>'failed']);
        $this->assertFalse(app(MembershipFeeStatusService::class)->hasPaidCurrentMembershipFee($user));
    }
    public function test_membership_debit_preserves_committed_dim_total(): void {
        $this->policy(); [$user,$account]=$this->member();
        $account->forceFill(['balance_active'=>1500,'balance_faded'=>100,'committed_dim'=>200,'balance'=>1800])->save();
        app(NajmBaharMembershipFeeApplicationService::class)->pay($user,'active'); $account->refresh();
        $this->assertSame(300,(int)$account->balance_active); $this->assertSame(100,(int)$account->balance_faded);
        $this->assertSame(200,(int)$account->committed_dim); $this->assertSame(600,(int)$account->balance);
    }
    public function test_active_subaccount_payment_does_not_debit_main_local_balance(): void {
        $this->policy(); [$user,$main]=$this->member();
        app(MonetaryService::class)->activateDim($main,1500,'Fund own subaccount',['type'=>'test'],'completion-fund-active',false);
        $sub=app(\App\Modules\NajmBahar\Services\SubAccountService::class)->createSubAccount($main->id,'Own payment source');
        app(\App\Modules\NajmBahar\Services\InternalAccountTransferService::class)->mainToSub($main,$sub,1500,'active','Fund source','completion-main-to-sub');
        $main->refresh(); $before=(int)$main->balance; $dim=(int)$main->balance_faded;
        app(NajmBaharMembershipFeeApplicationService::class)->pay($user,'active',$sub->id);
        $this->assertSame($before,(int)$main->fresh()->balance);
        $this->assertSame($dim,(int)$main->fresh()->balance_faded);
        $this->assertSame(300,(int)$sub->fresh()->balance_active);
    }
    public function test_system_credit_preserves_receiver_committed_dim(): void {
        $sender=\App\Modules\NajmBahar\Models\Account::create(['account_number'=>'9800000001','name'=>'Sender','type'=>'system','balance'=>1000,'balance_active'=>1000,'balance_faded'=>0,'status'=>1]);
        $receiver=\App\Modules\NajmBahar\Models\Account::create(['account_number'=>'9800000002','name'=>'Receiver','type'=>'user','balance'=>300,'balance_active'=>0,'balance_faded'=>100,'committed_dim'=>200,'status'=>1]);
        app(\App\Modules\NajmBahar\Services\TransactionService::class)->transfer($sender->account_number,$receiver->account_number,100,'Preserve committed receiver',['system_operation'=>true],'completion-receiver-committed','active');
        $this->assertSame(400,(int)$receiver->fresh()->balance);
        $this->assertSame(200,(int)$receiver->fresh()->committed_dim);
    }

}
