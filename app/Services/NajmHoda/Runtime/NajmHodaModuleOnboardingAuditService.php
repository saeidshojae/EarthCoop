<?php

namespace App\Services\NajmHoda\Runtime;

use Carbon\CarbonImmutable;

class NajmHodaModuleOnboardingAuditService
{
    public function __construct(
        protected RuntimeEventBus $eventBus
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function audit(string $module, string $prefix, ?int $windowHours = null, ?int $eventLimit = null): array
    {
        $module = trim(strtolower($module));
        $prefix = trim($prefix);
        $windowHours = max(1, $windowHours ?? 24);
        $eventLimit = max(100, $eventLimit ?? 5000);

        $events = $this->eventBus->recent(null, $eventLimit);
        $events = $this->filterByWindow($events, $windowHours);

        $moduleEvents = array_values(array_filter($events, static fn (array $entry): bool => str_starts_with((string) ($entry['event'] ?? ''), $prefix)));
        $moduleEventNames = array_values(array_unique(array_map(
            static fn (array $entry): string => (string) ($entry['event'] ?? ''),
            $moduleEvents
        )));

        $checks = [
            'contract_detected' => count($moduleEvents) > 0,
            'requested_present' => $this->hasSuffix($moduleEventNames, ['requested']),
            'success_present' => $this->hasSuffix($moduleEventNames, ['succeeded', 'created', 'updated']),
            'failure_present' => $this->hasSuffix($moduleEventNames, ['failed', 'rejected', 'deleted']),
            'policy_link_observed' => $this->hasPolicySignals($events, $module),
        ];

        $trueCount = count(array_filter($checks, static fn (bool $value): bool => $value));
        $score = round($trueCount / max(1, count($checks)), 4);

        $result = [
            'generated_at' => now()->toIso8601String(),
            'module' => $module,
            'prefix' => $prefix,
            'window_hours' => $windowHours,
            'event_limit' => $eventLimit,
            'event_count' => count($events),
            'module_event_count' => count($moduleEvents),
            'module_event_names' => $moduleEventNames,
            'automated_checks' => $checks,
            'automated_score' => $score,
            'manual_checklist' => [
                'observer_or_listener_registered' => 'ثبت observer/listener روی نقاط mutation دامنه',
                'tests_added' => 'وجود تست instrumentation/policy برای ماژول',
                'matrix_updated' => 'به‌روزرسانی `docs/NAJM_HODA_PHASE6_DOMAIN_EVENT_MATRIX.fa.md`',
                'tasks_updated' => 'به‌روزرسانی `docs/NAJM_HODA_PHASE6_TASKS.fa.md`',
                'execution_log_updated' => 'ثبت اجرای تغییر در `docs/NAJM_HODA_EXECUTION_LOG.fa.md`',
            ],
        ];

        $this->eventBus->emit('najm_hoda.autonomy.onboarding_audit.generated', [
            'module' => $module,
            'prefix' => $prefix,
            'automated_score' => $score,
            'contract_detected' => (bool) $checks['contract_detected'],
            'policy_link_observed' => (bool) $checks['policy_link_observed'],
            'scope' => 'autonomy',
            'risk' => 'low',
        ]);

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $events
     * @return array<int, array<string, mixed>>
     */
    protected function filterByWindow(array $events, int $windowHours): array
    {
        $cutoff = now()->subHours($windowHours);

        return array_values(array_filter($events, static function (array $event) use ($cutoff): bool {
            $timestamp = $event['timestamp'] ?? null;
            if (!is_string($timestamp) || trim($timestamp) === '') {
                return false;
            }

            try {
                return CarbonImmutable::parse($timestamp)->greaterThanOrEqualTo($cutoff);
            } catch (\Throwable) {
                return false;
            }
        }));
    }

    /**
     * @param array<int, string> $eventNames
     * @param array<int, string> $suffixes
     */
    protected function hasSuffix(array $eventNames, array $suffixes): bool
    {
        foreach ($eventNames as $event) {
            foreach ($suffixes as $suffix) {
                if (str_ends_with($event, '.' . $suffix)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $events
     */
    protected function hasPolicySignals(array $events, string $module): bool
    {
        foreach ($events as $entry) {
            $name = (string) ($entry['event'] ?? '');
            if (!in_array($name, [
                'najm_hoda.autonomy.safety.blocked',
                'najm_hoda.autonomy.governance.alert.raised',
                'najm_hoda.autonomy.approval.requested',
            ], true)) {
                continue;
            }

            $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];
            if ((string) ($payload['domain'] ?? '') === $module) {
                return true;
            }
        }

        return false;
    }
}

