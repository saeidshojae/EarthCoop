<?php

namespace App\Modules\Secretariat\Services;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatDispatch;
use App\Modules\Secretariat\Models\SecretariatOffice;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Models\SecretariatRelation;
use Illuminate\Support\Facades\Gate;

/**
 * Read-side work queue for managers/inspectors and Najm Hoda.
 *
 * There is intentionally no heuristic deadline. A dispatch is overdue only when
 * an explicit due_at exists. "Unanswered" also requires expects_response=true.
 */
class SecretariatWorkQueueService
{
    private const TERMINAL_DISPATCH_STATUSES = ['completed', 'failed', 'cancelled'];

    public function __construct(private readonly SecretariatAclService $acl) {}

    /** @return array<string,array<int,array<string,mixed>>> */
    public function forOffice(SecretariatOffice $office, User $actor, int $limit = 50): array
    {
        Gate::forUser($actor)->authorize('view', $office);
        $limit = max(1, min(100, $limit));
        $audited = [];

        $pendingApproval = SecretariatRecord::query()
            ->with('office')
            ->where('office_id', $office->id)
            ->where('status', 'pending_approval')
            ->orderBy('updated_at')
            ->limit($limit * 2)
            ->get()
            ->filter(fn (SecretariatRecord $record) => Gate::forUser($actor)->allows('register', $record))
            ->take($limit)
            ->map(fn (SecretariatRecord $record) => $this->recordPacket($record, $actor, $audited, 'pending_approval'))
            ->values()
            ->all();

        $dispatches = SecretariatDispatch::query()
            ->with(['record.office', 'targetUser', 'targetParty'])
            ->whereHas('record', fn ($query) => $query->where('office_id', $office->id))
            ->whereNotIn('status', self::TERMINAL_DISPATCH_STATUSES)
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->orderBy('id')
            ->limit($limit * 5)
            ->get()
            ->filter(fn (SecretariatDispatch $dispatch) => $dispatch->record instanceof SecretariatRecord
                && Gate::forUser($actor)->allows('view', $dispatch->record));

        $overdue = [];
        $followUpDue = [];
        $unanswered = [];
        $now = now();

        foreach ($dispatches as $dispatch) {
            /** @var SecretariatDispatch $dispatch */
            $record = $dispatch->record;
            if ($dispatch->due_at !== null && $dispatch->due_at->lte($now) && count($overdue) < $limit) {
                $overdue[] = $this->dispatchPacket($dispatch, $actor, $audited, 'overdue');
            }
            if ($dispatch->follow_up_at !== null && $dispatch->follow_up_at->lte($now) && count($followUpDue) < $limit) {
                $followUpDue[] = $this->dispatchPacket($dispatch, $actor, $audited, 'follow_up_due');
            }
            if ((bool) $dispatch->expects_response
                && count($unanswered) < $limit
                && ! $this->hasVisibleResponse($record, $actor, $audited)) {
                $unanswered[] = $this->dispatchPacket($dispatch, $actor, $audited, 'awaiting_response');
            }
        }

        return [
            'pending_approval' => $pendingApproval,
            'overdue_dispatches' => $overdue,
            'follow_up_due' => $followUpDue,
            'unanswered_correspondence' => $unanswered,
        ];
    }

    /** @param array<int,bool> $audited */
    private function recordPacket(SecretariatRecord $record, User $actor, array &$audited, string $kind): array
    {
        $this->auditIfSensitive($record, $actor, $audited);
        return [
            'kind' => $kind,
            'record_id' => (int) $record->id,
            'registry_number' => $record->registry_number,
            'record_type' => $record->record_type,
            'title' => $record->title,
            'status' => $record->status,
            'confidentiality' => $record->confidentiality,
            'updated_at' => $record->updated_at?->toIso8601String(),
        ];
    }

    /** @param array<int,bool> $audited */
    private function dispatchPacket(SecretariatDispatch $dispatch, User $actor, array &$audited, string $kind): array
    {
        $record = $dispatch->record;
        $this->auditIfSensitive($record, $actor, $audited);
        return [
            'kind' => $kind,
            'dispatch_id' => (int) $dispatch->id,
            'dispatch_status' => $dispatch->status,
            'record_id' => (int) $record->id,
            'registry_number' => $record->registry_number,
            'record_type' => $record->record_type,
            'title' => $record->title,
            'due_at' => $dispatch->due_at?->toIso8601String(),
            'follow_up_at' => $dispatch->follow_up_at?->toIso8601String(),
            'expects_response' => (bool) $dispatch->expects_response,
            'target_user_id' => $dispatch->target_user_id,
            'target_party_id' => $dispatch->target_party_id,
        ];
    }

    /** @param array<int,bool> $audited */
    private function hasVisibleResponse(SecretariatRecord $record, User $actor, array &$audited): bool
    {
        $sourceIds = SecretariatRelation::query()
            ->where('target_record_id', $record->id)
            ->where('relation_type', 'responds_to')
            ->pluck('source_record_id');

        if ($sourceIds->isEmpty()) {
            return false;
        }

        foreach (SecretariatRecord::query()->with('office')->whereIn('id', $sourceIds)->get() as $response) {
            if (Gate::forUser($actor)->allows('view', $response)) {
                $this->auditIfSensitive($response, $actor, $audited);
                return true;
            }
        }
        return false;
    }

    /** @param array<int,bool> $audited */
    private function auditIfSensitive(SecretariatRecord $record, User $actor, array &$audited): void
    {
        if ($record->confidentiality !== 'confidential' || isset($audited[$record->id])) {
            return;
        }
        $this->acl->auditSensitiveAccess($record, $actor, ['channel' => 'secretariat_work_queue']);
        $audited[$record->id] = true;
    }
}
