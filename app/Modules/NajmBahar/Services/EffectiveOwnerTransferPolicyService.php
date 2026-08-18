<?php

namespace App\Modules\NajmBahar\Services;

use App\Models\Setting;
use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;

/**
 * Read-only policy boundary for transfers between independent economic owners.
 *
 * Main accounts and their mirrored sub-account Accounts must resolve to the
 * same effective owner. Cross-owner movement is allowed only after the global
 * Najm Bahar transfer threshold is satisfied. System operations remain open.
 */
class EffectiveOwnerTransferPolicyService
{
    public function assertAllowed(Account $from, Account $to, array $meta = []): void
    {
        if ((bool) ($meta['system_operation'] ?? false)) {
            return;
        }

        $fromOwner = $this->ownerKey($from);
        $toOwner = $this->ownerKey($to);

        if ($fromOwner === $toOwner) {
            return;
        }

        if (str_starts_with($fromOwner, 'system:') || str_starts_with($toOwner, 'system:')) {
            return;
        }

        $threshold = (int) (Setting::query()->first()?->najm_bahar_user_threshold ?? 1111111);
        $userCount = User::query()->count();

        if ($userCount < $threshold) {
            throw new \RuntimeException(
                'همه تراکنشهای بین کاربران قفله. تبادل در خود حساب بین اصلی و فرعی و بالعکس و همچنین کلیه تراکنشهای سیستمی مثل واریز و برداشت برای سیستم بازه.'
            );
        }
    }

    private function ownerKey(Account $account): string
    {
        if ($account->type === 'system') {
            return 'system:' . $account->id;
        }

        if ($account->type === 'user' && $account->user_id) {
            return 'user:' . (int) $account->user_id;
        }

        if ($account->type === 'legal_entity') {
            $meta = (array) ($account->meta ?? []);
            return 'legal_entity:' . (int) ($meta['group_id'] ?? $account->id);
        }

        if ($account->type === 'subaccount') {
            $subAccount = SubAccount::query()
                ->where('sub_account_code', $account->account_number)
                ->first();

            if (! $subAccount) {
                throw new \RuntimeException('Sub-account owner could not be resolved.');
            }

            $parent = Account::query()->find($subAccount->account_id);
            if (! $parent) {
                throw new \RuntimeException('Sub-account parent account could not be resolved.');
            }

            return $this->ownerKey($parent);
        }

        return 'account:' . (int) $account->id;
    }
}
