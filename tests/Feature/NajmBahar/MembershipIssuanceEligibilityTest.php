<?php

namespace Tests\Feature\NajmBahar;

use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipIssuanceEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_incomplete_member_cannot_bypass_profile_gate_and_mint_membership_credit_via_post(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('najm-bahar.agreement.process'), [
                'agreement_accepted' => '1',
            ])
            ->assertRedirect(route('najm-bahar.agreement'))
            ->assertSessionHas('error');

        $this->assertFalse(Account::where('user_id', $user->id)->where('type', 'user')->exists());
        $this->assertSame(0, Transaction::where('metadata->issuance_reason', 'membership')
            ->where('metadata->user_id', $user->id)
            ->count());
        $this->assertSame(0, LedgerEntry::where('meta->monetary_event', 'money_created')->count());
        $this->assertNull($user->fresh()->najm_bahar_agreement_accepted_at);
    }
}
