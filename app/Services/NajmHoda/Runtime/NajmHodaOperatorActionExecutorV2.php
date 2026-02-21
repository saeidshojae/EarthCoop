<?php

namespace App\Services\NajmHoda\Runtime;

use Illuminate\Support\Facades\Cache;

class NajmHodaOperatorActionExecutorV2
{
    protected string $idempotencyPrefix = 'najm_hoda:autonomy:executor:idempotency:';
    protected string $cooldownPrefix = 'najm_hoda:autonomy:executor:cooldown:';

    public function __construct(
        protected RuntimeEventBus $eventBus,
        protected ?NajmHodaAutonomyCostLedgerService $costLedger = null
    ) {
        $this->costLedger = $this->costLedger ?? new NajmHodaAutonomyCostLedgerService($eventBus);
    }

    /**
     * @param array<int, array<string, mixed>> $plan
     * @return array<int, array<string, mixed>>
     */
    public function execute(array $plan, string $runId): array
    {
        if (!(bool) config('najm-hoda.runtime.autonomy.executor.enabled', true)) {
            return [];
        }

        $results = [];
        $maxRetries = max(0, (int) config('najm-hoda.runtime.autonomy.executor.max_retries', 1));
        $idempotencyMinutes = max(1, (int) config('najm-hoda.runtime.autonomy.executor.idempotency_ttl_minutes', 60));

        foreach ($plan as $item) {
            if (!is_array($item)) {
                continue;
            }

            $action = (string) ($item['action'] ?? '');
            $mode = (string) ($item['mode'] ?? 'propose');
            $risk = (string) ($item['risk'] ?? 'unknown');

            if ($mode !== 'apply') {
                $results[] = $this->skip($runId, $action, 'mode_not_apply');
                continue;
            }

            if ($risk !== 'low') {
                $results[] = $this->skip($runId, $action, 'risk_not_low');
                continue;
            }

            $idempotencyKey = $this->idempotencyKey($action, (array) ($item['input'] ?? []));
            if (Cache::has($idempotencyKey)) {
                $results[] = $this->skip($runId, $action, 'idempotent_duplicate');
                continue;
            }

            if ($this->isOnCooldown($action)) {
                $results[] = $this->skip($runId, $action, 'cooldown_active');
                continue;
            }

            $estimatedCost = $this->costLedger->estimateForAction($action);
            $budgetCheck = $this->costLedger->canSpend($estimatedCost);
            if (!(bool) ($budgetCheck['allowed'] ?? false)) {
                $results[] = $this->skip($runId, $action, (string) ($budgetCheck['reason'] ?? 'budget_blocked'));
                continue;
            }

            $attempt = 0;
            $executed = null;
            while ($attempt <= $maxRetries) {
                $attempt++;
                try {
                    $context = $this->executeAction($action, $item, $runId);
                    Cache::put($idempotencyKey, 1, now()->addMinutes($idempotencyMinutes));
                    $this->armCooldown($action);
                    $cost = $this->costLedger->record($action, $estimatedCost, [
                        'run_id' => $runId,
                    ]);

                    $executed = [
                        'action' => $action,
                        'status' => 'executed',
                        'attempt' => $attempt,
                        'context' => $context,
                        'cost' => $cost,
                    ];

                    $this->eventBus->emit('najm_hoda.autonomy.executor.executed', [
                        'run_id' => $runId,
                        'action' => $action,
                        'attempt' => $attempt,
                    ]);
                    break;
                } catch (\Throwable $exception) {
                    if ($attempt > $maxRetries) {
                        $executed = [
                            'action' => $action,
                            'status' => 'failed',
                            'attempt' => $attempt,
                            'error' => $exception->getMessage(),
                        ];
                        $this->eventBus->emit('najm_hoda.autonomy.executor.failed', [
                            'run_id' => $runId,
                            'action' => $action,
                            'attempt' => $attempt,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
            }

            if ($executed !== null) {
                $results[] = $executed;
            }
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    protected function executeAction(string $action, array $item, string $runId): array
    {
        return match ($action) {
            'run_ops_monitor' => $this->emitExecutionIntent($runId, $action, [
                'intent' => 'ops_monitor_trigger',
            ]),
            'propose_engagement_recommendations' => $this->emitExecutionIntent($runId, $action, [
                'intent' => 'dispatch_recommendations',
                'recommendation_count' => count((array) ($item['recommendations'] ?? [])),
            ]),
            'prioritize_overdue_action_items' => $this->emitExecutionIntent($runId, $action, [
                'intent' => 'prioritize_backlog',
            ]),
            default => throw new \RuntimeException('unsupported_action'),
        };
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    protected function emitExecutionIntent(string $runId, string $action, array $context): array
    {
        $payload = array_merge([
            'run_id' => $runId,
            'action' => $action,
        ], $context);

        $this->eventBus->emit('najm_hoda.autonomy.executor.intent', $payload);
        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function skip(string $runId, string $action, string $reason): array
    {
        $payload = [
            'run_id' => $runId,
            'action' => $action,
            'status' => 'skipped',
            'reason' => $reason,
        ];

        $this->eventBus->emit('najm_hoda.autonomy.executor.skipped', $payload);
        return $payload;
    }

    /**
     * @param array<string, mixed> $input
     */
    protected function idempotencyKey(string $action, array $input): string
    {
        ksort($input);
        return $this->idempotencyPrefix . sha1($action . '|' . json_encode($input));
    }

    protected function isOnCooldown(string $action): bool
    {
        return Cache::has($this->cooldownPrefix . $action);
    }

    protected function armCooldown(string $action): void
    {
        $defaultSeconds = max(0, (int) config('najm-hoda.runtime.autonomy.executor.default_action_cooldown_seconds', 60));
        $actionSeconds = (int) config("najm-hoda.runtime.autonomy.executor.action_cooldowns.{$action}", $defaultSeconds);
        $cooldownSeconds = max(0, $actionSeconds);
        if ($cooldownSeconds <= 0) {
            return;
        }

        Cache::put($this->cooldownPrefix . $action, 1, now()->addSeconds($cooldownSeconds));
    }
}
