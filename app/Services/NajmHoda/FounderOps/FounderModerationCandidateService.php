<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\Report;
use App\Models\ReportedMessage;

class FounderModerationCandidateService
{
    /** @return array<int,array<string,mixed>> */
    public function candidates(int $limit = 10): array
    {
        $limit = max(1, min($limit, 50));
        $new = Report::query()
            ->whereIn('status', ['pending','reviewed'])
            ->latest('id')->limit($limit * 2)->get()
            ->map(fn (Report $r) => [
                'source_type'=>'report','source_id'=>(int)$r->id,'type'=>(string)$r->type,
                'status'=>(string)$r->status,'priority'=>(string)($r->priority ?: 'medium'),
                'group_id'=>$r->group_id ? (int)$r->group_id : null,
            ]);

        $legacy = ReportedMessage::query()
            ->whereNotIn('status', ['resolved_by_group_manager','resolved_by_admin'])
            ->orderByDesc('escalated_to_admin')->latest('id')->limit($limit * 2)->get()
            ->map(fn (ReportedMessage $r) => [
                'source_type'=>'reported_message','source_id'=>(int)$r->id,'type'=>'message',
                'status'=>(string)$r->status,'priority'=>$r->isEscalatedToAdmin() ? 'high' : 'medium',
                'group_id'=>$r->group_id ? (int)$r->group_id : null,
            ]);

        return $new->concat($legacy)->sortBy(fn(array $x) => match($x['priority']) {
            'critical'=>0,'high'=>1,'medium'=>2,default=>3
        })->take($limit)->values()->all();
    }
}
