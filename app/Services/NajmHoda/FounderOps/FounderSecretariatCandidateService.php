<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Modules\Secretariat\Models\SecretariatDispatch;

class FounderSecretariatCandidateService
{
    /** @return array<int,array<string,mixed>> */
    public function candidates(int $limit = 10): array
    {
        $limit=max(1,min($limit,50));
        $now=now();
        return SecretariatDispatch::query()
            ->with('record:id,registry_number,status')
            ->whereNotIn('status',['completed','failed','cancelled'])
            ->where(function($q) use ($now): void {
                $q->where(function($qq) use ($now): void {
                    $qq->whereNotNull('due_at')->where('due_at','<=',$now->copy()->addDay());
                })->orWhere(function($qq) use ($now): void {
                    $qq->whereNotNull('follow_up_at')->where('follow_up_at','<=',$now->copy()->addDay());
                })->orWhere('expects_response',1);
            })
            ->orderByRaw('COALESCE(due_at, follow_up_at) asc')
            ->limit($limit)
            ->get()
            ->map(function(SecretariatDispatch $d) use ($now): array {
                $deadline=$d->due_at ?? $d->follow_up_at;
                $overdue=$deadline ? $deadline->lt($now) : false;
                return [
                    'dispatch_id'=>(int)$d->id,
                    'record_id'=>(int)$d->record_id,
                    'registry_number'=>$d->record?->registry_number,
                    'status'=>(string)$d->status,
                    'channel'=>(string)$d->channel,
                    'expects_response'=>(bool)$d->expects_response,
                    'due_at'=>$d->due_at?->toIso8601String(),
                    'follow_up_at'=>$d->follow_up_at?->toIso8601String(),
                    'urgency'=>$overdue?'high':'normal',
                ];
            })->all();
    }
}
