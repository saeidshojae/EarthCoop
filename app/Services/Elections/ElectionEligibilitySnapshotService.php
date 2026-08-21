<?php

namespace App\Services\Elections;

use App\Models\Election;
use App\Models\ElectionEligibilitySnapshot;
use App\Models\GroupUser;
use Illuminate\Support\Facades\DB;

class ElectionEligibilitySnapshotService
{
    public const VERSION = 'e4-v1';

    public function capture(Election $election): int
    {
        return DB::transaction(function () use ($election): int {
            /** @var Election $locked */
            $locked = Election::query()->lockForUpdate()->findOrFail($election->getKey());

            if ($locked->eligibility_snapshot_captured_at !== null) {
                return ElectionEligibilitySnapshot::where('election_id', $locked->id)->count();
            }

            $capturedAt = now();
            $rows = [];

            GroupUser::query()
                ->leftJoin('users', 'users.id', '=', 'group_user.user_id')
                ->where('group_user.group_id', $locked->group_id)
                ->select([
                    'group_user.user_id',
                    'group_user.role',
                    'group_user.status',
                    'users.id as persisted_user_id',
                    'users.is_system',
                ])
                ->orderBy('group_user.user_id')
                ->chunk(500, function ($members) use (&$rows, $locked, $capturedAt): void {
                    foreach ($members as $member) {
                        [$voterEligible, $voterReason] = $this->evaluate($member);
                        [$selectableEligible, $selectableReason] = $this->evaluate($member);

                        $rows[] = [
                            'election_id' => $locked->id,
                            'user_id' => (int) $member->user_id,
                            'voter_eligible' => $voterEligible,
                            'selectable_eligible' => $selectableEligible,
                            'voter_exclusion_reason' => $voterReason,
                            'selectable_exclusion_reason' => $selectableReason,
                            'membership_role' => $member->role !== null ? (int) $member->role : null,
                            'membership_status' => $member->status !== null ? (int) $member->status : null,
                            'snapshot_version' => self::VERSION,
                            'captured_at' => $capturedAt,
                            'created_at' => $capturedAt,
                            'updated_at' => $capturedAt,
                        ];
                    }

                    if (count($rows) >= 500) {
                        ElectionEligibilitySnapshot::query()->insert($rows);
                        $rows = [];
                    }
                });

            if ($rows !== []) {
                ElectionEligibilitySnapshot::query()->insert($rows);
            }

            $locked->forceFill([
                'eligibility_snapshot_captured_at' => $capturedAt,
                'eligibility_snapshot_version' => self::VERSION,
            ])->save();

            return ElectionEligibilitySnapshot::where('election_id', $locked->id)->count();
        }, 3);
    }

    public function voterIds(Election $election): array
    {
        return ElectionEligibilitySnapshot::query()
            ->where('election_id', $election->id)
            ->where('voter_eligible', true)
            ->orderBy('user_id')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function selectableUserIds(Election $election): array
    {
        return ElectionEligibilitySnapshot::query()
            ->where('election_id', $election->id)
            ->where('selectable_eligible', true)
            ->orderBy('user_id')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function evaluate(object $member): array
    {
        if ($member->persisted_user_id === null) {
            return [false, 'missing_user'];
        }

        if ((bool) $member->is_system) {
            return [false, 'system_user'];
        }

        if ((int) $member->status !== 1) {
            return [false, 'inactive_membership'];
        }

        $role = (int) $member->role;
        if ($role < 1) {
            return [false, 'observer_role'];
        }

        if ($role === 4) {
            return [false, 'guest_role'];
        }

        return [true, null];
    }
}
