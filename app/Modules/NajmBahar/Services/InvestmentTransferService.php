<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Canonical Active-Bahar settlement boundary for investments.
 *
 * Economic actors never transfer Main -> Main directly. Funds are staged into
 * an owner-controlled clearing sub-account, transferred across owners through
 * CrossOwnerActiveSubAccountTransferService, then swept into the recipient's
 * main account. The whole flow is expected to be called inside the investment
 * domain transaction, while every monetary stage is independently idempotent.
 */
class InvestmentTransferService
{
    private const CLEARING_SUB_ACCOUNT_NAME = 'Najm Bahar Investment Clearing';

    public function __construct(
        private readonly InternalAccountTransferService $internalTransfer,
        private readonly CrossOwnerActiveSubAccountTransferService $crossOwnerTransfer,
        private readonly TransactionService $transferPolicy,
    ) {
    }

    public function transfer(
        string $fromAccountNumber,
        string $toAccountNumber,
        int $amount,
        ?string $description = null,
        array $meta = [],
        ?string $idempotencyKey = null,
    ): NajmTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Investment transfer amount must be positive.');
        }

        if (! $idempotencyKey || trim($idempotencyKey) === '') {
            throw new \InvalidArgumentException('Investment transfer requires an idempotency key.');
        }

        $from = Account::query()->where('account_number', $fromAccountNumber)->firstOrFail();
        $to = Account::query()->where('account_number', $toAccountNumber)->firstOrFail();

        if ((int) $from->id === (int) $to->id) {
            throw new \RuntimeException('Investment payer and recipient accounts must be different.');
        }

        if (! in_array($from->type, ['user', 'legal_entity'], true)
            || ! in_array($to->type, ['user', 'legal_entity'], true)) {
            throw new \RuntimeException('Investment settlement requires economic-actor main accounts.');
        }

        // Preserve the constitutional/pre-threshold transfer gate. This service
        // is an explicit boundary, not a system-operation bypass.
        $this->transferPolicy->assertEffectiveOwnerTransferAllowed($from, $to, $meta);

        return DB::transaction(function () use ($from, $to, $amount, $description, $meta, $idempotencyKey) {
            $sourceClearing = $this->clearingSubAccount($from);
            $destinationClearing = $this->clearingSubAccount($to);

            $stageMeta = array_merge($meta, [
                'domain' => 'investment',
                'money_state' => 'active',
                'canonical_boundary' => 'investment_transfer_service',
            ]);

            $this->internalTransfer->mainToSub(
                $from,
                $sourceClearing,
                $amount,
                'active',
                $description ?? 'تخصیص وجه فعال برای سرمایه‌گذاری',
                $idempotencyKey . ':source-stage',
                array_merge($stageMeta, ['stage' => 'source_main_to_clearing'])
            );

            $settlement = $this->crossOwnerTransfer->transfer(
                $sourceClearing,
                $destinationClearing,
                $amount,
                $description ?? 'تسویه سرمایه‌گذاری بین اعضا',
                $idempotencyKey,
                array_merge($stageMeta, ['stage' => 'cross_owner_settlement'])
            );

            $this->internalTransfer->subToMain(
                $destinationClearing,
                $to,
                $amount,
                'active',
                $description ?? 'واریز وجه سرمایه‌گذاری به حساب مالک پروژه',
                $idempotencyKey . ':destination-stage',
                array_merge($stageMeta, ['stage' => 'destination_clearing_to_main'])
            );

            return $settlement;
        });
    }

    private function clearingSubAccount(Account $main): SubAccount
    {
        return DB::transaction(function () use ($main) {
            $lockedMain = Account::query()->whereKey($main->id)->lockForUpdate()->firstOrFail();

            $existing = SubAccount::query()
                ->where('account_id', $lockedMain->id)
                ->where('name', self::CLEARING_SUB_ACCOUNT_NAME)
                ->where('status', 1)
                ->orderBy('id')
                ->first();

            if ($existing) {
                app(AccountService::class)->ensureSubAccountAccount($existing);
                return $existing;
            }

            return app(SubAccountService::class)->createSubAccount(
                (int) $lockedMain->id,
                self::CLEARING_SUB_ACCOUNT_NAME
            );
        });
    }
}
