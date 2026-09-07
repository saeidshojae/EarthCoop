<?php

namespace App\Modules\Secretariat\Services;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatLegalHold;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Models\SecretariatRetentionAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SecretariatRetentionService
{
    private const DISPOSITIONS = ['preserve', 'review', 'eligible_for_disposition'];
    private const FORMAL_STATUSES = ['registered', 'active', 'closed', 'archived', 'superseded', 'voided'];

    public function __construct(private readonly SecretariatAuditService $audit) {}

    /** @param array<string,mixed> $attributes */
    public function assign(SecretariatRecord $record, User $actor, array $attributes): SecretariatRetentionAssignment
    {
        $disposition = (string) ($attributes['disposition'] ?? 'preserve');
        if (! in_array($disposition, self::DISPOSITIONS, true)) {
            throw ValidationException::withMessages(['disposition' => 'Unsupported Secretariat retention disposition.']);
        }
        $record->loadMissing('office');
        Gate::forUser($actor)->authorize('transition', $record);

        return DB::transaction(function () use ($record, $actor, $attributes, $disposition) {
            $locked = SecretariatRecord::query()->with('office')->whereKey($record->id)->lockForUpdate()->firstOrFail();
            $this->assertFormal($locked);
            Gate::forUser($actor)->authorize('transition', $locked);

            $sequence = (int) SecretariatRetentionAssignment::query()
                ->where('record_id', $locked->id)
                ->lockForUpdate()
                ->max('assignment_sequence') + 1;

            $assignment = SecretariatRetentionAssignment::query()->create([
                'record_id' => $locked->id,
                'assignment_sequence' => $sequence,
                'disposition' => $disposition,
                'retention_until' => $attributes['retention_until'] ?? null,
                'policy_reference' => $this->nullableString($attributes['policy_reference'] ?? null, 255),
                'reason' => $this->nullableString($attributes['reason'] ?? null, 5000),
                'metadata' => is_array($attributes['metadata'] ?? null) ? $attributes['metadata'] : null,
                'assigned_by' => $actor->id,
                'assigned_at' => now(),
            ]);

            $this->audit->append($locked->office, $locked, $actor, 'retention_assigned', [
                'retention_assignment_id' => $assignment->id,
                'assignment_sequence' => $sequence,
                'disposition' => $disposition,
                'retention_until' => optional($assignment->retention_until)->toIso8601String(),
                'policy_reference' => $assignment->policy_reference,
            ]);

            return $assignment;
        });
    }

    /** @param array<string,mixed> $attributes */
    public function placeHold(SecretariatRecord $record, User $actor, array $attributes): SecretariatLegalHold
    {
        $reason = trim((string) ($attributes['reason'] ?? ''));
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A legal hold requires a reason.']);
        }
        $record->loadMissing('office');
        Gate::forUser($actor)->authorize('transition', $record);

        return DB::transaction(function () use ($record, $actor, $attributes, $reason) {
            $locked = SecretariatRecord::query()->with('office')->whereKey($record->id)->lockForUpdate()->firstOrFail();
            $this->assertFormal($locked);
            Gate::forUser($actor)->authorize('transition', $locked);

            $hold = SecretariatLegalHold::query()->create([
                'record_id' => $locked->id,
                'hold_reference' => $this->nullableString($attributes['hold_reference'] ?? null, 255),
                'reason' => $reason,
                'metadata' => is_array($attributes['metadata'] ?? null) ? $attributes['metadata'] : null,
                'placed_by' => $actor->id,
                'placed_at' => now(),
            ]);

            $this->audit->append($locked->office, $locked, $actor, 'legal_hold_placed', [
                'legal_hold_id' => $hold->id,
                'hold_reference' => $hold->hold_reference,
            ]);

            return $hold;
        });
    }

    public function releaseHold(SecretariatLegalHold $hold, User $actor, string $reason): SecretariatLegalHold
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['release_reason' => 'Releasing a legal hold requires a reason.']);
        }
        $hold->loadMissing('record.office');
        Gate::forUser($actor)->authorize('transition', $hold->record);

        return DB::transaction(function () use ($hold, $actor, $reason) {
            $locked = SecretariatLegalHold::query()->with('record.office')->whereKey($hold->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('transition', $locked->record);
            if ($locked->released_at !== null) {
                return $locked;
            }

            $locked->performRelease(function (SecretariatLegalHold $target) use ($actor, $reason): void {
                $target->released_by = $actor->id;
                $target->released_at = now();
                $target->release_reason = $reason;
                $target->save();
            });

            $this->audit->append($locked->record->office, $locked->record, $actor, 'legal_hold_released', [
                'legal_hold_id' => $locked->id,
                'release_reason' => $reason,
            ]);

            return $locked->refresh();
        });
    }

    /**
     * Assessment only. It does not authorize or execute purge.
     * @return array{assignment:?SecretariatRetentionAssignment,active_hold_count:int,retention_elapsed:bool,eligible_for_disposition:bool,purge_authorized:bool}
     */
    public function assess(SecretariatRecord $record): array
    {
        $record = $record->fresh();
        $assignment = SecretariatRetentionAssignment::query()
            ->where('record_id', $record->id)
            ->orderByDesc('assignment_sequence')
            ->first();
        $activeHoldCount = SecretariatLegalHold::query()
            ->where('record_id', $record->id)
            ->whereNull('released_at')
            ->count();
        $elapsed = $assignment?->retention_until !== null && $assignment->retention_until->lte(now());
        $eligible = $assignment !== null
            && $assignment->disposition === 'eligible_for_disposition'
            && $elapsed
            && $activeHoldCount === 0;

        return [
            'assignment' => $assignment,
            'active_hold_count' => $activeHoldCount,
            'retention_elapsed' => $elapsed,
            'eligible_for_disposition' => $eligible,
            'purge_authorized' => false,
        ];
    }

    private function assertFormal(SecretariatRecord $record): void
    {
        if (! in_array((string) $record->status, self::FORMAL_STATUSES, true)) {
            throw ValidationException::withMessages(['record' => 'Retention and legal hold controls require a formal Secretariat record.']);
        }
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        if ($value === null) return null;
        $value = trim((string) $value);
        if ($value === '') return null;
        return mb_substr($value, 0, $max);
    }
}
