<?php

namespace App\Modules\Secretariat\Services;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Models\SecretariatRelation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SecretariatRelationService
{
    private const TYPES = [
        'derived_from',
        'refers_to',
        'supersedes',
        'amends',
        'implements',
        'responds_to',
        'decision_of',
        'action_of',
        'report_of',
        'part_of_case',
        'related_to',
    ];

    public function __construct(private readonly SecretariatAuditService $audit)
    {
    }

    public function add(
        SecretariatRecord $source,
        SecretariatRecord $target,
        string $type,
        User $actor,
        array $metadata = [],
    ): SecretariatRelation {
        if (! in_array($type, self::TYPES, true)) {
            throw ValidationException::withMessages(['relation_type' => 'Unsupported Secretariat relation type.']);
        }

        if ($source->is($target)) {
            throw ValidationException::withMessages(['target_record_id' => 'A Secretariat record cannot relate to itself.']);
        }

        // Cross-office reference/transfer policy is deliberately deferred to S5.
        if ((int) $source->office_id !== (int) $target->office_id) {
            throw ValidationException::withMessages(['target_record_id' => 'Cross-office Secretariat relations are not enabled in S2.']);
        }

        return DB::transaction(function () use ($source, $target, $type, $actor, $metadata) {
            // Lock both records in deterministic primary-key order. Logical
            // relation direction remains source→target, but A→B and B→A cannot
            // deadlock by acquiring the same two record locks in reverse order.
            $ids = [(int) $source->id, (int) $target->id];
            sort($ids, SORT_NUMERIC);

            $lockedRecords = SecretariatRecord::query()
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var SecretariatRecord $lockedSource */
            $lockedSource = $lockedRecords->get($source->id);
            /** @var SecretariatRecord $lockedTarget */
            $lockedTarget = $lockedRecords->get($target->id);

            if ($lockedSource === null || $lockedTarget === null) {
                throw ValidationException::withMessages(['target_record_id' => 'Secretariat relation records no longer exist.']);
            }

            $existing = SecretariatRelation::query()
                ->where('source_record_id', $lockedSource->id)
                ->where('target_record_id', $lockedTarget->id)
                ->where('relation_type', $type)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $relation = SecretariatRelation::query()->create([
                'source_record_id' => $lockedSource->id,
                'target_record_id' => $lockedTarget->id,
                'relation_type' => $type,
                'created_by' => $actor->id,
                'metadata' => $metadata ?: null,
            ]);

            $this->audit->append($lockedSource->office, $lockedSource, $actor, 'relation_added', [
                'relation_id' => $relation->id,
                'relation_type' => $type,
                'target_record_id' => $lockedTarget->id,
            ]);

            return $relation;
        });
    }
}
