<?php

namespace App\Services\NajmHoda\Runtime;

use App\Modules\NajmBahar\Services\MonetaryOperationsReportService;

class NajmBaharHealthBridgeService
{
    public function __construct(
        private readonly MonetaryOperationsReportService $report
    ) {
    }

    public function snapshot(int $limit = 20): array
    {
        $health = $this->report->health();
        $items = $this->report->problemItems($limit);

        return [
            'source' => 'najm_bahar',
            'read_only' => true,
            'severity' => $health['severity'],
            'failed' => (int) $health['failed'],
            'dead_letter' => (int) $health['dead_letter'],
            'operator_attention' => [
                'required' => (bool) $health['requires_operator_attention'],
                'reason' => $health['requires_operator_attention']
                    ? 'Najm Bahar has dead-letter monetary operations that require explicit operator review.'
                    : null,
            ],
            'items' => $items->all(),
            'capabilities' => [
                'observe' => true,
                'report' => true,
                'retry' => false,
                'recover_dead_letter' => false,
                'move_money' => false,
            ],
        ];
    }
}
