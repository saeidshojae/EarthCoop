<?php

namespace App\Modules\NajmBahar\Services\Api;

use App\Models\Setting;
use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\ActiveBaharReservationService;

class NajmBaharTransferCapabilityService
{
    public function __construct(
        private readonly AccountService $accounts,
        private readonly ActiveBaharReservationService $reservations,
    ) {
    }

    public function forUser(User $user): array
    {
        $main = $this->accounts->getMainAccountForUser((int) $user->id);

        if (! $main instanceof Account || (int) $main->user_id !== (int) $user->id) {
            return [
                'transfer_contract_version' => 1,
                'external_transfer_enabled' => false,
                'disabled_reason' => 'no_eligible_source',
                'sources' => [],
            ];
        }

        $threshold = (int) (Setting::query()->first()?->najm_bahar_user_threshold ?? 1111111);
        $thresholdMet = User::query()->count() >= $threshold;

        $sources = SubAccount::query()
            ->where('account_id', (int) $main->id)
            ->where('status', 1)
            ->orderBy('id')
            ->get()
            ->map(fn (SubAccount $sub) => $this->projectSource($sub))
            ->filter()
            ->values()
            ->all();

        $hasEligibleSource = collect($sources)->contains(
            fn (array $source) => (bool) ($source['can_transfer_active'] ?? false),
        );

        $disabledReason = null;
        if (! $thresholdMet) {
            $disabledReason = 'threshold_not_met';
        } elseif (! $hasEligibleSource) {
            $disabledReason = 'no_eligible_source';
        }

        return [
            'transfer_contract_version' => 1,
            'external_transfer_enabled' => $disabledReason === null,
            'disabled_reason' => $disabledReason,
            'sources' => $sources,
        ];
    }

    private function projectSource(SubAccount $sub): ?array
    {
        $active = max(0, (int) ($sub->balance_active ?? 0));

        $mirror = Account::query()
            ->where('type', 'subaccount')
            ->where('account_number', (string) $sub->sub_account_code)
            ->where('status', 1)
            ->first();

        if (! $mirror instanceof Account) {
            return null;
        }

        $available = min(
            $active,
            $this->reservations->availableActive($mirror),
        );

        return [
            'account_id' => (int) $mirror->id,
            'sub_account_id' => (int) $sub->id,
            'account_number' => (string) $sub->sub_account_code,
            'name' => (string) $sub->name,
            'kind' => 'subaccount',
            'status' => (int) $sub->status,
            'active_available_gol' => $available,
            'can_transfer_active' => $available > 0,
        ];
    }
}
