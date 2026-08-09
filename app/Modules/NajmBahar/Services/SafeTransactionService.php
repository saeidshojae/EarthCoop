<?php

namespace App\Modules\NajmBahar\Services;

use App\Models\Setting;
use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Transitional adapter around TransactionService.
 *
 * Existing active transfers keep the mature locking/idempotency behavior in
 * TransactionService. Own Main ↔ Sub movements are routed to the canonical
 * internal service so Account.balance remains local and money state is never
 * changed as a side effect of moving between a member's own accounts.
 *
 * Release C also resolves the effective owner of sub-account mirrors through
 * their parent account. This closes the legacy policy gap where sub-account
 * Account rows have no user_id and could therefore bypass the pre-threshold
 * cross-member transfer lock.
 *
 * Dim money is not spendable/transferable between economic actors. It may only
 * move between the same owner's accounts or be consumed by explicit monetary
 * operations such as activation, membership payment, or retirement.
 *
 * Release D retires the generic SubAccount ↔ SubAccount fallback entirely.
 * Those transfers must enter through SubAccountService and its canonical
 * executors so direct callers cannot reactivate legacy balance mutation.
 */
class SafeTransactionService extends TransactionService
{
    public function transfer(
        string|null $fromAccountNumber,
        string $toAccountNumber,
        int $amount,
        string $description = null,
        array $meta = [],
        string|null $idempotencyKey = null,
        string $balanceType = 'balance',
        ?string $transactionType = null
    ): NajmTransaction {
        $from = $fromAccountNumber
            ? Account::where('account_number', $fromAccountNumber)->first()
            : null;
        $to = Account::where('account_number', $toAccountNumber)->first();

        if ($fromAccountNumber
            && in_array($balanceType, ['active', 'faded'], true)
            && $amount > 0
            && $from
            && $to) {
            $internal = $this->resolveInternalOwnTransfer($from, $to);
            if ($internal) {
                [$direction, $main, $sub] = $internal;
                $requestKey = request()?->header('Idempotency-Key');
                $key = $idempotencyKey;
                if (! $key && is_string($requestKey) && trim($requestKey) !== '') {
                    $key = implode('-', [
                        'safe-internal-transfer',
                        $direction,
                        $main->id,
                        $sub->id,
                        $balanceType,
                        $amount,
                        hash('sha256', trim($requestKey)),
                    ]);
                }
                $key ??= implode('-', [
                    'safe-internal-transfer',
                    $direction,
                    $main->id,
                    $sub->id,
                    $balanceType,
                    $amount,
                    bin2hex(random_bytes(12)),
                ]);

                $internalService = app(InternalAccountTransferService::class);
                $metadata = array_merge($meta, [
                    'requested_transaction_type' => $transactionType,
                    'routed_by' => 'safe_transaction_service',
                ]);

                if ($direction === 'main_to_sub') {
                    return $internalService->mainToSub(
                        $main,
                        $sub,
                        $amount,
                        $balanceType,
                        $description ?? 'انتقال داخلی به حساب فرعی',
                        $key,
                        $metadata
                    );
                }

                return $internalService->subToMain(
                    $sub,
                    $main,
                    $amount,
                    $balanceType,
                    $description ?? 'انتقال داخلی به حساب اصلی',
                    $key,
                    $metadata
                );
            }
        }

        if ($from
            && $to
            && $from->type === 'subaccount'
            && $to->type === 'subaccount') {
            throw new \RuntimeException(
                'Sub-account to sub-account transfers must use the canonical SubAccountService boundary.'
            );
        }

        if ($from
            && $to
            && $from->type === 'subaccount'
            && $to->type === 'system'
            && $balanceType === 'active') {
            $sourceSubAccount = SubAccount::where('sub_account_code', $from->account_number)->firstOrFail();

            return app(SubAccountSystemTransferService::class)->transfer(
                $sourceSubAccount,
                $to,
                $amount,
                $description,
                $meta,
                $idempotencyKey,
                $transactionType
            );
        }

        if ($from
            && $to
            && in_array($from->type, ['user', 'legal_entity'], true)
            && $to->type === 'system'
            && $balanceType === 'active'
            && (bool) ($meta['system_operation'] ?? false)) {
            return app(MainAccountSystemTransferService::class)->transfer(
                $from,
                $to,
                $amount,
                $description,
                $meta,
                $idempotencyKey,
                $transactionType
            );
        }

        // Constitutional Dim non-transferability is the stronger rule and must
        // take precedence over the temporary cross-member threshold lock. Keep
        // this check before threshold validation so legacy callers receive the
        // stable domain error and cannot reinterpret Dim as merely time-locked.
        if ($balanceType === 'faded') {
            throw new \RuntimeException('پول کمرنگ قابل انتقال بین اشخاص یا نهادهای مستقل نیست. ابتدا باید از یک مسیر مجاز فعال شود.');
        }

        if ($from && $to) {
            $this->assertEffectiveOwnerTransferAllowed($from, $to, $meta);
        }

        return DB::transaction(function () use (
            $fromAccountNumber,
            $toAccountNumber,
            $amount,
            $description,
            $meta,
            $idempotencyKey,
            $balanceType,
            $transactionType
        ) {
            $transaction = parent::transfer(
                $fromAccountNumber,
                $toAccountNumber,
                $amount,
                $description,
                $meta,
                $idempotencyKey,
                $balanceType,
                $transactionType
            );

            $subAccounts = collect([$fromAccountNumber, $toAccountNumber])
                ->filter()
                ->unique()
                ->map(fn (string $accountNumber) => SubAccount::where('sub_account_code', $accountNumber)->first())
                ->filter();

            $invariants = app(AccountInvariantService::class);
            foreach ($subAccounts as $subAccount) {
                $invariants->reconcileSubAccountMirror($subAccount);
            }

            return $transaction;
        });
    }

