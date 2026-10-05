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
        $terms = $this->terms($user);
        $fee = $terms['fee_gol'];
        $split = $terms['split'];

        $aggregate = $this->balances->aggregate($account);

        return [
            'has_paid' => $this->status->hasPaidCurrentMembershipFee($user),
            'payment_year' => $terms['payment_year'],
            'fee_gol' => $fee,
            'breakdown' => [
                TreasuryService::OPERATIONS_SALARY.'_gol' => $split[TreasuryService::OPERATIONS_SALARY],
                TreasuryService::CENTRAL_INSURANCE.'_gol' => $split[TreasuryService::CENTRAL_INSURANCE],
                TreasuryService::MONEY_DESTRUCTION.'_gol' => $split[TreasuryService::MONEY_DESTRUCTION],
            ],
            'can_pay_from_dim' => (int) ($account->balance_faded ?? 0) >= $fee,
            'can_pay_from_active' => (int) ($aggregate['active'] ?? 0) >= $fee,
            'default_payment_source' => (int) ($account->balance_faded ?? 0) >= $fee ? 'dim' : 'active',
            'policy_version_id' => $terms['policy_version_id'],
            'payment_contract_version' => 1,
            'payment_sources' => app(NajmBaharMembershipPaymentSources::class)->forAccount($account, $fee),
            'balance' => [
                'local' => $this->projectBalance($this->balances->local($account)),
                'aggregate' => $this->projectBalance($aggregate),
            ],
        ];
    }

    /**
     * @return array{account:Account,has_paid:bool,payment_year:int,fee_gol:int,payment_source:string,breakdown:array,balance:array}
     */
    public function pay(User $user, string $paymentSource, ?int $subAccountId = null, ?array $expected = null): array
    {
        $account = $this->mainAccount($user);
        if ($this->status->hasPaidCurrentMembershipFee($user)) {
            throw new NajmBaharMembershipFeeException(
                'already_paid',
                'The annual membership fee has already been paid for the current membership year.',
            );
        }

        $result = DB::transaction(function () use ($user, $account, $paymentSource, $subAccountId, $expected) {
            $lockedAccount = Account::query()
                ->whereKey((int) $account->id)
                ->where('user_id', (int) $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->status->hasPaidCurrentMembershipFee($user)) {
                throw new NajmBaharMembershipFeeException('already_paid', 'The membership fee has already been paid.');
            }

            $terms = $this->terms($user);
            $fee = $terms['fee_gol'];
            $split = $terms['split'];
            $paymentYear = $terms['payment_year'];
            $policyVersionId = $terms['policy_version_id'];
            if ($expected !== null) {
                foreach (['payment_year', 'fee_gol', 'policy_version_id'] as $key) {
                    if (! array_key_exists($key, $expected) || $expected[$key] !== $terms[$key]) {
                        $this->termsChanged();
                    }
                }
                $breakdown = $expected['breakdown'] ?? [];
                foreach ($split as $code => $amount) {
                    if (($breakdown[$code.'_gol'] ?? null) !== $amount) {
                        $this->termsChanged();
                    }
                }
                if ($paymentSource === 'dim' && $subAccountId !== null) {
                    throw new NajmBaharMembershipFeeException('payment_source_invalid', 'Dim payment requires the main account.', 422);
                }
            }

            $sourceAccountNumber = (string) $lockedAccount->account_number;

            if ($expected !== null) {
                $selectedNumber = $sourceAccountNumber;
                if ($paymentSource === 'active' && $subAccountId !== null) {
                    $selected = SubAccount::query()->whereKey($subAccountId)->where('account_id', $lockedAccount->id)
                        ->where('status', 1)->lockForUpdate()->firstOrFail();
                    $selectedNumber = (string) $selected->sub_account_code;
                }
                if (($expected['account_number'] ?? null) !== $selectedNumber) {
                    throw new NajmBaharMembershipFeeException('membership_fee_source_changed', 'The selected payment account has changed.');
                }
            }
            if ($this->status->membershipPaymentYear($user) !== $paymentYear) {
                $this->termsChanged();
            }

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
                    $expected !== null,
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

            if (! $this->status->hasPaidCurrentMembershipFee($user)) {
                throw new NajmBaharMembershipFeeException('membership_fee_incomplete', 'Membership payment evidence is incomplete.');
            }

            $this->awardParticipation($user, $paymentYear, $paymentSource, $policyVersionId);
            return $terms + ['payment_account_number' => $sourceAccountNumber];
        });

        $fee = $result['fee_gol'];
        $split = $result['split'];
        $paymentYear = $result['payment_year'];
        $account->refresh();

        return [
            'account' => $account,
            'has_paid' => $this->status->hasPaidCurrentMembershipFee($user),
            'payment_year' => $paymentYear,
            'fee_gol' => $fee,
            'payment_source' => $paymentSource,
            'payment_account_number' => $result['payment_account_number'],
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

    private function terms(User $user): array
    {
        $policy = $this->policy->current();
        $parameters = $policy['parameters'] ?? [];
        $fee = $this->fees->getMembershipFee($policy);
        $split = [
            TreasuryService::OPERATIONS_SALARY => max(0, (int) ($parameters['membership_operations_gol'] ?? BaharMoney::toGolFromBahar(6))),
            TreasuryService::CENTRAL_INSURANCE => max(0, (int) ($parameters['membership_insurance_gol'] ?? BaharMoney::toGolFromBahar(3))),
            TreasuryService::MONEY_DESTRUCTION => max(0, (int) ($parameters['membership_burn_gol'] ?? BaharMoney::toGolFromBahar(3))),
        ];
        $this->assertValidSplit($fee, $split);
        return ['fee_gol' => $fee, 'split' => $split, 'payment_year' => $this->status->membershipPaymentYear($user),
            'policy_version_id' => $policy['version_id'] === null ? null : (int) $policy['version_id']];
    }

    private function termsChanged(): never
    {
        throw new NajmBaharMembershipFeeException('membership_fee_terms_changed', 'Membership fee terms changed; review the current terms before paying.');
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

    private function resolveActiveSource(Account $main, int $fee, ?int $subAccountId, bool $strictMain = false): string
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

        if ($strictMain) {
            throw new NajmBaharMembershipFeeException('insufficient_available_funds', 'Available Active balance in the selected main account is insufficient.');
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
                    'trusted_canonical_subaccount_transfer' => true,
                    'membership_fee_total_gol' => array_sum($split),
                    'expected_breakdown_gol' => [
                        TreasuryService::OPERATIONS_SALARY.'_gol' => $split[TreasuryService::OPERATIONS_SALARY],
                        TreasuryService::CENTRAL_INSURANCE.'_gol' => $split[TreasuryService::CENTRAL_INSURANCE],
                        TreasuryService::MONEY_DESTRUCTION.'_gol' => $split[TreasuryService::MONEY_DESTRUCTION],
                    ],
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
