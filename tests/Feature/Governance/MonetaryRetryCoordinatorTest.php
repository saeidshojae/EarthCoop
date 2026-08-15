<?php

namespace Tests\Feature\Governance;

use App\Models\Group;
use App\Modules\Governance\Models\EconomicAction;
use App\Modules\Governance\Models\Proposal;
use App\Modules\Governance\Models\Resolution;
use App\Modules\Governance\Services\PublicExecutionBridge;
use App\Modules\NajmBahar\Services\MonetaryRetryCoordinator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MonetaryRetryCoordinatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_retry_backoff_is_bounded_and_dead_letter_is_never_due(): void
    {
        $coordinator = app(MonetaryRetryCoordinator::class);
        $now = Carbon::parse('2026-08-09 12:00:00');

        $this->assertFalse($coordinator->isDue(1, $now->copy()->subMinutes(4), $now));
        $this->assertTrue($coordinator->isDue(1, $now->copy()->subMinutes(5), $now));
        $this->assertFalse($coordinator->isDue(2, $now->copy()->subMinutes(14), $now));
        $this->assertTrue($coordinator->isDue(2, $now->copy()->subMinutes(15), $now));
        $this->assertFalse($coordinator->isDue(3, $now->copy()->subMinutes(59), $now));
        $this->assertTrue($coordinator->isDue(3, $now->copy()->subMinutes(60), $now));
        $this->assertFalse($coordinator->isDue(4, $now->copy()->subMinutes(359), $now));
        $this->assertTrue($coordinator->isDue(4, $now->copy()->subMinutes(360), $now));
        $this->assertFalse($coordinator->isDue(5, $now->copy()->subDay(), $now));
    }

    public function test_scheduler_skips_not_due_failed_and_all_dead_letter_actions(): void
    {
        $group = Group::create([
            'name' => 'مجمع تست زمان‌بندی retry',
            'group_type' => 'public',
            'location_level' => 'neighborhood',
        ]);
        $proposal = Proposal::create([
            'group_id' => $group->id,
            'type' => 'public_project',
            'title' => 'پروژه تست retry scheduler',
            'status' => 'approved',
        ]);
        $resolution = Resolution::create([
            'proposal_id' => $proposal->id,
            'group_id' => $group->id,
            'type' => 'public_project',
            'status' => 'adopted',
        ]);

        $notDue = EconomicAction::create([
            'resolution_id' => $resolution->id,
            'group_id' => $group->id,
            'action_type' => PublicExecutionBridge::PUBLIC_PROJECT_EXECUTION_AUTHORIZED,
            'status' => 'failed',
            'idempotency_key' => 'retry-not-due:' . $resolution->id,
            'payload' => [],
            'attempts' => 1,
            'failure_reason' => 'synthetic not-due failure',
            'failed_at' => now(),
        ]);

        $deadLetter = EconomicAction::create([
            'resolution_id' => $resolution->id,
            'group_id' => $group->id,
            'action_type' => PublicExecutionBridge::PUBLIC_PROJECT_EXECUTION_AUTHORIZED,
            'status' => 'dead_letter',
            'idempotency_key' => 'retry-dead-letter:' . $resolution->id,
            'payload' => [],
            'attempts' => 5,
            'failure_reason' => 'synthetic terminal failure',
            'failed_at' => now()->subDay(),
        ]);

        $result = app(MonetaryRetryCoordinator::class)->retryDue(20);

        $this->assertSame(0, $result['attempted']);
        $this->assertSame('failed', $notDue->fresh()->status);
        $this->assertSame(1, (int) $notDue->fresh()->attempts);
        $this->assertSame('dead_letter', $deadLetter->fresh()->status);
        $this->assertSame(5, (int) $deadLetter->fresh()->attempts);
    }
}
