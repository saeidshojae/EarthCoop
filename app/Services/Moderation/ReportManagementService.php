<?php

namespace App\Services\Moderation;

use App\Models\Report;
use App\Models\ReportedMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReportManagementService
{
    public function resolve(string $sourceType, int $sourceId, int $reviewerId, ?string $note = null): array
    {
        return DB::transaction(function () use ($sourceType, $sourceId, $reviewerId, $note): array {
            $report = $this->find($sourceType, $sourceId);

            if ($sourceType === 'report') {
                $report->status = 'resolved';
                $report->reviewed_by = $reviewerId;
                $report->reviewed_at = now();
                if ($note !== null) $report->admin_note = $note;
            } else {
                $report->status = 'resolved_by_admin';
                if ($note !== null) $report->admin_note = $note;
            }
            $report->save();

            return ['source_type'=>$sourceType,'source_id'=>$sourceId,'status'=>(string)$report->status];
        });
    }

    public function setPriority(string $sourceType, int $sourceId, string $priority): array
    {
        if (! in_array($priority, ['low','medium','high','critical'], true)) {
            throw new InvalidArgumentException('invalid_priority');
        }
        $report = $this->find($sourceType, $sourceId);
        if ($sourceType !== 'report') {
            return ['source_type'=>$sourceType,'source_id'=>$sourceId,'priority'=>'medium','changed'=>false];
        }
        $report->priority = $priority;
        $report->save();
        return ['source_type'=>$sourceType,'source_id'=>$sourceId,'priority'=>$priority,'changed'=>true];
    }

    public function find(string $sourceType, int $sourceId): Model
    {
        return match ($sourceType) {
            'report' => Report::query()->findOrFail($sourceId),
            'reported_message' => ReportedMessage::query()->findOrFail($sourceId),
            default => throw new InvalidArgumentException('unsupported_report_source'),
        };
    }
}
