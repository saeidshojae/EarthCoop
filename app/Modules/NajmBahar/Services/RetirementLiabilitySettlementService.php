<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\MonetaryRetirementLiability;
use Illuminate\Support\Facades\DB;

class RetirementLiabilitySettlementService
{
    public function __construct(
        private readonly TreasuryService $treasury,
        private readonly MonetaryService $money,
    ) {
    }

    /**
     * Settle as much of a system retirement liability as current protected
     * treasury surplus allows. The estate/member is never a funding source.
     *
     * Order is constitutional: Money Destruction Fund, then Idle Tax Fund.
     * The method is safe to call repeatedly as treasury liquidity changes.
     */
    public function settle(int $liabilityId): MonetaryRetirementLiability
    {
        return DB::transaction(function () use ($liabilityId) {
            $liability = MonetaryRetirementLiability::with('retirement')
                ->whereKey($liabilityId)
                ->lockForUpdate()
                ->firstOrFail();

            $outstanding = max(0, (int) $liability->amount - (int) $liability->settled_amount);
            if ($outstanding === 0) {
                if ($liability->status !== 'settled') {
                    $liability->status = 'settled';
                    $liability->save();
                }
                $this->syncRetirement($liability);
                return $liability->fresh('retirement');
            }

            $settledBefore = (int) $liability->settled_amount;
            $burn = $this->destroyFromFund(
                $liability,
                TreasuryService::MONEY_DESTRUCTION,
                $outstanding,
                'burn',
                $settledBefore
            );
            $outstanding -= $burn;

            $tax = $this->destroyFromFund(
                $liability,
                TreasuryService::IDLE_TAX,
                $outstanding,
                'idle-tax',
                $settledBefore + $burn
            );

            $newlySettled = $burn + $tax;
            $liability->settled_amount = min((int) $liability->amount, $settledBefore + $newlySettled);
            $liability->status = (int) $liability->settled_amount >= (int) $liability->amount
                ? 'settled'
                : 'outstanding';
            $liability->metadata = array_merge((array) ($liability->metadata ?? []), [
                'last_settlement_at' => now()->toIso8601String(),
                'last_settlement_burn_fund_gol' => $burn,
                'last_settlement_idle_tax_fund_gol' => $tax,
                'estate_not_liable' => true,
            ]);
            $liability->save();

            $this->syncRetirement($liability, $burn, $tax);

            return $liability->fresh('retirement');
        }, 3);
    }

    private function destroyFromFund(
        MonetaryRetirementLiability $liability,
        string $fundCode,
        int $requested,
        string $suffix,
        int $settledBefore
    ): int {
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
            'تسویه بدهی امحای پول ناشی از پایان عضویت',
            [
                'type' => 'membership_retirement_liability_settlement',
                'liability_id' => $liability->id,
                'retirement_id' => $liability->retirement_id,
                'treasury_fund' => $fundCode,
                'estate_not_liable' => true,
            ],
            'retirement-liability-' . $liability->id . '-' . $suffix . '-' . $settledBefore,
            true
        );

        return (int) $result['amount'];
    }

    private function syncRetirement(
        MonetaryRetirementLiability $liability,
        int $burnDelta = 0,
        int $taxDelta = 0
    ): void {
        $retirement = $liability->retirement()->lockForUpdate()->firstOrFail();

        if ($burnDelta > 0) {
            $retirement->active_destroyed_from_burn_fund =
                (int) $retirement->active_destroyed_from_burn_fund + $burnDelta;
        }
        if ($taxDelta > 0) {
            $retirement->active_destroyed_from_idle_tax_fund =
                (int) $retirement->active_destroyed_from_idle_tax_fund + $taxDelta;
        }

        $remaining = max(0, (int) $liability->amount - (int) $liability->settled_amount);
        $retirement->outstanding_liability = $remaining;
        $retirement->status = $remaining === 0 ? 'completed' : 'liability_outstanding';
        $retirement->save();
    }
}
