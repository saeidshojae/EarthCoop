<?php

namespace App\Observers;

use App\Models\Group;
use App\Modules\NajmBahar\Services\AccountService;
use App\Services\NajmHoda\NajmHodaGroupAssistantService;
use Illuminate\Support\Facades\Log;

class GroupObserver
{
    public function created(Group $group): void
    {
        try {
            app(AccountService::class)->ensureLegalEntityAccountForGroup($group);
        } catch (\Throwable $e) {
            Log::warning('Group legal account creation failed: ' . $e->getMessage(), [
                'group_id' => $group->id,
            ]);
        }

        try {
            app(NajmHodaGroupAssistantService::class)->ensureGroupAssistantSetup($group);
        } catch (\Throwable $e) {
            Log::warning('NajmHoda group assistant setup failed: ' . $e->getMessage(), [
                'group_id' => $group->id,
            ]);
        }
    }
}
