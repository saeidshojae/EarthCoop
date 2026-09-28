<?php

namespace App\Modules\NajmBahar\Services\Api;

use App\Helpers\BaharMoney;
use App\Models\User;
use App\Models\UserPointTransaction;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Services\AccountBalanceService;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\ActiveBaharReservationService;
use App\Modules\NajmBahar\Services\FeeService;
use App\Modules\NajmBahar\Services\MonetaryPolicyService;
use App\Modules\NajmBahar\Services\MonetaryService;
use App\Modules\NajmBahar\Services\TransactionService;
use App\Modules\NajmBahar\Services\TreasuryService;
use App\Services\MembershipFeeStatusService;
use App\Services\ReputationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class NajmBaharMembershipFeeApplicationService
{
    public function __construct(
        private readonly TransactionService $transactions,
        private readonly AccountService $accounts,
        private readonly AccountBalanceService $balances,
        private readonly ActiveBaharReservationService $reservations,
        private readonly FeeService $fees,
        private readonly MonetaryService $monetary,
        private readonly MonetaryPolicyService $policy,
        private readonly TreasuryService $treasury,
        private readonly ReputationService $reputation,
        private readonly MembershipFeeStatusService $status,
    ) {
    }

    public function info(User $user): array
    {
        $account = $this->mainAccount($user);
        $fee = $this->feeAmount();
        $split = $this->split();
        $this->assertValidSplit($fee, $split);

        $aggregate = $this->balances->aggregate($account);

        return [
            'has_paid' => $this->status->hasPaidCurrentMembershipFee($user),
            'payment_year' => $this->status->membershipPaymentYear($user),
            'fee_gol' => $fee,
            'breakdown' => [
                TreasuryService::OPERATIONS_SALARY.'_gol' => $split[TreasuryService::OPERATIONS_SALARY],
                TreasuryService::CENTRAL_INSURANCE.'_gol' => $split[TreasuryService::CENTRAL_INSURANCE],
                TreasuryService::MONEY_DESTRUCTION.'_gol' => $split[TreasuryService::MONEY_DESTRUCTION],
            ],
            'can_pay_from_dim' => (int) ($account->balance_faded ?? 0) >= $fee,
            'can_pay_from_active' => (int) ($aggregate['active'] ?? 0) >= $fee,
            'default_payment_source' => (int) ($account->balance_faded ?? 0) >= $fee ? 'dim' : 'active',
            'policy_version_id' => $this->policy->versionId(),
            'balance' => [
                'local' => $this->projectBalance($this->balances->local($account)),
                'aggregate' => $this->projectBalance($aggregate),
            ],
        ];
    }

    /**
     * @return array{account:Account,has_paid:bool,payment_year:int,fee_gol:int,payment_source:string,breakdown:array,balance:array}
     */
    public function pay(User $user, string $paymentSource, ?int $subAccountId = null): array
    {
        $account = $this->mainAccount($user);
        if ($this->status->hasPaidCurrentMembershipFee($user)) {
            throw new NajmBaharMembershipFeeException(
                'already_paid',
                'The annual membership fee has already been paid for the current membership year.',
            );
        }

        $fee = $this->feeAmount();
        $split = $this->split();
        $this->assertValidSplit($fee, $split);
        $paymentYear = $this->status->membershipPaymentYear($user);
        $policyVersionId = $this->policy->versionId();

        DB::transaction(function () use (
            $user,
            $account,
            $paymentSource,
            $subAccountId,
            $fee,
            $split,
            $paymentYear,
            $policyVersionId,
        ) {
            $lockedAccount = Account::query()
                ->whereKey((int) $account->id)
                ->where('user_id', (int) $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->status->hasPaidCurrentMembershipFee($user)) {
                return;
            }

            $sourceAccountNumber = (string) $lockedAccount->account_number;

            if ($paymentSource === 'dim') {
                if ((int) ($lockedAccount->balance_faded ?? 0) < $fee) {
                    throw new NajmBaharMembershipFeeException(
                        'insufficient_dim',
                        'Dim balance is insufficient for the annual membership fee.',
                    );
                }

                $this->monetary->activateDim(
                    $lockedAccount,
                    $fee,
                    'فعال‌سازی حق عضویت سالانه EarthCoop',
                    [
                        'type' => 'membership_fee_activation',
                        'user_id' => (int) $user->id,
                        'payment_year' => $paymentYear,
                        'policy_version_id' => $policyVersionId,
                    ],
                    'membership-fee-activation-'.(int) $user->id.'-'.$paymentYear,
                    false,
                );
            } elseif ($paymentSource === 'active') {
                $sourceAccountNumber = $this->resolveActiveSource(
                    $lockedAccount,
                    $fee,
                    $subAccountId,
                );
            } else {
                throw new NajmBaharMembershipFeeException(
                    'payment_source_invalid',
                    'Unsupported membership-fee payment source.',
                    422,
                );
            }

            $this->distribute(
                $sourceAccountNumber,
                (int) $user->id,
                $paymentYear,
                $split,
                $paymentSource,
                $policyVersionId,
            );

            $this->awardParticipation($user, $paymentYear, $paymentSource, $policyVersionId);
        });

        $account->refresh();

        return [
            'account' => $account,
            'has_paid' => $this->status->hasPaidCurrentMembershipFee($user),
            'payment_year' => $paymentYear,
            'fee_gol' => $fee,
            'payment_source' => $paymentSource,
            'breakdown' => [
                TreasuryService::OPERATIONS_SALARY.'_gol' => $split[TreasuryService::OPERATIONS_SALARY],
                TreasuryService::CENTRAL_INSURANCE.'_gol' => $split[TreasuryService::CENTRAL_INSURANCE],
                TreasuryService::MONEY_DESTRUCTION.'_gol' => $split[TreasuryService::MONEY_DESTRUCTION],
            ],
            'balance' => [
                'local' => $this->projectBalance($this->balances->local($account)),
                'aggregate' => $this->projectBalance($this->balances->aggregate($account)),
            ],
        ];
    }

    public function feeAmount(): int
    {
        return (int) $this->fees->getMembershipFee();
    }

    /** @return array<string,int> */
    public function split(): array
    {
        return [
            TreasuryService::OPERATIONS_SALARY => max(0, (int) $this->policy->parameter(
                'membership_operations_gol',
                BaharMoney::toGolFromBahar(6),
            )),
            TreasuryService::CENTRAL_INSURANCE => max(0, (int) $this->policy->parameter(
                'membership_insurance_gol',
                BaharMoney::toGolFromBahar(3),
            )),
            TreasuryService::MONEY_DESTRUCTION => max(0, (int) $this->policy->parameter(
                'membership_burn_gol',
                BaharMoney::toGolFromBahar(3),
            )),
        ];
    }

    private function mainAccount(User $user): Account
    {
        $account = $this->accounts->getMainAccountForUser((int) $user->id);
        if (! $account instanceof Account || (int) $account->user_id !== (int) $user->id) {
            throw (new ModelNotFoundException())->setModel(Account::class);
        }

        return $account;
    }

    private function assertValidSplit(int $fee, array $split): void
    {
        if ($fee <= 0 || array_sum($split) !== $fee) {
            throw new NajmBaharMembershipFeeException(
                'membership_fee_policy_invalid',
                'Membership fee policy split must exactly equal the declared annual fee.',
            );
        }
    }

    private function resolveActiveSource(Account $main, int $fee, ?int $subAccountId): string
    {
        if ($subAccountId !== null) {
            $sub = SubAccount::query()
                ->whereKey($subAccountId)
                ->where('account_id', (int) $main->id)
                ->where('status', 1)
                ->lockForUpdate()
                ->first();

            if (! $sub instanceof SubAccount) {
                throw (new ModelNotFoundException())->setModel(SubAccount::class);
            }

            $mirror = $this->accounts->ensureSubAccountAccount($sub);
            if ((int) ($sub->balance_active ?? 0) < $fee || $this->reservations->availableActive($mirror) < $fee) {
                throw new NajmBaharMembershipFeeException(
                    'insufficient_available_funds',
                    'Available Active balance is insufficient for the annual membership fee.',
                );
            }

            return (string) $sub->sub_account_code;
        }

        if ((int) ($main->balance_active ?? 0) >= $fee && $this->reservations->availableActive($main) >= $fee) {
            return (string) $main->account_number;
        }

        $subAccounts = SubAccount::query()
            ->where('account_id', (int) $main->id)
            ->where('status', 1)
            ->where('balance_active', '>=', $fee)
            ->orderBy('created_at')
            ->lockForUpdate()
            ->get();

        foreach ($subAccounts as $sub) {
            $mirror = $this->accounts->ensureSubAccountAccount($sub);
            if ($this->reservations->availableActive($mirror) >= $fee) {
                return (string) $sub->sub_account_code;
            }
        }

        throw new NajmBaharMembershipFeeException(
            'insufficient_available_funds',
            'Available Active balance is insufficient for the annual membership fee.',
        );
    }

    private function distribute(
        string $sourceAccountNumber,
        int $userId,
        int $paymentYear,
        array $split,
        string $paymentSource,
        ?int $policyVersionId,
    ): void {
        $funds = $this->treasury->ensureDefaultFunds();

        foreach ([
            TreasuryService::OPERATIONS_SALARY,
            TreasuryService::CENTRAL_INSURANCE,
            TreasuryService::MONEY_DESTRUCTION,
        ] as $code) {
            $amount = (int) ($split[$code] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $this->transactions->transfer(
                $sourceAccountNumber,
                (string) $funds[$code]->account->account_number,
                $amount,
                'پرداخت حق عضویت سالانه EarthCoop',
                [
                    'type' => 'membership_fee',
                    'user_id' => $userId,
                    'split' => $code,
                    'user_initiated' => true,
                    'system_operation' => true,
                    'payment_source' => $paymentSource,
                    'payment_year' => $paymentYear,
                    'policy_version_id' => $policyVersionId,
                ],
                'membership-fee-'.$userId.'-'.$code.'-'.$paymentYear,
                'active',
                'membership_fee',
            );
        }
    }

    private function awardParticipation(
        User $user,
        int $paymentYear,
        string $paymentSource,
        ?int $policyVersionId,
    ): void {
        if (UserPointTransaction::query()
            ->where('user_id', (int) $user->id)
            ->where('action', 'membership_fee_paid')
            ->where('reference_id', $paymentYear)
            ->exists()) {
            return;
        }

        $this->reputation->applyAction(
            $user,
            'membership_fee_paid',
            [
                'payment_year' => $paymentYear,
                'payment_source' => $paymentSource,
                'policy_version_id' => $policyVersionId,
            ],
            $paymentYear,
            'najm_bahar_membership',
        );
    }

    private function projectBalance(array $balance): array
    {
        return [
            'active_gol' => (int) $balance['active'],
            'dim_available_gol' => (int) $balance['dim_available'],
            'dim_committed_gol' => (int) $balance['dim_committed'],
            'dim_total_gol' => (int) $balance['dim'],
            'total_gol' => (int) $balance['total'],
        ];
    }
}
