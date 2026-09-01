<?php

namespace Tests\Feature\Invitation;

use App\Models\InvitationCode;
use App\Models\ReputationRule;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserPointTransaction;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\MonetaryService;
use App\Services\InvitationLifecycleService;
use App\Services\ProfileCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InvitationLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_unused_or_abandoned_claim_releases_slot_while_live_code_reserves_it(): void
    {
        $this->configureInvitationPolicy(2);
        $referrer = $this->completeMember();
        $this->activateParticipation($referrer);
        $abandonedInvitee = User::factory()->create();

        InvitationCode::create([
            'code' => 'EXPIRED1',
            'user_id' => $referrer->id,
            'used' => false,
            'expire_at' => now()->subMinute(),
        ]);

        InvitationCode::create([
            'code' => 'ABANDON1',
            'user_id' => $referrer->id,
            'used' => true,
            'used_by' => $abandonedInvitee->id,
            'used_at' => now()->subHours(73),
            'expire_at' => now()->subHour(),
        ]);

        InvitationCode::create([
            'code' => 'ACTIVE01',
            'user_id' => $referrer->id,
            'used' => false,
            'expire_at' => now()->addHour(),
        ]);

        $service = app(InvitationLifecycleService::class);

        $this->assertSame(1, $service->occupiedSlots($referrer));
        $this->assertSame(1, $service->remainingSlots($referrer));
        $this->assertTrue($service->canIssueMemberInvitation($referrer));

        $service->issueMemberInvitation($referrer);

        $this->assertSame(2, $service->occupiedSlots($referrer));
        $this->assertFalse($service->canIssueMemberInvitation($referrer));
    }

    public function test_registration_completion_awards_one_hundred_points_exactly_once_and_marks_success(): void
    {
        $this->configureInvitationPolicy(10);
        $this->configureInviteReward(100);

        $referrer = $this->completeMember();
        $invitee = $this->completeMember();

        $invitation = InvitationCode::create([
            'code' => 'SUCCESS1',
            'user_id' => $referrer->id,
            'used' => true,
            'used_by' => $invitee->id,
            'used_at' => now(),
            'expire_at' => now()->addHour(),
        ]);

        $completion = app(ProfileCompletionService::class);
        $completion->maybeAward($invitee);
        $completion->maybeAward($invitee);

        $reward = UserPointTransaction::where('user_id', $referrer->id)
            ->where('action', 'invite_member')
            ->firstOrFail();

        $this->assertSame(100, (int) $reward->delta);
        $this->assertSame('participation', $reward->dimension);
        $this->assertTrue((bool) $reward->convertible);
        $this->assertSame('registration_completion', $reward->source);
        $this->assertSame($invitation->id, (int) $reward->reference_id);
        $this->assertSame(1, UserPointTransaction::where('user_id', $referrer->id)
            ->where('action', 'invite_member')
            ->count());
        $this->assertNotNull($invitation->fresh()->completed_at);
    }

    public function test_incomplete_registration_does_not_activate_invitation_reward(): void
    {
        $this->configureInvitationPolicy(10);
        $this->configureInviteReward(100);

        $referrer = $this->completeMember();
        $invitee = User::factory()->create();

        $invitation = InvitationCode::create([
            'code' => 'PENDING1',
            'user_id' => $referrer->id,
            'used' => true,
            'used_by' => $invitee->id,
            'used_at' => now(),
            'expire_at' => now()->addHour(),
        ]);

        app(ProfileCompletionService::class)->maybeAward($invitee);

        $this->assertSame(0, UserPointTransaction::where('user_id', $referrer->id)
            ->where('action', 'invite_member')
            ->count());
        $this->assertNull($invitation->fresh()->completed_at);
    }

    public function test_lifecycle_service_itself_refuses_to_complete_an_incomplete_invitee(): void
    {
        $this->configureInvitationPolicy(10);
        $this->configureInviteReward(100);

        $referrer = $this->completeMember();
        $invitee = User::factory()->create();

        $invitation = InvitationCode::create([
            'code' => 'DIRECT01',
            'user_id' => $referrer->id,
            'used' => true,
            'used_by' => $invitee->id,
            'used_at' => now(),
            'expire_at' => now()->addHour(),
        ]);

        $this->assertFalse(app(InvitationLifecycleService::class)->completeSuccessfulInvitation($invitee));
        $this->assertNull($invitation->fresh()->completed_at);
        $this->assertDatabaseMissing('user_point_transactions', [
            'user_id' => $referrer->id,
            'action' => 'invite_member',
        ]);
    }

    public function test_expired_claim_cannot_receive_a_late_success_reward_even_when_quota_is_available(): void
    {
        $this->configureInvitationPolicy(10);
        $this->configureInviteReward(100);

        $referrer = $this->completeMember();
        $lateInvitee = $this->completeMember();

        $late = InvitationCode::create([
            'code' => 'LATEFREE',
            'user_id' => $referrer->id,
            'used' => true,
            'used_by' => $lateInvitee->id,
            'used_at' => now()->subHours(73),
            'expire_at' => now()->subHour(),
        ]);

        $this->assertFalse(app(InvitationLifecycleService::class)->completeSuccessfulInvitation($lateInvitee));
        $this->assertNull($late->fresh()->completed_at);
        $this->assertDatabaseMissing('user_point_transactions', [
            'user_id' => $referrer->id,
            'action' => 'invite_member',
            'reference_id' => $late->id,
        ]);
    }

    public function test_late_completion_cannot_create_more_successful_rewards_than_the_configured_quota(): void
    {
        $this->configureInvitationPolicy(1);
        $this->configureInviteReward(100);

        $referrer = $this->completeMember();
        $firstInvitee = $this->completeMember();
        $lateInvitee = $this->completeMember();

        InvitationCode::create([
            'code' => 'DONE0001',
            'user_id' => $referrer->id,
            'used' => true,
            'used_by' => $firstInvitee->id,
            'used_at' => now()->subDay(),
            'completed_at' => now()->subDay(),
            'expire_at' => now()->subHour(),
        ]);

        $late = InvitationCode::create([
            'code' => 'LATE0001',
            'user_id' => $referrer->id,
            'used' => true,
            'used_by' => $lateInvitee->id,
            'used_at' => now()->subHours(73),
            'expire_at' => now()->subHour(),
        ]);

        $this->assertFalse(app(InvitationLifecycleService::class)->completeSuccessfulInvitation($lateInvitee));
        $this->assertNull($late->fresh()->completed_at);
        $this->assertDatabaseMissing('user_point_transactions', [
            'user_id' => $referrer->id,
            'action' => 'invite_member',
            'reference_id' => $late->id,
        ]);
    }

    private function configureInvitationPolicy(int $quota): void
    {
        $setting = Setting::singleton();
        $setting->fill([
            'invation_status' => true,
            'count_invation' => $quota,
            'expire_invation_time' => 72,
        ]);
        $setting->save();
    }

    private function configureInviteReward(int $weight): void
    {
        ReputationRule::query()->updateOrCreate(['key' => 'invite_member'], [
            'label' => 'Invite member',
            'weight' => $weight,
            'active' => true,
            'daily_cap' => null,
            'dimension' => 'participation',
            'convertible' => true,
            'repeat_policy' => 'once_per_context',
        ]);
    }

    private function completeMember(): User
    {
        $user = User::factory()->create([
            'gender' => 'male',
            'national_id' => 'T' . fake()->unique()->numerify('#########'),
            'phone' => fake()->unique()->numerify('09#########'),
        ]);

        $experienceFieldId = DB::table('experience_fields')->insertGetId([
            'name' => 'Test Experience ' . $user->id,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_experience_field')->insert([
            'user_id' => $user->id,
            'experience_field_id' => $experienceFieldId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('addresses')->insert([
            'user_id' => $user->id,
            'continent_id' => 1,
            'country_id' => 1,
            'province_id' => 1,
            'county_id' => 1,
            'section_id' => 1,
            'neighborhood_id' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user->fresh();
    }

    private function activateParticipation(User $user): void
    {
        $account = app(AccountService::class)->createMainAccountForUser($user->id, 'Invitation test');
        app(MonetaryService::class)->issueMembershipCredit($account, $user->id);

        $this->actingAs($user)
            ->post(route('najm-bahar.membership-fee.pay'), ['payment_source' => 'dim'])
            ->assertRedirect(route('najm-bahar.dashboard'));
    }
}
