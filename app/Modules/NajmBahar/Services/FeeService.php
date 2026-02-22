<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\Fee;
use App\Models\Setting;
use App\Helpers\BaharMoney;
use App\Services\NajmHoda\Runtime\NajmHodaDomainEventPolicyLinkService;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Throwable;

class FeeService
{
    /**
     * Get membership fee amount
     */
    public function getMembershipFee(): int
    {
        $context = [
            'scope' => 'economy:najm-bahar',
            'risk' => 'low',
        ];
        $this->emitRuntime('najm_hoda.input.najm_bahar.service.fee.membership.requested', $context);

        try {
            $settings = Setting::firstNajmBaharSettings();
            $configuredAmount = (int) ($settings?->najm_bahar_membership_fee_amount ?? 0);
            if ($configuredAmount > 0) {
                $this->emitRuntime('najm_hoda.input.najm_bahar.service.fee.membership.succeeded', array_merge($context, [
                    'source' => 'settings_membership_fee_amount',
                    'amount' => $configuredAmount,
                ]));
                return $configuredAmount;
            }

            $configuredSplitTotal = $this->getMembershipSplitTotal($settings);
            if ($configuredSplitTotal > 0) {
                $this->emitRuntime('najm_hoda.input.najm_bahar.service.fee.membership.succeeded', array_merge($context, [
                    'source' => 'settings_split_total',
                    'amount' => $configuredSplitTotal,
                ]));
                return $configuredSplitTotal;
            }

            $fee = Fee::where('name', 'membership_fee')
                ->where('is_active', true)
                ->first();

            $amount = $fee ? (int) $fee->fixed_amount : BaharMoney::toGolFromBahar(12);
            $this->emitRuntime('najm_hoda.input.najm_bahar.service.fee.membership.succeeded', array_merge($context, [
                'source' => $fee ? 'db_fee' : 'default',
                'amount' => $amount,
            ]));

            return $amount;
        } catch (Throwable $exception) {
            $this->emitRuntime('najm_hoda.input.najm_bahar.service.fee.membership.failed', array_merge($context, [
                'error' => $exception->getMessage(),
            ]));
            throw $exception;
        }
    }

    public function getMembershipSplitTotal(?Setting $settings): int
    {
        if (!$settings) {
            return 0;
        }

        $membershipAmount = (int) ($settings->najm_bahar_membership_fee_membership_amount ?? 0);
        $insuranceAmount = (int) ($settings->najm_bahar_membership_fee_insurance_amount ?? 0);
        $burnAmount = (int) ($settings->najm_bahar_membership_fee_burn_amount ?? 0);

        return $membershipAmount + $insuranceAmount + $burnAmount;
    }

    /**
     * Calculate fee for a transaction
     */
    public function calculateTransactionFee(int $amount, string $transactionType = 'immediate'): int
    {
        $context = [
            'amount' => $amount,
            'transaction_type' => $transactionType,
            'scope' => 'economy:najm-bahar',
            'risk' => 'low',
        ];
        $this->emitRuntime('najm_hoda.input.najm_bahar.service.fee.calculate.requested', $context);

        try {
            $fees = Fee::where('transaction_type', $transactionType)
                ->orWhere('transaction_type', 'all')
                ->where('is_active', true)
                ->get();

            $totalFee = 0;

            foreach ($fees as $fee) {
                $calculatedFee = 0;

            switch ($fee->type) {
                case 'fixed':
                    $calculatedFee = $fee->fixed_amount;
                    break;
                
                case 'percentage':
                    $calculatedFee = intval($amount * ($fee->percentage / 100));
                    break;
                
                case 'combined':
                    $calculatedFee = $fee->fixed_amount + intval($amount * ($fee->percentage / 100));
                    break;
            }

            // اعمال حداقل و حداکثر
            if ($fee->min_amount && $calculatedFee < $fee->min_amount) {
                $calculatedFee = $fee->min_amount;
            }
            
            if ($fee->max_amount && $calculatedFee > $fee->max_amount) {
                $calculatedFee = $fee->max_amount;
            }

                $totalFee += $calculatedFee;
            }

            $this->emitRuntime('najm_hoda.input.najm_bahar.service.fee.calculate.succeeded', array_merge($context, [
                'fee_count' => (int) $fees->count(),
                'total_fee' => $totalFee,
            ]));

            return $totalFee;
        } catch (Throwable $exception) {
            $this->emitRuntime('najm_hoda.input.najm_bahar.service.fee.calculate.failed', array_merge($context, [
                'error' => $exception->getMessage(),
            ]));
            throw $exception;
        }
    }

    /**
     * Get all active fees
     */
    public function getActiveFees()
    {
        $context = [
            'scope' => 'economy:najm-bahar',
            'risk' => 'low',
        ];
        $this->emitRuntime('najm_hoda.input.najm_bahar.service.fee.active_list.requested', $context);

        try {
            $result = Fee::where('is_active', true)->get();
            $this->emitRuntime('najm_hoda.input.najm_bahar.service.fee.active_list.succeeded', array_merge($context, [
                'count' => (int) $result->count(),
            ]));
            return $result;
        } catch (Throwable $exception) {
            $this->emitRuntime('najm_hoda.input.najm_bahar.service.fee.active_list.failed', array_merge($context, [
                'error' => $exception->getMessage(),
            ]));
            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function emitRuntime(string $event, array $payload): void
    {
        try {
            /** @var RuntimeEventBus $bus */
            $bus = app(RuntimeEventBus::class);
            $bus->emit($event, $payload);
            /** @var NajmHodaDomainEventPolicyLinkService $link */
            $link = app(NajmHodaDomainEventPolicyLinkService::class);
            $link->ingest($event, $payload);
        } catch (Throwable) {
            // Fee computation should not fail due to telemetry.
        }
    }
}
