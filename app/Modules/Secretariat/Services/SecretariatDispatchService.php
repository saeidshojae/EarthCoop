<?php

namespace App\Modules\Secretariat\Services;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatDispatch;
use App\Modules\Secretariat\Models\SecretariatParty;
use App\Modules\Secretariat\Models\SecretariatRecord;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class SecretariatDispatchService
{
    private const TYPES = ['referral', 'notification', 'delivery', 'return'];
    private const CHANNELS = ['internal', 'email', 'physical', 'api', 'other'];
    private const DISPATCHABLE_RECORD_STATUSES = ['registered', 'active', 'closed'];
    private const TERMINAL_STATUSES = ['completed', 'failed', 'cancelled'];
    private const TRANSITIONS = [
        'pending' => ['sent', 'cancelled'],
        'sent' => ['received', 'failed', 'cancelled'],
        'received' => ['acknowledged', 'completed'],
        'acknowledged' => ['completed'],
        'completed' => [],
        'failed' => [],
        'cancelled' => [],
    ];

    public function __construct(private readonly SecretariatAuditService $audit) {}

    /** @param array<string,mixed> $attributes */
    public function create(SecretariatRecord $record, User $actor, array $attributes): SecretariatDispatch
    {
        $dueAt = $this->parseTimestamp($attributes['due_at'] ?? null, 'due_at');
        $followUpAt = $this->parseTimestamp($attributes['follow_up_at'] ?? null, 'follow_up_at');
        $expectsResponse = filter_var($attributes['expects_response'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return DB::transaction(function () use ($record, $actor, $attributes, $dueAt, $followUpAt, $expectsResponse) {
            $lockedRecord = SecretariatRecord::query()->with('office')->whereKey($record->id)->lockForUpdate()->firstOrFail();
            if ($lockedRecord->registry_number === null || ! in_array($lockedRecord->status, self::DISPATCHABLE_RECORD_STATUSES, true)) {
                throw ValidationException::withMessages(['record' => 'Only a registered, active, or closed Secretariat record can enter a new dispatch trail.']);
            }

            $type = (string) ($attributes['dispatch_type'] ?? '');
            $channel = (string) ($attributes['channel'] ?? 'internal');
            if (! in_array($type, self::TYPES, true)) throw ValidationException::withMessages(['dispatch_type' => 'Unsupported Secretariat dispatch type.']);
            if (! in_array($channel, self::CHANNELS, true)) throw ValidationException::withMessages(['channel' => 'Unsupported Secretariat dispatch channel.']);

            $partyId = isset($attributes['target_party_id']) ? (int) $attributes['target_party_id'] : null;
            $userId = isset($attributes['target_user_id']) ? (int) $attributes['target_user_id'] : null;
            if (($partyId === null) === ($userId === null)) throw ValidationException::withMessages(['target' => 'Dispatch requires exactly one target: a record party or a user.']);

            if ($partyId !== null) {
                $party = SecretariatParty::query()->whereKey($partyId)->where('record_id', $lockedRecord->id)->first();
                if ($party === null) throw ValidationException::withMessages(['target_party_id' => 'Dispatch target party must belong to the same Secretariat record.']);
            }
            if ($userId !== null && ! User::query()->whereKey($userId)->exists()) throw ValidationException::withMessages(['target_user_id' => 'Dispatch target user does not exist.']);
            if ($channel === 'internal' && $userId === null) throw ValidationException::withMessages(['channel' => 'Internal dispatch requires an EarthCoop user target.']);
            if ($channel !== 'internal' && $partyId === null) throw ValidationException::withMessages(['channel' => 'External transport channels require a snapshotted record party target.']);

            $dispatch = SecretariatDispatch::query()->create([
                'record_id' => $lockedRecord->id,
                'dispatch_type' => $type,
                'status' => 'pending',
                'channel' => $channel,
                'target_party_id' => $partyId,
                'target_user_id' => $userId,
                'instructions' => $attributes['instructions'] ?? null,
                'external_reference_number' => $attributes['external_reference_number'] ?? null,
                'expects_response' => $expectsResponse,
                'due_at' => $dueAt,
                'follow_up_at' => $followUpAt,
                'metadata' => $attributes['metadata'] ?? null,
                'created_by' => $actor->id,
            ]);

            $this->audit->append($lockedRecord->office, $lockedRecord, $actor, 'dispatch_created', [
                'dispatch_id' => $dispatch->id,
                'dispatch_type' => $type,
                'channel' => $channel,
                'target_party_id' => $partyId,
                'target_user_id' => $userId,
                'expects_response' => $expectsResponse,
                'due_at' => $dueAt?->toIso8601String(),
                'follow_up_at' => $followUpAt?->toIso8601String(),
            ]);
            return $dispatch;
        });
    }

    public function schedule(SecretariatDispatch $dispatch, User $actor, mixed $dueAt = null, mixed $followUpAt = null): SecretariatDispatch
    {
        $parsedDueAt = $this->parseTimestamp($dueAt, 'due_at');
        $parsedFollowUpAt = $this->parseTimestamp($followUpAt, 'follow_up_at');

        return DB::transaction(function () use ($dispatch, $actor, $parsedDueAt, $parsedFollowUpAt) {
            $locked = SecretariatDispatch::query()->with('record.office')->whereKey($dispatch->id)->lockForUpdate()->firstOrFail();
            if (in_array((string) $locked->status, self::TERMINAL_STATUSES, true)) throw ValidationException::withMessages(['status' => 'A terminal Secretariat dispatch cannot be rescheduled.']);

            $before = ['due_at' => $locked->due_at?->toIso8601String(), 'follow_up_at' => $locked->follow_up_at?->toIso8601String()];
            $locked->performControlledMutation(function (SecretariatDispatch $target) use ($parsedDueAt, $parsedFollowUpAt): void {
                $target->forceFill(['due_at' => $parsedDueAt, 'follow_up_at' => $parsedFollowUpAt])->save();
            });
            $this->audit->append($locked->record->office, $locked->record, $actor, 'dispatch_schedule_changed', [
                'dispatch_id' => $locked->id,
                'before' => $before,
                'after' => ['due_at' => $parsedDueAt?->toIso8601String(), 'follow_up_at' => $parsedFollowUpAt?->toIso8601String()],
            ]);
            return $locked->refresh();
        });
    }

    /** @param array<string,mixed> $metadata */
    public function transition(SecretariatDispatch $dispatch, string $to, User $actor, array $metadata = []): SecretariatDispatch
    {
        return DB::transaction(function () use ($dispatch, $to, $actor, $metadata) {
            $locked = SecretariatDispatch::query()->with('record.office')->whereKey($dispatch->id)->lockForUpdate()->firstOrFail();
            $from = (string) $locked->status;
            if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) throw ValidationException::withMessages(['status' => "Unsupported Secretariat dispatch transition {$from} → {$to}."]);

            $now = now();
            $timestamps = match ($to) {
                'sent' => ['dispatched_at' => $now],
                'received' => ['received_at' => $now],
                'acknowledged' => ['acknowledged_at' => $now],
                'completed' => ['completed_at' => $now],
                default => [],
            };
            $locked->performControlledMutation(function (SecretariatDispatch $target) use ($to, $timestamps, $metadata): void {
                $currentMetadata = is_array($target->metadata) ? $target->metadata : [];
                $target->forceFill(array_merge(['status' => $to, 'metadata' => array_replace_recursive($currentMetadata, $metadata)], $timestamps))->save();
            });
            $this->audit->append($locked->record->office, $locked->record, $actor, 'dispatch_status_changed', ['dispatch_id' => $locked->id, 'from' => $from, 'to' => $to]);
            return $locked->refresh();
        });
    }

    private function parseTimestamp(mixed $value, string $field): ?CarbonImmutable
    {
        if ($value === null || $value === '') return null;
        try { return CarbonImmutable::parse($value); }
        catch (Throwable) { throw ValidationException::withMessages([$field => "Invalid {$field} timestamp."]); }
    }
}
