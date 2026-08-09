<?php

namespace App\Modules\Governance\Services;

use App\Models\Group;
use App\Models\Poll;
use App\Models\User;
use App\Modules\Governance\Models\EligibilitySnapshot;
use App\Modules\Governance\Models\EligibilitySnapshotChunk;
use Illuminate\Support\Facades\DB;

class EligibilitySnapshotService
{
    public function capture(Group $group, ?Poll $poll, ?User $actor = null, int $chunkSize = 1000): EligibilitySnapshot
    {
        if ($chunkSize < 100 || $chunkSize > 5000) {
            throw new \InvalidArgumentException('Eligibility snapshot chunk size must be between 100 and 5000.');
        }

        return DB::transaction(function () use ($group, $poll, $actor, $chunkSize) {
            $criteria = [
                'group_id' => (int) $group->id,
                'membership_status' => 1,
                'deleted_at' => null,
                'captured_from' => 'group_user',
            ];

            $snapshot = EligibilitySnapshot::create([
                'group_id' => $group->id,
                'poll_id' => $poll?->id,
                'captured_by' => $actor?->id,
                'purpose' => 'resolution_vote',
                'status' => 'capturing',
                'chunk_size' => $chunkSize,
                'criteria' => $criteria,
            ]);

            $hash = hash_init('sha256');
            $eligibleCount = 0;
            $chunkIndex = 0;

            DB::table('group_user')
                ->select(['id', 'user_id'])
                ->where('group_id', $group->id)
                ->where('status', 1)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->chunkById($chunkSize, function ($rows) use ($snapshot, &$hash, &$eligibleCount, &$chunkIndex) {
                    $memberIds = [];
                    foreach ($rows as $row) {
                        $userId = (int) $row->user_id;
                        $memberIds[] = $userId;
                        hash_update($hash, $userId . "\n");
                    }

                    EligibilitySnapshotChunk::create([
                        'snapshot_id' => $snapshot->id,
                        'chunk_index' => $chunkIndex++,
                        'member_count' => count($memberIds),
                        'member_ids' => $memberIds,
                    ]);
                    $eligibleCount += count($memberIds);
                }, 'id');

            $snapshot->update([
                'status' => 'finalized',
                'eligible_count' => $eligibleCount,
                'chunk_count' => $chunkIndex,
                'membership_fingerprint' => hash_final($hash),
                'captured_at' => now(),
            ]);

            return $snapshot->fresh();
        }, 3);
    }

    public function contains(EligibilitySnapshot $snapshot, int $userId): bool
    {
        if ($snapshot->status !== 'finalized') {
            return false;
        }

        return $snapshot->chunks()
            ->get(['member_ids'])
            ->contains(fn (EligibilitySnapshotChunk $chunk) => in_array($userId, array_map('intval', $chunk->member_ids ?? []), true));
    }
}
