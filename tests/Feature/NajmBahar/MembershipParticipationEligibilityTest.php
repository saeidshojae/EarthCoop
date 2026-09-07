<?php

namespace Tests\Feature\NajmBahar;

use App\Models\User;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\MonetaryService;
use App\Services\MembershipParticipationEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipParticipationEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_without_najm_bahar_account_is_not_eligible(): void
    {
        $user = User::factory()->create();
        $service = app(MembershipParticipationEligibilityService::class);

        $this->assertSame(MembershipParticipationEligibilityService::NO_NAJM_BAHAR_ACCOUNT, $service->status($user));
        $this->assertFalse($service->isEligible($user));
    }

    public function test_member_with_account_but_without_current_fee_is_not_eligible(): void
    {
        $user = User::factory()->create();
        app(AccountService::class)->createMainAccountForUser($user->id, 'Eligibility test');

        $service = app(MembershipParticipationEligibilityService::class);
        $this->assertSame(MembershipParticipationEligibilityService::MEMBERSHIP_FEE_DUE, $service->status($user));
        $this->assertFalse($service->isEligible($user));
    }

    public function test_member_becomes_eligible_after_real_current_membership_fee_payment(): void
    {
        $user = User::factory()->create();
        $account = app(AccountService::class)->createMainAccountForUser($user->id, 'Eligibility test');
        app(MonetaryService::class)->issueMembershipCredit($account, $user->id);

        $this->actingAs($user)
            ->post(route('najm-bahar.membership-fee.pay'), ['payment_source' => 'dim'])
            ->assertRedirect(route('najm-bahar.dashboard'));

        $service = app(MembershipParticipationEligibilityService::class);
        $this->assertSame(MembershipParticipationEligibilityService::ELIGIBLE, $service->status($user->fresh()));
        $this->assertTrue($service->isEligible($user->fresh()));
    }

    public function test_official_single_operations_fallback_payment_counts_as_current_membership_fee(): void
    {
        $user = User::factory()->create();
        $account = app(AccountService::class)->createMainAccountForUser($user->id, 'Fallback eligibility test');
        $service = app(MembershipParticipationEligibilityService::class);
        $paymentYear = $service->membershipPaymentYear($user);

        NajmTransaction::query()->create([
            'from_account_id' => $account->id,
            'to_account_id' => $account->id,
            'amount' => 1200,
            'fee' => 0,
            'type' => 'membership_fee',
            'status' => 'completed',
            'description' => 'Membership fee fallback regression fixture',
            'metadata' => [
                'type' => 'membership_fee',
                'user_id' => $user->id,
                'split' => 'operations_salary',
                'payment_year' => $paymentYear,
            ],
        ]);

        $this->assertSame(MembershipParticipationEligibilityService::ELIGIBLE, $service->status($user->fresh()));
        $this->assertTrue($service->isEligible($user->fresh()));
    }
}
