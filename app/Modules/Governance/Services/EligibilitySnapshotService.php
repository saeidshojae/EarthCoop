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
                'ordering' => 'user_id_asc',
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
                ->select(['user_id'])
                ->where('group_id', $group->id)
                ->where('status', 1)
                ->whereNull('deleted_at')
                ->chunkById($chunkSize, function ($rows) use ($snapshot, &$hash, &$eligibleCount, &$chunkIndex) {
                    $memberIds = $rows->pluck('user_id')->map(fn ($id) => (int) $id)->values()->all();
                    if ($memberIds === []) {
                        return;
                    }

                    foreach ($memberIds as $userId) {
                        hash_update($hash, $userId . "\n");
                    }

                    EligibilitySnapshotChunk::create([
                        'snapshot_id' => $snapshot->id,
                        'chunk_index' => $chunkIndex++,
                        'member_count' => count($memberIds),
                        'first_user_id' => $memberIds[0],
                        'last_user_id' => $memberIds[array_key_last($memberIds)],
                        'member_ids' => $memberIds,
                    ]);
                    $eligibleCount += count($memberIds);
                }, 'user_id', 'user_id');

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
        return $this->memberPosition($snapshot, $userId) !== null;
    }

    /**
     * Return the zero-based position of a member inside the immutable cohort.
     * The range columns narrow lookup to one candidate chunk; prefix count is
     * computed in SQL so callers never load the full assembly snapshot.
     */
    public function memberPosition(EligibilitySnapshot $snapshot, int $userId): ?int
    {
        if ($snapshot->status !== 'finalized') {
            return null;
        }

        $chunk = EligibilitySnapshotChunk::where('snapshot_id', $snapshot->id)
            ->where('first_user_id', '<=', $userId)
            ->where('last_user_id', '>=', $userId)
            ->orderBy('chunk_index')
            ->first();

        if (! $chunk) {
            return null;
        }

        $ids = array_map('intval', $chunk->member_ids ?? []);
        $localPosition = array_search($userId, $ids, true);
        if ($localPosition === false) {
            return null;
        }

        $prefixCount = (int) EligibilitySnapshotChunk::where('snapshot_id', $snapshot->id)
            ->where('chunk_index', '<', $chunk->chunk_index)
            ->sum('member_count');

        return $prefixCount + (int) $localPosition;
    }
}
