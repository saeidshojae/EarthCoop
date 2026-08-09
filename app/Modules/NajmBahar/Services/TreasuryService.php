<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\TreasuryFund;
use App\Modules\NajmBahar\Models\TreasuryTransfer;
use Illuminate\Support\Facades\DB;

class TreasuryService
{
    public const OPERATIONS_SALARY = 'operations_salary';
    public const CENTRAL_INSURANCE = 'central_insurance';
    public const MONEY_DESTRUCTION = 'money_destruction';
    public const IDLE_TAX = 'idle_tax';

    public function __construct(
        private readonly AccountService $accountService,
        private readonly TransactionService $transactionService
    ) {
    }

    public function ensureDefaultFunds(): array
    {
        return DB::transaction(function () {
            $systemAccount = $this->accountService->getSystemAccount();
            $this->accountService->ensureDefaultSystemSubAccounts($systemAccount);

            $definitions = [
                self::OPERATIONS_SALARY => [
                    'suffix' => 1,
                    'name' => 'صندوق حقوق و هزینه‌های EarthCoop',
                    'purpose' => 'پرداخت حقوق، هزینه‌های جاری و زیرساخت‌های مصوب EarthCoop',
                ],
                self::CENTRAL_INSURANCE => [
                    'suffix' => 2,
                    'name' => 'صندوق بیمه مرکزی',
                    'purpose' => 'ذخیره و پشتیبانی سیاست‌های بیمه مرکزی و تعهدات مصوب',
                ],
                self::MONEY_DESTRUCTION => [
                    'suffix' => 0,
                    'name' => 'صندوق امحای پول',
                    'purpose' => 'تأمین ظرفیت امحای پول و تسویه تعهدات retirement پولی',
                ],
                self::IDLE_TAX => [
                    'suffix' => 3,
                    'name' => 'صندوق مالیات پول راکد',
                    'purpose' => 'تجمیع مالیات پول راکد و بازتخصیص شفاف آن طبق سیاست اقتصادی',
                ],
            ];

            $funds = [];
            foreach ($definitions as $code => $definition) {
                $subAccountCode = AccountNumberService::makeSubAccountCode(
                    $systemAccount->account_number,
                    $definition['suffix']
                );

                $subAccount = $this->accountService->getSystemSubAccountByCode($subAccountCode);
                if (! $subAccount) {
                    throw new \RuntimeException("System treasury sub-account {$subAccountCode} is missing.");
                }

                $account = $this->accountService->ensureSubAccountAccount($subAccount);

                $funds[$code] = TreasuryFund::updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => $definition['name'],
                        'account_id' => $account->id,
                        'purpose' => $definition['purpose'],
                        'is_active' => true,
                    ]
                );
            }

            return $funds;
        });
    }

    public function get(string $code): TreasuryFund
    {
        $this->ensureDefaultFunds();

        return TreasuryFund::with('account')->where('code', $code)->firstOrFail();
    }

    /**
     * Move active Bahar between system funds without touching protected reserves.
     * The operation is idempotent and leaves a dedicated treasury audit record.
     */
    public function transferSurplus(
        string $fromCode,
        string $toCode,
        int $amount,
        string $reason,
        string $idempotencyKey,
        ?int $authorizedBy = null,
        ?string $policyReference = null,
        array $meta = []
    ): TreasuryTransfer {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Treasury transfer amount must be positive.');
        }

        if ($fromCode === $toCode) {
            throw new \InvalidArgumentException('Treasury source and destination funds must differ.');
        }

        return DB::transaction(function () use (
            $fromCode,
            $toCode,
            $amount,
            $reason,
            $idempotencyKey,
            $authorizedBy,
            $policyReference,
            $meta
        ) {
            $existing = TreasuryTransfer::where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $this->ensureDefaultFunds();

            $from = TreasuryFund::with('account')->where('code', $fromCode)->lockForUpdate()->firstOrFail();
            $to = TreasuryFund::with('account')->where('code', $toCode)->lockForUpdate()->firstOrFail();

            if (! $from->is_active || ! $to->is_active) {
                throw new \RuntimeException('Treasury fund is inactive.');
            }

            if ($from->availableSurplus() < $amount) {
                throw new \RuntimeException('Treasury transfer would violate reserve or committed-liability protection.');
            }

            $transaction = $this->transactionService->transfer(
                $from->account->account_number,
                $to->account->account_number,
                $amount,
                $reason,
                array_merge($meta, [
                    'type' => 'interfund_transfer',
                    'from_fund' => $fromCode,
                    'to_fund' => $toCode,
                    'authorized_by' => $authorizedBy,
                    'policy_reference' => $policyReference,
                    'system_operation' => true,
                ]),
                'treasury-transfer-' . $idempotencyKey,
                'active',
                'interfund_transfer'
            );

            return TreasuryTransfer::create([
                'from_fund_id' => $from->id,
                'to_fund_id' => $to->id,
                'transaction_id' => $transaction->id,
                'authorized_by' => $authorizedBy,
                'amount' => $amount,
                'reason' => $reason,
                'policy_reference' => $policyReference,
                'idempotency_key' => $idempotencyKey,
                'meta' => $meta,
            ]);
        });
    }
}
