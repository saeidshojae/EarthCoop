<?php

namespace App\Services\NajmHoda\Runtime;

class NajmHodaRunbookRegistryService
{
    public function __construct(
        protected RuntimeEventBus $eventBus
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $runbooks = config('najm-hoda.runtime.autonomy.runbooks.registry', []);
        if (!is_array($runbooks)) {
            return [];
        }

        return array_values(array_map(function ($item): array {
            $entry = is_array($item) ? $item : [];
            $entry['id'] = (string) ($entry['id'] ?? 'unknown');
            $entry['title'] = (string) ($entry['title'] ?? 'Untitled');
            $entry['owner'] = (string) ($entry['owner'] ?? 'unknown');
            $entry['version'] = (string) ($entry['version'] ?? '0.0.0');
            $entry['status'] = (string) ($entry['status'] ?? 'draft');
            $entry['updated_at'] = (string) ($entry['updated_at'] ?? now()->toDateString());
            $entry['checklist'] = array_values(array_filter(
                is_array($entry['checklist'] ?? null) ? $entry['checklist'] : [],
                static fn ($line): bool => is_string($line) && trim($line) !== ''
            ));
            return $entry;
        }, $runbooks));
    }

    /**
     * @return array<string, mixed>
     */
    public function readiness(): array
    {
        $registry = $this->all();
        $requiredCount = max(1, (int) config('najm-hoda.runtime.autonomy.runbooks.min_required_checklist_items', 4));

        $ready = 0;
        $details = [];

        foreach ($registry as $runbook) {
            $checklist = is_array($runbook['checklist'] ?? null) ? $runbook['checklist'] : [];
            $status = (string) ($runbook['status'] ?? 'draft');
            $isReady = $status === 'active' && count($checklist) >= $requiredCount;
            if ($isReady) {
                $ready++;
            }

            $details[] = [
                'id' => (string) ($runbook['id'] ?? 'unknown'),
                'status' => $status,
                'checklist_count' => count($checklist),
                'is_ready' => $isReady,
            ];
        }

        $total = count($registry);
        $ratio = $total > 0 ? round($ready / $total, 4) : 0.0;
        $readiness = [
            'generated_at' => now()->toIso8601String(),
            'total_runbooks' => $total,
            'ready_runbooks' => $ready,
            'readiness_ratio' => $ratio,
            'status' => $ratio >= 1.0 ? 'ready' : ($ratio >= 0.75 ? 'warning' : 'breach'),
            'details' => $details,
        ];

        $this->eventBus->emit('najm_hoda.autonomy.runbook.readiness.reported', [
            'total_runbooks' => $total,
            'ready_runbooks' => $ready,
            'readiness_ratio' => $ratio,
            'status' => (string) ($readiness['status'] ?? 'breach'),
        ]);

        return $readiness;
    }
}
