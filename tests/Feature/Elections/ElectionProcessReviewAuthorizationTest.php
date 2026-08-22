<?php

namespace Tests\Feature\Elections;

use App\Models\Election;
use App\Models\ElectionProcessReview;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectionProcessReviewAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_arbitrary_admin_panel_role_cannot_stay_or_decide_election_review(): void
    {
        $review = $this->pendingReview();
        $user = User::factory()->create(['is_system' => false]);
        $role = Role::create([
            'name' => 'Unrelated operator',
            'slug' => 'unrelated-operator',
            'description' => 'May enter generic admin middleware but has no election review authority.',
            'is_system' => false,
            'order' => 50,
        ]);
        $user->roles()->attach($role->id);

        $this->actingAs($user)
            ->postJson(route('admin.elections.process-reviews.stay', $review), [
                'reason' => 'نباید اجازه داده شود',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson(route('admin.elections.process-reviews.decision', $review), [
                'decision' => 'dismissed',
                'reason' => 'نباید اجازه داده شود',
            ])
            ->assertForbidden();

        $this->assertSame('pending', $review->refresh()->human_status);
        $this->assertSame('none', $review->interim_state);
    }

    public function test_system_admin_can_use_authorized_review_routes_and_actions_are_audited(): void
    {
        $review = $this->pendingReview();
        $admin = User::factory()->create(['is_system' => false]);
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)
            ->postJson(route('admin.elections.process-reviews.stay', $review), [
                'reason' => 'خطر فوری برای حقوق اعضا تا پایان بررسی',
            ])
            ->assertOk()
            ->assertJsonPath('interim_state', 'stayed');

        $this->assertDatabaseHas('election_review_audit_accesses', [
            'review_id' => $review->id,
            'actor_user_id' => $admin->id,
            'authority_path' => 'review_authority',
            'purpose' => 'interim_stay_decision',
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.elections.process-reviews.decision', $review->refresh()), [
                'decision' => 'dismissed',
                'reason' => 'بررسی مستند نشان داد مغایرت قابل اثباتی باقی نمانده است.',
            ])
            ->assertOk()
            ->assertJsonPath('human_status', 'decided')
            ->assertJsonPath('decision', 'dismissed');

        $this->assertDatabaseHas('election_review_audit_accesses', [
            'review_id' => $review->id,
            'actor_user_id' => $admin->id,
            'authority_path' => 'review_authority',
            'purpose' => 'reasoned_final_decision',
        ]);
    }

    private function pendingReview(): ElectionProcessReview
    {
        $group = Group::create([
            'name' => 'Review authorization group',
            'group_type' => 'public',
            'location_level' => 'neighborhood',
        ]);
        $election = Election::create([
            'group_id' => $group->id,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
            'is_closed' => false,
            'lifecycle_status' => 'open',
        ]);

        return ElectionProcessReview::create([
            'election_id' => $election->id,
            'ground' => 'representation',
            'challenged_event' => 'appointment_representation',
            'event_occurred_at' => now()->subDay(),
            'automatic_status' => 'requires_human_review',
            'automatic_result' => ['identity_disclosure' => 'none'],
            'human_status' => 'pending',
            'support_count' => 3,
            'human_deadline_at' => now()->addDays(6),
            'human_requested_at' => now()->subHour(),
            'decision_due_at' => now()->addDays(14),
            'interim_state' => 'none',
        ]);
    }
}
