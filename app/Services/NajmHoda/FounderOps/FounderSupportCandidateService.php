<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\Ticket;

class FounderSupportCandidateService
{
    /** @return array<int,array<string,mixed>> */
    public function candidates(int $limit = 5): array
    {
        $limit = max(1, min($limit, 20));

        return Ticket::query()
            ->whereIn('status', ['open', 'in-progress'])
            ->orderByRaw("CASE WHEN priority = 'high' THEN 0 WHEN assignee_id IS NULL THEN 1 ELSE 2 END")
            ->orderBy('created_at')
            ->limit($limit)
            ->get(['id','status','priority','category','assignee_id','created_at'])
            ->map(static fn (Ticket $ticket): array => [
                'ticket_id' => (int) $ticket->id,
                'status' => (string) $ticket->status,
                'priority' => $ticket->priority,
                'category' => $ticket->category,
                'unassigned' => $ticket->assignee_id === null,
                'created_at' => optional($ticket->created_at)->toIso8601String(),
            ])->all();
    }
}
