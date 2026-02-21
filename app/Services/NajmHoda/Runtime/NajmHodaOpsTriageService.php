<?php

namespace App\Services\NajmHoda\Runtime;

use Illuminate\Support\Facades\Cache;

class NajmHodaOpsTriageService
{
    protected string $degradedKey = 'najm_hoda:ops:degraded_until';
    protected string $entryRateMultiplierKey = 'najm_hoda:ops:entry_rate_multiplier';
    protected string $playbookCooldownPrefix = 'najm_hoda:ops:playbook:cooldown:';
    protected string $playbookTelemetryPrefix = 'najm_hoda:ops:playbook:telemetry:';
    protected string $playbookTelemetryIndexKey = 'najm_hoda:ops:telemetry:index';

    public function __construct(
        protected RuntimeEventBus $eventBus
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function processSnapshot(array $snapshot, bool $applyPlaybooks = true): array
    {
        $incidents = $this->detectIncidents($snapshot);

        foreach ($incidents as $incident) {
            $this->eventBus->emit('najm_hoda.ops.incident.detected', $incident);
        }

        if ($applyPlaybooks) {
            $this->applyLowRiskPlaybooks($snapshot, $incidents);
        }

        return $incidents;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function detectIncidents(array $snapshot): array
    {
        $status = (string) ($snapshot['status'] ?? 'healthy');
        $errorRate = (float) data_get($snapshot, 'metrics.error_rate_percent', 0);
        $unresolved = (int) data_get($snapshot, 'metrics.unresolved_requests', 0);
        $counts = (array) ($snapshot['counts'] ?? []);

        $incidents = [];

        if ($status === 'critical') {
            $incidents[] = [
                'severity' => 'critical',
                'code' => 'OPS_CRITICAL_HEALTH',
                'title' => 'Najm Hoda runtime health is critical',
                'details' => [
                    'error_rate_percent' => $errorRate,
                    'unresolved_requests' => $unresolved,
                    'counts' => $counts,
                ],
                'recommended_playbook' => 'set_degraded_mode',
            ];
        } elseif ($status === 'warning') {
            $incidents[] = [
                'severity' => 'warning',
                'code' => 'OPS_WARNING_HEALTH',
                'title' => 'Najm Hoda runtime health is degraded',
                'details' => [
                    'error_rate_percent' => $errorRate,
                    'unresolved_requests' => $unresolved,
                    'counts' => $counts,
                ],
                'recommended_playbook' => 'set_degraded_mode',
            ];
        }

        if ($errorRate > 0 && (int) ($counts['response_failed'] ?? 0) > 0) {
            $incidents[] = [
                'severity' => $status === 'critical' ? 'critical' : 'warning',
                'code' => 'OPS_FAILURE_SPIKE',
                'title' => 'Spike in failed Najm Hoda responses detected',
                'details' => [
                    'response_failed' => (int) ($counts['response_failed'] ?? 0),
                    'response_ready' => (int) ($counts['response_ready'] ?? 0),
                    'error_rate_percent' => $errorRate,
                ],
                'recommended_playbook' => 'set_degraded_mode',
            ];
        }

        return $incidents;
    }

    /**
     * @param array<int, array<string, mixed>> $incidents
     */
    protected function applyLowRiskPlaybooks(array $snapshot, array $incidents): void
    {
        $enabled = (bool) config('najm-hoda.runtime.ops.triage.auto_playbook_enabled', true);
        if (!$enabled) {
            return;
        }

        $status = (string) ($snapshot['status'] ?? 'healthy');
        $actions = $this->resolvePlaybookActions($status);
        $maxActions = max(1, (int) config('najm-hoda.runtime.ops.playbooks.max_actions_per_run', 5));
        $actions = array_slice($actions, 0, $maxActions);

        foreach ($actions as $action) {
            $action = (string) $action;
            if ($action === '') {
                continue;
            }

            $safety = $this->validateActionSafety($action);
            if (!(bool) ($safety['allowed'] ?? false)) {
                $reason = (string) ($safety['reason'] ?? 'blocked');
                $this->recordPlaybookTelemetry($action, 'skipped', $status, $reason);
                $this->eventBus->emit('najm_hoda.ops.playbook.skipped', [
                    'action' => $action,
                    'status' => $status,
                    'reason' => $reason,
                ]);
                continue;
            }

            if ($this->isActionOnCooldown($action)) {
                $this->recordPlaybookTelemetry($action, 'skipped', $status, 'cooldown_active');
                $this->eventBus->emit('najm_hoda.ops.playbook.skipped', [
                    'action' => $action,
                    'status' => $status,
                    'reason' => 'cooldown_active',
                ]);
                continue;
            }

            $result = $this->executePlaybookAction($action, $status, $incidents);
            $outcome = (string) ($result['result'] ?? 'ok');
            $this->recordPlaybookTelemetry($action, $outcome, $status, null);
            $this->armActionCooldown($action);
            $this->eventBus->emit('najm_hoda.ops.playbook.executed', array_merge([
                'action' => $action,
                'status' => $status,
                'result' => $outcome,
                'incidents' => count($incidents),
            ], (array) ($result['context'] ?? [])));
        }
    }

    /**
     * @return array<int, string>
     */
    protected function resolvePlaybookActions(string $status): array
    {
        $configured = config("najm-hoda.runtime.ops.playbooks.plan.{$status}");
        if (is_array($configured) && !empty($configured)) {
            return array_values(array_map(static fn ($v): string => (string) $v, $configured));
        }

        return match ($status) {
            'critical' => ['set_degraded_mode', 'set_entry_rate_multiplier_critical'],
            'warning' => ['set_degraded_mode', 'set_entry_rate_multiplier_warning'],
            default => ['clear_degraded_mode', 'set_entry_rate_multiplier_base'],
        };
    }

    protected function validateActionSafety(string $action): array
    {
        $catalog = config("najm-hoda.runtime.ops.playbooks.catalog.{$action}", []);
        if (!is_array($catalog)) {
            $catalog = [];
        }

        $enabled = (bool) ($catalog['enabled'] ?? true);
        if (!$enabled) {
            return ['allowed' => false, 'reason' => 'action_disabled'];
        }

        $risk = (string) ($catalog['risk'] ?? 'low');
        $enforceLowRiskOnly = (bool) config('najm-hoda.runtime.ops.playbooks.enforce_low_risk_only', true);
        if ($enforceLowRiskOnly && $risk !== 'low') {
            return ['allowed' => false, 'reason' => 'high_risk_blocked'];
        }

        return ['allowed' => true];
    }

    /**
     * @param array<int, array<string, mixed>> $incidents
     * @return array<string, mixed>
     */
    protected function executePlaybookAction(string $action, string $status, array $incidents): array
    {
        $ttlSeconds = max(60, (int) config('najm-hoda.runtime.ops.triage.degraded_ttl_seconds', 900));
        $until = now()->addSeconds($ttlSeconds)->timestamp;

        return match ($action) {
            'clear_degraded_mode' => $this->clearDegradedMode(),
            'set_degraded_mode' => $this->setDegradedMode($until, $ttlSeconds),
            'set_entry_rate_multiplier_base' => $this->setEntryRateMultiplier('base', 1800),
            'set_entry_rate_multiplier_warning' => $this->setEntryRateMultiplier('warning', $ttlSeconds + 60),
            'set_entry_rate_multiplier_critical' => $this->setEntryRateMultiplier('critical', $ttlSeconds + 60),
            default => [
                'result' => 'skipped',
                'context' => [
                    'reason' => 'unknown_action',
                    'action' => $action,
                    'status' => $status,
                    'incidents' => count($incidents),
                ],
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function clearDegradedMode(): array
    {
        Cache::forget($this->degradedKey);

        return [
            'result' => 'ok',
            'context' => [
                'degraded_cleared' => true,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function setDegradedMode(int $until, int $ttlSeconds): array
    {
        Cache::put($this->degradedKey, $until, now()->addSeconds($ttlSeconds + 60));

        return [
            'result' => 'ok',
            'context' => [
                'degraded_until' => $until,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function setEntryRateMultiplier(string $mode, int $ttlSeconds): array
    {
        $multiplier = (float) config("najm-hoda.runtime.ops.triage.entry_rate_multiplier_{$mode}", 1.0);
        $multiplier = max(0.1, min(1.0, $multiplier));
        Cache::put($this->entryRateMultiplierKey, $multiplier, now()->addSeconds(max(60, $ttlSeconds)));

        return [
            'result' => 'ok',
            'context' => [
                'entry_rate_multiplier' => $multiplier,
                'mode' => $mode,
            ],
        ];
    }

    protected function isActionOnCooldown(string $action): bool
    {
        $key = $this->playbookCooldownPrefix . $action;
        return Cache::has($key);
    }

    protected function armActionCooldown(string $action): void
    {
        $actionCooldown = (int) config("najm-hoda.runtime.ops.playbooks.action_cooldowns.{$action}", 0);
        $defaultCooldown = (int) config('najm-hoda.runtime.ops.playbooks.default_action_cooldown_seconds', 0);
        $cooldownSeconds = max(0, $actionCooldown > 0 ? $actionCooldown : $defaultCooldown);

        if ($cooldownSeconds <= 0) {
            return;
        }

        Cache::put(
            $this->playbookCooldownPrefix . $action,
            1,
            now()->addSeconds($cooldownSeconds)
        );
    }

    protected function recordPlaybookTelemetry(string $action, string $outcome, string $status, ?string $reason): void
    {
        $bucket = now()->format('YmdHi');
        $ttl = now()->addDays(2);

        $keys = [
            "{$this->playbookTelemetryPrefix}{$action}:{$bucket}:total",
            "{$this->playbookTelemetryPrefix}{$action}:{$bucket}:{$outcome}",
        ];

        foreach ($keys as $key) {
            Cache::add($key, 0, $ttl);
            Cache::increment($key);
        }
        $this->indexTelemetryKeys($keys);

        $total = (int) Cache::get("{$this->playbookTelemetryPrefix}{$action}:{$bucket}:total", 0);
        $outcomeCount = (int) Cache::get("{$this->playbookTelemetryPrefix}{$action}:{$bucket}:{$outcome}", 0);

        $this->eventBus->emit('najm_hoda.ops.playbook.telemetry', [
            'action' => $action,
            'status' => $status,
            'outcome' => $outcome,
            'reason' => $reason,
            'bucket' => $bucket,
            'count_total' => $total,
            'count_outcome' => $outcomeCount,
        ]);
    }

    /**
     * @param array<int, string> $keys
     */
    protected function indexTelemetryKeys(array $keys): void
    {
        $index = Cache::get($this->playbookTelemetryIndexKey, []);
        if (!is_array($index)) {
            $index = [];
        }

        $nowTs = time();
        foreach ($keys as $key) {
            $key = (string) $key;
            if ($key === '') {
                continue;
            }

            $index[] = [
                'key' => $key,
                'created_at' => $nowTs,
            ];
        }

        $maxIndexSize = max(100, (int) config('najm-hoda.runtime.ops.retention.telemetry_index_max_size', 5000));
        if (count($index) > $maxIndexSize) {
            $index = array_slice($index, -1 * $maxIndexSize);
        }

        Cache::put($this->playbookTelemetryIndexKey, $index, now()->addDays(7));
    }
}
