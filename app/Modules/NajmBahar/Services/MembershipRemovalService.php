<?php

namespace App\Modules\NajmBahar\Services;

use App\Models\User;
use App\Modules\NajmBahar\Models\MembershipRetirement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MembershipRemovalService
{
    public function __construct(
        private readonly AccountService $accounts,
        private readonly MembershipRetirementService $retirements,
    ) {
    }

    /**
     * Remove an active membership without deleting the person's identity,
     * estate wealth, Najm Bahar account, ledger history, or audit evidence.
     *
     * Administrative removal is a membership lifecycle event, not data purge.
     */
    public function remove(int $userId, array $metadata = []): ?MembershipRetirement
    {
        return DB::transaction(function () use ($userId, $metadata) {
            $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();

            $retirement = null;
            if ($this->accounts->getMainAccountForUser($userId)) {
                $retirement = $this->retirements->retire($userId, 'removal', array_merge($metadata, [
                    'membership_removal_boundary' => true,
                    'identity_preserved' => true,
                    'estate_assets_preserved' => true,
                ]));
            }

            if ($user->status !== 'inactive') {
                $user->status = 'inactive';
                $user->save();
            }

            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $userId)->delete();
            }

            return $retirement;
        }, 3);
    }
}