    /**
     * Enforce the cross-member transfer gate using effective ownership rather
     * than Account.user_id alone. Sub-account mirrors inherit the owner of their
     * parent main account. System/legal-entity policy remains with the existing
     * TransactionService rules.
     */
    public function assertEffectiveOwnerTransferAllowed(Account $from, Account $to, array $meta = []): void
    {
        if ((bool) ($meta['system_operation'] ?? false)) {
            return;
        }

        $fromOwner = $this->effectiveUserId($from);
        $toOwner = $this->effectiveUserId($to);

        if (! $fromOwner || ! $toOwner || $fromOwner === $toOwner) {
            return;
        }

        $setting = Setting::first();
        $threshold = (int) ($setting?->najm_bahar_user_threshold ?? 1111111);

        if (User::count() < $threshold) {
            throw new \RuntimeException('همه تراکنشهای بین کاربران قفله. تبادل در خود حساب بین اصلی و فرعی و بالعکس و همچنین کلیه تراکنشهای سیستمی مثل واریز و برداشت برای سیستم بازه.');
        }
    }

    private function effectiveUserId(Account $account): ?int
    {
        if ($account->user_id) {
            return (int) $account->user_id;
        }

        if ($account->type !== 'subaccount') {
            return null;
        }

        $sub = SubAccount::where('sub_account_code', $account->account_number)->first();
        if (! $sub) {
            return null;
        }

        $parent = Account::find($sub->account_id);
        return $parent?->user_id ? (int) $parent->user_id : null;
    }

    private function resolveInternalOwnTransfer(Account $from, Account $to): ?array
    {
        if ($from->type !== 'subaccount' && $to->type === 'subaccount') {
            $sub = SubAccount::where('sub_account_code', $to->account_number)->first();
            if ($sub && (int) $sub->account_id === (int) $from->id) {
                return ['main_to_sub', $from, $sub];
            }
        }

        if ($from->type === 'subaccount' && $to->type !== 'subaccount') {
            $sub = SubAccount::where('sub_account_code', $from->account_number)->first();
            if ($sub && (int) $sub->account_id === (int) $to->id) {
                return ['sub_to_main', $to, $sub];
            }
        }

        return null;
    }
}
