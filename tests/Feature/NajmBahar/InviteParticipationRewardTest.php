<?php

namespace Tests\Feature\NajmBahar;

use App\Http\Controllers\Admin\ReputationController;
use App\Models\InvitationCode;
use App\Models\ReputationRule;
use App\Models\User;
use App\Models\UserPointTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InviteParticipationRewardTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_invite_awards_referrer_once_for_the_invited_member_with_managed_policy(): void
    {
        $referrer = User::factory()->create();
        $newMember = User::factory()->create();

        ReputationRule::create([
            'key' => 'invite_member',
            'label' => 'Invite member',
            'weight' => 10,
            'active' => true,
            'daily_cap' => null,
            'dimension' => 'participation',
            'convertible' => true,
            'repeat_policy' => 'once_per_context',
        ]);

        $invitation = InvitationCode::create([
            'code' => 'INV-' . $newMember->id,
            'user_id' => $referrer->id,
            'used' => true,
            'used_by' => $newMember->id,
            'used_at' => now(),
        ]);

        $this->actingAs($newMember)
            ->post(route('najm-bahar.agreement.process'), ['agreement_accepted' => '1'])
            ->assertRedirect(route('najm-bahar.dashboard'));

        $reward = UserPointTransaction::where('user_id', $referrer->id)
            ->where('action', 'invite_member')
            ->firstOrFail();

        $this->assertSame(10, (int) $reward->delta);
        $this->assertSame('participation', $reward->dimension);
        $this->assertTrue((bool) $reward->convertible);
        $this->assertSame($newMember->id, (int) ($reward->metadata['new_user_id'] ?? 0));
        $this->assertSame($invitation->id, (int) $reward->reference_id);
        $this->assertSame(1, UserPointTransaction::where('user_id', $referrer->id)
            ->where('action', 'invite_member')
            ->where('reference_id', $invitation->id)
            ->count());
    }

    public function test_invite_member_is_bootstrapped_as_an_admin_managed_convertible_rule(): void
    {
        app(ReputationController::class)->index();

        $rule = ReputationRule::where('key', 'invite_member')->firstOrFail();

        $this->assertTrue((bool) $rule->active);
        $this->assertSame(10, (int) $rule->weight);
        $this->assertSame('participation', $rule->dimension);
        $this->assertTrue((bool) $rule->convertible);
        $this->assertSame('once_per_context', $rule->repeat_policy);
    }
}
