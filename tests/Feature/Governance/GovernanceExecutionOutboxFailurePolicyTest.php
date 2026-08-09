<?php

namespace Tests\Feature\Governance;

use App\Models\Group;
use App\Modules\Governance\Models\EconomicAction;
use App\Modules\Governance\Models\Proposal;
use App\Modules\Governance\Models\Resolution;
use App\Modules\Governance\Services\PublicExecutionBridge;
use App\Modules\NajmBahar\Services\GovernanceExecutionOutboxConsumer;
use App\Modules\NajmBahar\Services\MonetaryOperationsReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GovernanceExecutionOutboxFailurePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_invalid_consumption_dead_letters_action_after_bounded_retry_budget(): void
    {
        $group = Group::create([
            'name' => 'مجمع تست Dead Letter',
            'group_type' => 'public',
            'location_level' => 'neighborhood',
        ]);

        $proposal = Proposal::create([
            'group_id' => $group->id,
            'type' => 'public_project',
            'title' => 'پروژه تست سیاست خطا',
            'status' => 'approved',
        ]);

        $resolution = Resolution::create([
            'proposal_id' => $proposal->id,
            'group_id' => $group->id,
            'type' => 'public_project',
            'status' => 'adopted',
            'effect_status' => 'pending_bridge',
            'financial_effect' => ['action' => 'PUBLIC_PROJECT_APPROVED', 'requested_capital_gol' => 10],
            'adopted_at' => now(),
            'effective_at' => now(),
        ]);

        $action = EconomicAction::create([
            'resolution_id' => $resolution->id,
            'group_id' => $group->id,
            'action_type' => PublicExecutionBridge::PUBLIC_PROJECT_EXECUTION_AUTHORIZED,
            'status' => 'pending',
            'idempotency_key' => 'dead-letter-test:' . $resolution->id,
            'payload' => [],
            'attempts' => 0,
        ]);

        $consumer = app(GovernanceExecutionOutboxConsumer::class);

        for ($attempt = 1; $attempt <= GovernanceExecutionOutboxConsumer::MAX_ATTEMPTS; $attempt++) {
            try {
                $consumer->consume($action->fresh());
                $this->fail('Invalid outbox payload unexpectedly succeeded.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('payload is incomplete', strtolower($e->getMessage()));
            }

            $fresh = $action->fresh();
            $this->assertSame($attempt, (int) $fresh->attempts);
            $this->assertSame(
                $attempt === GovernanceExecutionOutboxConsumer::MAX_ATTEMPTS ? 'dead_letter' : 'failed',
                $fresh->status
            );
            $this->assertNotNull($fresh->failed_at);
        }

        $deadLettered = $action->fresh();
        $attemptsBeforeBlockedRetry = (int) $deadLettered->attempts;

        try {
            $consumer->consume($deadLettered);
            $this->fail('Dead-lettered action unexpectedly retried.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('dead-lettered', strtolower($e->getMessage()));
        }

        $this->assertSame('dead_letter', $action->fresh()->status);
        $this->assertSame($attemptsBeforeBlockedRetry, (int) $action->fresh()->attempts);

        $report = app(MonetaryOperationsReportService::class);
        $this->assertSame(1, $report->summary()['execution_outbox']['dead_letter']);
        $item = $report->problemItems()->firstWhere('kind', 'execution_outbox');
        $this->assertNotNull($item);
        $this->assertSame((int) $action->id, $item['id']);
        $this->assertSame('dead_letter', $item['status']);
        $this->assertSame('recover_dead_letter_then_retry', $item['operator_action']);
    }

    public function test_dead_letter_requires_explicit_recovery_before_becoming_retryable(): void
    {
        $group = Group::create([
            'name' => 'مجمع تست Recovery',
            'group_type' => 'public',
            'location_level' => 'neighborhood',
        ]);

        $proposal = Proposal::create([
            'group_id' => $group->id,
            'type' => 'public_project',
            'title' => 'پروژه تست بازیابی',
        ]);

        $resolution = Resolution::create([
            'proposal_id' => $proposal->id,
            'group_id' => $group->id,
            'type' => 'public_project',
            'status' => 'adopted',
        ]);

        $action = EconomicAction::create([
            'resolution_id' => $resolution->id,
            'group_id' => $group->id,
            'action_type' => PublicExecutionBridge::PUBLIC_PROJECT_EXECUTION_AUTHORIZED,
            'status' => 'dead_letter',
            'idempotency_key' => 'dead-letter-recovery-test:' . $resolution->id,
            'payload' => [],
            'attempts' => GovernanceExecutionOutboxConsumer::MAX_ATTEMPTS,
            'failure_reason' => 'synthetic failure',
            'failed_at' => now(),
        ]);

        $recovered = app(GovernanceExecutionOutboxConsumer::class)->recoverDeadLetter($action);

        $this->assertSame('failed', $recovered->status);
        $this->assertSame(0, (int) $recovered->attempts);
        $this->assertNull($recovered->failure_reason);
        $this->assertNull($recovered->failed_at);

        $report = app(MonetaryOperationsReportService::class);
        $this->assertSame(1, $report->summary()['execution_outbox']['failed']);
        $this->assertSame(0, $report->summary()['execution_outbox']['dead_letter']);
    }
}
