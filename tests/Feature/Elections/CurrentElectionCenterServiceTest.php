<?php

namespace Tests\Feature\Elections;

use App\Models\Election;
use App\Models\ElectionEligibilitySnapshot;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Poll;
use App\Models\PollVote;
use App\Models\User;
use App\Services\Elections\CurrentElectionCenterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentElectionCenterServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_systemic_center_keeps_non_terminal_cycles_and_excludes_terminal_cycles(): void
    {
        [$group, $user] = $this->memberGroup();
        $open = $this->election($group, 'open', 1);
        $awaiting = $this->election($group, 'awaiting_acceptance', 2);
        $filled = $this->election($group, 'filled', 3);

        $snapshot = app(CurrentElectionCenterService::class)->forUser($user);
        $ids = collect($snapshot['systemic'])->pluck('source_id')->all();

        $this->assertContains($open->id, $ids);
        $this->assertContains($awaiting->id, $ids);
        $this->assertNotContains($filled->id, $ids);
    }

    public function test_open_systemic_election_previews_new_member_eligibility_without_mutating_snapshot(): void
    {
        [$group, $user] = $this->memberGroup();
        $open = $this->election($group, 'open', 1);
        $this->assertDatabaseCount('election_eligibility_snapshots', 0);

        $snapshot = app(CurrentElectionCenterService::class)->forUser($user);
        $item = collect($snapshot['systemic'])->firstWhere('source_id', $open->id);

        $this->assertNotNull($item);
        $this->assertTrue($item['eligible']);
        $this->assertTrue($item['can_vote_now']);
        $this->assertFalse($item['has_voted']);
        $this->assertSame('ثبت رأی', $item['primary_action']['label']);
        $this->assertDatabaseCount('election_eligibility_snapshots', 0);
    }

    public function test_existing_systemic_snapshot_can_make_open_election_detail_only(): void
    {
        [$group, $user] = $this->memberGroup();
        $open = $this->election($group, 'open', 1);
        ElectionEligibilitySnapshot::create([
            'election_id' => $open->id,
            'user_id' => $user->id,
            'voter_eligible' => false,
            'selectable_eligible' => false,
            'voter_exclusion_reason' => 'test_exclusion',
            'selectable_exclusion_reason' => 'test_exclusion',
            'membership_role' => 1,
            'membership_status' => 1,
            'snapshot_version' => 'test-v1',
            'captured_at' => now(),
        ]);

        $item = collect(app(CurrentElectionCenterService::class)->forUser($user)['systemic'])
            ->firstWhere('source_id', $open->id);

        $this->assertNotNull($item);
        $this->assertFalse($item['eligible']);
        $this->assertFalse($item['can_vote_now']);
        $this->assertSame('مشاهده جزئیات', $item['primary_action']['label']);
    }

    public function test_internal_center_contains_only_active_main_type_zero_and_uses_poll_policy(): void
    {
        [$group, $user] = $this->memberGroup();
        $internal = Poll::create([
            'group_id' => $group->id,
            'created_by' => $user->id,
            'question' => 'انتخابات داخلی فعال',
            'main_type' => 0,
            'is_active' => true,
            'expires_at' => now()->addDay(),
        ]);
        Poll::create([
            'group_id' => $group->id,
            'created_by' => $user->id,
            'question' => 'نظرسنجی عادی',
            'main_type' => 1,
            'is_active' => true,
            'expires_at' => now()->addDay(),
        ]);
        Poll::create([
            'group_id' => $group->id,
            'created_by' => $user->id,
            'question' => 'انتخابات داخلی منقضی',
            'main_type' => 0,
            'is_active' => true,
            'expires_at' => now()->subMinute(),
        ]);

        $snapshot = app(CurrentElectionCenterService::class)->forUser($user);
        $items = collect($snapshot['internal']);

        $this->assertCount(1, $items);
        $item = $items->first();
        $this->assertSame($internal->id, $item['source_id']);
        $this->assertTrue($item['eligible']);
        $this->assertTrue($item['can_vote_now']);
        $this->assertSame('ثبت رأی', $item['primary_action']['label']);
    }

    private function memberGroup(): array
    {
        $group = Group::create([
            'name' => 'Current elections test group',
            'group_type' => 'public',
            'location_level' => 'neighborhood',
            'is_open' => true,
        ]);
        $user = User::factory()->create(['is_system' => false]);
        GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => 1,
            'status' => 1,
        ]);

        return [$group, $user];
    }

    private function election(Group $group, string $status, int $cycle): Election
    {
        return Election::create([
            'group_id' => $group->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(5),
            'is_closed' => ! in_array($status, ['scheduled', 'open'], true),
            'lifecycle_status' => $status,
            'cycle_number' => $cycle,
        ]);
    }
}
