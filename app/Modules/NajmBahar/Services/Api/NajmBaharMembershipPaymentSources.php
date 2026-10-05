<?php

namespace App\Modules\NajmBahar\Services\Api;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\ActiveBaharReservation;
use App\Modules\NajmBahar\Services\ActiveBaharReservationService;

class NajmBaharMembershipPaymentSources
{
    public function forAccount(Account $main, int $fee): array
    {
        $active = app(ActiveBaharReservationService::class)->availableActive($main);
        $dim = max(0, (int) $main->balance_faded);
        $sources = [$this->row('main', null, (string) $main->account_number, (string) $main->name, $active, $dim, $fee)];
        foreach (SubAccount::query()->where('account_id', $main->id)->where('status', 1)->orderBy('id')->get() as $sub) {
            // Reading a source must never create or reconcile its Account mirror.
            $mirror = Account::query()->where('account_number', $sub->sub_account_code)->first();
            $reserved = $mirror ? (int) ActiveBaharReservation::query()->where('payer_account_id', $mirror->id)
                ->where('status', ActiveBaharReservation::RESERVED)->sum('amount') : 0;
            $available = max(0, (int) $sub->balance_active - $reserved);
            $sources[] = $this->row('subaccount', (int) $sub->id, (string) $sub->sub_account_code,
                (string) $sub->name, $available, 0, $fee);
        }
        return $sources;
    }

    private function row(string $kind, ?int $id, string $number, string $name, int $active, int $dim, int $fee): array
    {
        return ['kind' => $kind, 'sub_account_id' => $id, 'account_number' => $number, 'name' => $name,
            'active_available_gol' => $active, 'dim_available_gol' => $dim,
            'can_pay_active' => $active >= $fee, 'can_pay_dim' => $kind === 'main' && $dim >= $fee];
    }
}
