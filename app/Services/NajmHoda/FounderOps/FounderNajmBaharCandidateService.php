<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Modules\NajmBahar\Models\ScheduledTransaction;

class FounderNajmBaharCandidateService
{
    public function candidates(int $limit = 10): array
    {
        return ScheduledTransaction::query()
            ->whereNotIn('status',['completed','executed','cancelled'])
            ->where(function ($q) { $q->where('execute_at','<=',now()->addDay())->orWhere('attempts','>=',3); })
            ->orderByRaw('CASE WHEN execute_at < ? THEN 0 ELSE 1 END',[now()])
            ->orderByDesc('attempts')->orderBy('execute_at')
            ->limit(max(1,min($limit,50)))
            ->get()
            ->map(fn (ScheduledTransaction $scheduled) => [
                'scheduled_transaction_id'=>(int)$scheduled->id,'transaction_id'=>$scheduled->transaction_id ? (int)$scheduled->transaction_id : null,
                'status'=>(string)$scheduled->status,'attempts'=>(int)$scheduled->attempts,
                'execute_at'=>$scheduled->execute_at?->toIso8601String(),
                'urgency'=>($scheduled->execute_at && $scheduled->execute_at->isPast()) || (int)$scheduled->attempts>=3 ? 'high' : 'normal',
            ])->values()->all();
    }
}
