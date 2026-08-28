<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;

class FounderApprovalInboxService
{
    public function __construct(protected NajmHodaAutonomyApprovalService $approvals) {}

    /** @return array<string,mixed> */
    public function snapshot(int $limit = 100): array
    {
        $pending = collect($this->approvals->pending($limit))
            ->filter(static fn (array $item): bool => str_starts_with((string) ($item['action'] ?? ''), 'founder_ops:'))
            ->values();

        return [
            'pending' => $pending->count(),
            'overdue' => $pending->where('sla_status', 'overdue')->count(),
            'within_sla' => $pending->where('sla_status', 'within_sla')->count(),
            'by_risk' => $pending->countBy(static fn (array $item): string => (string) ($item['risk'] ?? 'unknown'))->all(),
            'items' => $pending->map(static function (array $item): array {
                return [
                    'id' => $item['id'] ?? null,
                    'action' => $item['action'] ?? null,
                    'risk' => $item['risk'] ?? null,
                    'requested_at' => $item['requested_at'] ?? null,
                    'deadline_at' => $item['deadline_at'] ?? null,
                    'sla_status' => $item['sla_status'] ?? null,
                    'domain' => data_get($item, 'plan_item.domain'),
                    'domain_action' => data_get($item, 'plan_item.domain_action'),
                    'context' => $item['context'] ?? [],
                ];
            })->all(),
        ];
    }
}
