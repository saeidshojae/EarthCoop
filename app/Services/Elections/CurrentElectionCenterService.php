<?php

namespace App\Services\Elections;

use App\Models\User;
use Illuminate\Support\Collection;

class CurrentElectionCenterService
{
    public function __construct(
        private readonly SystemicElectionCurrentItemAdapter $systemic,
        private readonly InternalElectionCurrentItemAdapter $internal,
    ) {
    }

    public function forUser(User $user): array
    {
        $systemic = $this->sort($this->systemic->itemsFor($user));
        $internal = $this->sort($this->internal->itemsFor($user));
        $all = $systemic->concat($internal);

        return [
            'systemic' => $systemic,
            'internal' => $internal,
            'summary' => [
                'action_required' => $all->where('priority_bucket', 'action_required')->count(),
                'related' => $all->where('priority_bucket', 'related')->count(),
                'total' => $all->count(),
            ],
        ];
    }

    private function sort(Collection $items): Collection
    {
        return $items->sort(function (array $a, array $b): int {
            $bucketA = ($a['priority_bucket'] ?? 'related') === 'action_required' ? 0 : 1;
            $bucketB = ($b['priority_bucket'] ?? 'related') === 'action_required' ? 0 : 1;
            if ($bucketA !== $bucketB) {
                return $bucketA <=> $bucketB;
            }

            $deadlineA = $a['ends_at']?->getTimestamp() ?? PHP_INT_MAX;
            $deadlineB = $b['ends_at']?->getTimestamp() ?? PHP_INT_MAX;
            if ($deadlineA !== $deadlineB) {
                return $deadlineA <=> $deadlineB;
            }

            return ((int) ($b['source_id'] ?? 0)) <=> ((int) ($a['source_id'] ?? 0));
        })->values();
    }
}
