<?php

namespace Tests\Feature\Elections;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Models\Election;
use App\Models\ElectionLifecycleTransition;
use App\Models\Group;
use App\Services\Elections\ElectionLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ElectionLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_transition_is_persisted_and_audited(): void
    {
        $election = $this->election(ElectionLifecycleStatus::Open);
        $service = app(ElectionLifecycleService::class);

        $result = $service->transition(
            $election,
            ElectionLifecycleStatus::Closed,
            'test_close',
            'test',
            null,
            'test:close',
            ['proof' => true],
        );

        $this->assertSame(ElectionLifecycleStatus::Closed, $result->lifecycle_status);
        $this->assertTrue((bool) $result->is_closed);
        $this->assertDatabaseHas('election_lifecycle_transitions', [
            'election_id' => $election->id,
            'from_status' => ElectionLifecycleStatus::Open->value,
            'to_status' => ElectionLifecycleStatus::Closed->value,
            'reason' => 'test_close',
            'source' => 'test',
            'reference' => 'test:close',
        ]);
    }

    public function test_retrying_same_target_is_idempotent_and_does_not_duplicate_audit_row(): void
    {
        $election = $this->election(ElectionLifecycleStatus::Open);
        $service = app(ElectionLifecycleService::class);

        $service->transition($election, ElectionLifecycleStatus::Closed, 'first_close', 'test');
        $service->transition($election->fresh(), ElectionLifecycleStatus::Closed, 'retry_close', 'test');

        $this->assertSame(1, ElectionLifecycleTransition::where('election_id', $election->id)->count());
        $this->assertSame(ElectionLifecycleStatus::Closed, $election->fresh()->lifecycle_status);
    }

    public function test_invalid_transition_is_rejected_without_mutation_or_audit(): void
    {
        $election = $this->election(ElectionLifecycleStatus::Scheduled);
        $service = app(ElectionLifecycleService::class);

        try {
            $service->transition($election, ElectionLifecycleStatus::Closed, 'skip_open', 'test');
            $this->fail('Invalid transition should have thrown.');
        } catch (InvalidArgumentException) {
            $this->assertSame(ElectionLifecycleStatus::Scheduled, $election->fresh()->lifecycle_status);
            $this->assertSame(0, ElectionLifecycleTransition::where('election_id', $election->id)->count());
        }
    }

    public function test_advance_due_opens_scheduled_election_when_start_is_reached(): void
    {
        $election = $this->election(
            ElectionLifecycleStatus::Scheduled,
            now()->subMinute(),
            now()->addHour(),
        );

        $result = app(ElectionLifecycleService::class)->advanceDue($election);

        $this->assertSame(ElectionLifecycleStatus::Open, $result->lifecycle_status);
        $this->assertFalse((bool) $result->is_closed);
        $this->assertDatabaseHas('election_lifecycle_transitions', [
            'election_id' => $election->id,
            'from_status' => 'scheduled',
            'to_status' => 'open',
            'reason' => 'scheduled_start_reached',
            'source' => 'scheduler',
        ]);
    }

    public function test_advance_due_closes_open_election_when_voting_window_elapsed(): void
    {
        $election = $this->election(
            ElectionLifecycleStatus::Open,
            now()->subDay(),
            now()->subMinute(),
        );

        $result = app(ElectionLifecycleService::class)->advanceDue($election);

        $this->assertSame(ElectionLifecycleStatus::Closed, $result->lifecycle_status);
        $this->assertTrue((bool) $result->is_closed);
        $this->assertDatabaseHas('election_lifecycle_transitions', [
            'election_id' => $election->id,
            'from_status' => 'open',
            'to_status' => 'closed',
            'reason' => 'voting_window_elapsed',
            'source' => 'scheduler',
        ]);
    }

    public function test_process_lifecycle_command_is_retry_safe(): void
    {
        $election = $this->election(
            ElectionLifecycleStatus::Open,
            now()->subDay(),
            now()->subMinute(),
        );

        $this->artisan('elections:process-lifecycle --limit=50 --fail-on-error')
            ->expectsOutputToContain('processed=1 advanced=1 errors=0')
            ->assertSuccessful();

        $this->artisan('elections:process-lifecycle --limit=50 --fail-on-error')
            ->expectsOutputToContain('processed=0 advanced=0 errors=0')
            ->assertSuccessful();

        $this->assertSame(1, ElectionLifecycleTransition::where('election_id', $election->id)->count());
    }

    private function election(
        ElectionLifecycleStatus $status,
        mixed $startsAt = null,
        mixed $endsAt = null,
    ): Election {
        $group = Group::create([
            'name' => 'Election lifecycle test group',
            'group_type' => 'public',
            'location_level' => 'neighborhood',
        ]);

        return Election::create([
            'group_id' => $group->id,
            'starts_at' => $startsAt ?? now()->addHour(),
            'ends_at' => $endsAt ?? now()->addDay(),
            'is_closed' => ! in_array($status, [ElectionLifecycleStatus::Scheduled, ElectionLifecycleStatus::Open], true),
            'lifecycle_status' => $status,
        ]);
    }
}
