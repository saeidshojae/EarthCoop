<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\MembershipRetirement;
use App\Modules\NajmBahar\Models\MonetaryRetirementLiability;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Policy\NajmBaharConstitution;
use Illuminate\Support\Facades\DB;

class MembershipRetirementService
{
    public function __construct(
        private readonly AccountService $accounts,
        private readonly AccountBalanceService $balances,
        private readonly MonetaryService $money,
        private readonly TreasuryService $treasury,
    ) {
    }

    /**
     * Retire the monetary footprint of one membership.
     *
     * Economic wealth already activated/earned by the member is never debited.
     * This service only cancels remaining constitutional dim money and destroys
     * the complementary amount from system funds so the membership footprint
     * removed from the monetary system totals exactly 10,000 Bahar.
     */
    public function retire(int $userId, string $reason, array $metadata = []): MembershipRetirement
    {
        if (! in_array($reason, ['death', 'exit', 'removal'], true)) {
            throw new \InvalidArgumentException('Retirement reason must be death, exit, or removal.');
        }

        $idempotencyKey = 'membership-retirement-' . $userId;

        return DB::transaction(function () use ($userId, $reason, $metadata, $idempotencyKey) {
            $existing = MembershipRetirement::where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing->load('liability');
            }

            $main = $this->accounts->getMainAccountForUser($userId);
            if (! $main) {
                throw new \RuntimeException('Najm Bahar main account not found for retiring member.');
            }

            $constitutional = NajmBaharConstitution::initialMembershipGol();
            $before = $this->balances->aggregate($main);
            $dimTarget = min((int) $before['dim'], $constitutional);
            $dimCancelled = $this->cancelOwnerDim($main, $dimTarget, $userId);

            $requiredSystemDestruction = max(0, $constitutional - $dimCancelled);
            $remaining = $requiredSystemDestruction;

            $burnDestroyed = $this->destroyFromFund(
                TreasuryService::MONEY_DESTRUCTION,
                $remaining,
                $userId,
                'burn-fund'
            );
            $remaining -= $burnDestroyed;

            $idleTaxDestroyed = $this->destroyFromFund(
                TreasuryService::IDLE_TAX,
                $remaining,
                $userId,
                'idle-tax-fund'
            );
            $remaining -= $idleTaxDestroyed;

            $retirement = MembershipRetirement::create([
                'user_id' => $userId,
                'reason' => $reason,
                'constitutional_amount' => $constitutional,
                'dim_cancelled' => $dimCancelled,
                'active_destroyed_from_burn_fund' => $burnDestroyed,
                'active_destroyed_from_idle_tax_fund' => $idleTaxDestroyed,
                'outstanding_liability' => $remaining,
                'status' => $remaining > 0 ? 'liability_outstanding' : 'completed',
                'idempotency_key' => $idempotencyKey,
                'metadata' => array_merge($metadata, [
                    'active_wealth_before' => (int) $before['active'],
                    'dim_before' => (int) $before['dim'],
                    'dim_above_constitutional_footprint_preserved' => max(0, (int) $before['dim'] - $constitutional),
                    'estate_assets_untouched' => true,
                ]),
                'retired_at' => now(),
            ]);

            if ($remaining > 0) {
                MonetaryRetirementLiability::create([
                    'retirement_id' => $retirement->id,
                    'amount' => $remaining,
                    'settled_amount' => 0,
                    'status' => 'outstanding',
                    'metadata' => [
                        'debtor' => 'earthcoop_monetary_system',
                        'estate_not_liable' => true,
                    ],
                ]);
            }

            return $retirement->load('liability');
        }, 3);
    }

    private function cancelOwnerDim(Account $main, int $target, int $userId): int
    {
        if ($target <= 0) {
            return 0;
        }

        $remaining = $target;
        $cancelled = 0;

        $mainResult = $this->money->cancelDim(
            $main,
            $remaining,
            'لغو پول کمرنگ باقیمانده در پایان عضویت',
            ['type' => 'membership_retirement_dim_cancellation', 'user_id' => $userId],
            'membership-retirement-' . $userId . '-dim-main',
            true
        );
        $cancelled += (int) $mainResult['amount'];
        $remaining -= (int) $mainResult['amount'];

        if ($remaining <= 0) {
            return $cancelled;
        }

        $subs = SubAccount::where('account_id', $main->id)->orderBy('id')->get();
        foreach ($subs as $sub) {
            if ($remaining <= 0) {
                break;
            }

            $mirror = $this->accounts->ensureSubAccountAccount($sub);
            $result = $this->money->cancelDim(
                $mirror,
                $remaining,
                'لغو پول کمرنگ حساب فرعی در پایان عضویت',
                [
                    'type' => 'membership_retirement_dim_cancellation',
                    'user_id' => $userId,
                    'sub_account_id' => $sub->id,
                ],
                'membership-retirement-' . $userId . '-dim-sub-' . $sub->id,
                true
            );
            $cancelled += (int) $result['amount'];
            $remaining -= (int) $result['amount'];
        }

        return $cancelled;
    }

    private function destroyFromFund(string $fundCode, int $requested, int $userId, string $suffix): int
    {
        if ($requested <= 0) {
            return 0;
        }

        $fund = $this->treasury->get($fundCode);
        $available = min($requested, $fund->availableSurplus());
        if ($available <= 0) {
            return 0;
        }

        $result = $this->money->destroyActive(
            $fund->account,
            $available,
            'امحای سیستمی برای خنثی‌سازی اثر پولی پایان عضویت',
            [
                'type' => 'membership_retirement_system_destruction',
                'user_id' => $userId,
                'treasury_fund' => $fundCode,
            ],
            'membership-retirement-' . $userId . '-destroy-' . $suffix,
            true
        );

        return (int) $result['amount'];
    }
}
