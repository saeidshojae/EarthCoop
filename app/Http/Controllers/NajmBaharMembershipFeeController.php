<?php

namespace App\Http\Controllers;

use App\Helpers\BaharMoney;
use App\Models\User;
use App\Models\UserPointTransaction;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Services\AccountBalanceService;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\FeeService;
use App\Modules\NajmBahar\Services\MonetaryPolicyService;
use App\Modules\NajmBahar\Services\MonetaryService;
use App\Modules\NajmBahar\Services\TransactionService;
use App\Modules\NajmBahar\Services\TreasuryService;
use App\Services\MembershipFeeStatusService;
use App\Services\ReputationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NajmBaharMembershipFeeController extends Controller
{
    public function __construct(
        protected TransactionService $transactionService,
        protected AccountService $accountService,
        protected AccountBalanceService $balanceService,
        protected FeeService $feeService,
        protected MonetaryService $monetaryService,
        protected MonetaryPolicyService $monetaryPolicy,
        protected TreasuryService $treasuryService,
        protected ReputationService $reputationService,
        protected MembershipFeeStatusService $membershipFeeStatus
    ) {
    }

    public function getInfo()
    {
        $user = Auth::user();
        $account = $this->accountService->getMainAccountForUser($user->id);

        if (! $account) {
            return response()->json(['error' => 'حساب نجم بهار یافت نشد'], 404);
        }

        $hasPaid = $this->membershipFeeStatus->hasPaidCurrentMembershipFee($user);
        $wallet = $this->balanceService->aggregate($account);

        $membershipDate = $user->created_at;
        $currentYear = now()->year;
        $nextAnniversary = $membershipDate->copy()->setYear($currentYear);
        if (now()->greaterThanOrEqualTo($nextAnniversary)) {
            $nextAnniversary->addYear();
        }

        [$operationsAmount, $insuranceAmount, $burnAmount] = $this->membershipSplit();
        $total = $this->membershipFeeAmount();

        $subAccounts = SubAccount::where('account_id', $account->id)
            ->where('status', 1)
            ->orderBy('created_at')
            ->get();

        $mainActive = (int) ($account->balance_active ?? 0);
        $mainDim = (int) ($account->balance_faded ?? 0);
        $canPayFromDim = $mainDim >= $total;
        $canPayFromActive = (int) $wallet['active'] >= $total;

        $defaultSubAccount = $subAccounts->first(fn ($sub) => (int) ($sub->balance_active ?? 0) >= $total)
            ?? $subAccounts->first();
        $defaultSubActive = (int) ($defaultSubAccount?->balance_active ?? 0);
        $hasEnoughBalance = $canPayFromDim || $mainActive >= $total || $defaultSubActive >= $total;
        $requiresSubAccount = ! $canPayFromDim && $mainActive < $total && $defaultSubAccount === null;

        $funds = $this->treasuryService->ensureDefaultFunds();

        return response()->json([
            'has_paid' => $hasPaid,
            'total_fee' => $total,
            'total_fee_formatted' => BaharMoney::formatDecimal($total),
            'balance_dim' => (int) $wallet['dim'],
            'balance_dim_formatted' => BaharMoney::formatDecimal((int) $wallet['dim']),
            'balance_active' => (int) $wallet['active'],
            'balance_active_formatted' => BaharMoney::formatDecimal((int) $wallet['active']),
            'wallet_total' => (int) $wallet['total'],
            'wallet_total_formatted' => BaharMoney::formatDecimal((int) $wallet['total']),
            'can_pay_from_dim' => $canPayFromDim,
            'can_pay_from_active' => $canPayFromActive,
            'default_payment_source' => $canPayFromDim ? 'dim' : 'active',
            'payment_source_required' => true,
            'policy_version_id' => $this->monetaryPolicy->versionId(),
            'has_enough_balance' => $hasEnoughBalance,
            'requires_sub_account' => $requiresSubAccount,
            'main_active_balance' => $mainActive,
            'main_active_formatted' => BaharMoney::formatDecimal($mainActive),
            'sub_account' => $defaultSubAccount ? [
                'id' => $defaultSubAccount->id,
                'code' => $defaultSubAccount->sub_account_code,
                'name' => $defaultSubAccount->name,
                'balance_active' => $defaultSubActive,
                'balance_active_formatted' => BaharMoney::formatDecimal($defaultSubActive),
            ] : null,
            'create_subaccount_url' => route('najm-bahar.sub-accounts.create'),
            'create_subaccount_store_url' => route('najm-bahar.sub-accounts.store'),
            'transfer_url' => route('najm-bahar.transfer'),
            'transfer_to_url' => $defaultSubAccount
                ? route('najm-bahar.sub-accounts.transfer-to', ['subAccount' => $defaultSubAccount->id])
                : null,
            'sub_accounts' => $subAccounts->map(fn ($sub) => [
                'id' => $sub->id,
                'code' => $sub->sub_account_code,
                'name' => $sub->name,
                'balance_active' => (int) ($sub->balance_active ?? 0),
                'balance_active_formatted' => BaharMoney::formatDecimal((int) ($sub->balance_active ?? 0)),
            ])->values(),
            'membership_date' => $membershipDate->format('Y-m-d'),
            'membership_date_formatted' => $membershipDate->locale('fa')->isoFormat('jYYYY/jMM/jDD'),
            'next_anniversary' => $nextAnniversary->format('Y-m-d'),
            'next_anniversary_formatted' => $nextAnniversary->locale('fa')->isoFormat('jYYYY/jMM/jDD'),
            'breakdown' => [
                [
                    'name' => 'صندوق حقوق و هزینه‌ها',
                    'account' => $funds[TreasuryService::OPERATIONS_SALARY]->account->account_number,
                    'amount' => $operationsAmount,
                    'amount_formatted' => BaharMoney::formatDecimal($operationsAmount),
                ],
                [
                    'name' => 'صندوق بیمه مرکزی',
                    'account' => $funds[TreasuryService::CENTRAL_INSURANCE]->account->account_number,
                    'amount' => $insuranceAmount,
                    'amount_formatted' => BaharMoney::formatDecimal($insuranceAmount),
                ],
                [
                    'name' => 'صندوق امحای پول',
                    'account' => $funds[TreasuryService::MONEY_DESTRUCTION]->account->account_number,
                    'amount' => $burnAmount,
                    'amount_formatted' => BaharMoney::formatDecimal($burnAmount),
                ],
            ],
        ]);
    }

    public function pay(Request $request)
    {
        $validated = $request->validate([
            'payment_source' => 'required|in:dim,active',
            'sub_account_id' => 'nullable|integer',
        ], [
            'payment_source.required' => 'منبع پرداخت حق عضویت را مشخص کنید.',
            'payment_source.in' => 'منبع پرداخت حق عضویت معتبر نیست.',
        ]);

        $user = Auth::user();
        $account = $this->accountService->getMainAccountForUser($user->id);
        if (! $account) {
            return back()->with('error', 'حساب نجم بهار یافت نشد');
        }

        if ($this->membershipFeeStatus->hasPaidCurrentMembershipFee($user)) {
            return back()->with('error', 'شما برای سال جاری حق عضویت سالانه را پرداخت کرده‌اید');
        }

        [$operationsAmount, $insuranceAmount, $burnAmount] = $this->membershipSplit();
        $total = $this->membershipFeeAmount();
        $currentYear = $this->membershipFeeStatus->membershipPaymentYear($user);
        $policyVersionId = $this->monetaryPolicy->versionId();
        $paymentSource = $validated['payment_source'];

        try {
            DB::transaction(function () use (
                $user,
                $account,
                $paymentSource,
                $validated,
                $currentYear,
                $total,
                $operationsAmount,
                $insuranceAmount,
                $burnAmount,
                $policyVersionId
            ) {
                $sourceAccountNumber = $account->account_number;

                if ($paymentSource === 'dim') {
                    $this->monetaryService->activateDim(
                        $account,
                        $total,
                        'فعال‌سازی حق عضویت سالانه EarthCoop',
                        [
                            'type' => 'membership_fee_activation',
                            'user_id' => $user->id,
                            'payment_year' => $currentYear,
                            'policy_version_id' => $policyVersionId,
                        ],
                        'membership-fee-activation-' . $user->id . '-' . $currentYear,
                        false
                    );
                } else {
                    $subAccount = null;
                    if (! empty($validated['sub_account_id'])) {
                        $subAccount = SubAccount::where('id', $validated['sub_account_id'])
                            ->where('account_id', $account->id)
                            ->where('status', 1)
                            ->first();
                    }

                    if ($subAccount) {
                        if ((int) ($subAccount->balance_active ?? 0) < $total) {
                            throw new \RuntimeException('موجودی فعال حساب فرعی برای پرداخت حق عضویت کافی نیست.');
                        }
                        $this->accountService->ensureSubAccountAccount($subAccount);
                        $sourceAccountNumber = $subAccount->sub_account_code;
                    } elseif ((int) ($account->balance_active ?? 0) < $total) {
                        $subAccount = SubAccount::where('account_id', $account->id)
                            ->where('status', 1)
                            ->where('balance_active', '>=', $total)
                            ->orderBy('created_at')
                            ->first();

                        if (! $subAccount) {
                            throw new \RuntimeException('موجودی فعال برای پرداخت حق عضویت کافی نیست.');
                        }

                        $this->accountService->ensureSubAccountAccount($subAccount);
                        $sourceAccountNumber = $subAccount->sub_account_code;
                    }
                }

                $this->distributeMembershipFee(
                    $sourceAccountNumber,
                    $user->id,
                    $currentYear,
                    $operationsAmount,
                    $insuranceAmount,
                    $burnAmount,
                    $paymentSource,
                    $policyVersionId
                );

                $this->awardMembershipFeeParticipation($user, $currentYear, $paymentSource, $policyVersionId);
            });

            return redirect()->route('najm-bahar.dashboard')
                ->with('success', 'حق عضویت سالانه با موفقیت پرداخت شد. مبلغ: ' . BaharMoney::formatDecimal($total) . ' بهار');
        } catch (\Exception $e) {
            Log::error('NajmBahar membership fee payment failed', [
                'user_id' => $user->id,
                'payment_source' => $paymentSource,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'خطا در پرداخت حق عضویت: ' . $e->getMessage());
        }
    }

    private function awardMembershipFeeParticipation(User $user, int $paymentYear, string $paymentSource, ?int $policyVersionId): void
    {
        $alreadyAwarded = UserPointTransaction::where('user_id', $user->id)
            ->where('action', 'membership_fee_paid')
            ->where('reference_id', $paymentYear)
            ->exists();

        if ($alreadyAwarded) {
            return;
        }

        $this->reputationService->applyAction(
            $user,
            'membership_fee_paid',
            [
                'payment_year' => $paymentYear,
                'payment_source' => $paymentSource,
                'policy_version_id' => $policyVersionId,
            ],
            $paymentYear,
            'najm_bahar_membership'
        );
    }

    private function distributeMembershipFee(
        string $sourceAccountNumber,
        int $userId,
        int $paymentYear,
        int $operationsAmount,
        int $insuranceAmount,
        int $burnAmount,
        string $paymentSource,
        ?int $policyVersionId
    ): int {
        $membershipFee = $this->membershipFeeAmount();
        $total = $operationsAmount + $insuranceAmount + $burnAmount;
        $funds = $this->treasuryService->ensureDefaultFunds();

        $transfers = $total === $membershipFee && $total > 0
            ? [
                [$funds[TreasuryService::OPERATIONS_SALARY]->account->account_number, $operationsAmount, 'operations_salary'],
                [$funds[TreasuryService::CENTRAL_INSURANCE]->account->account_number, $insuranceAmount, 'central_insurance'],
                [$funds[TreasuryService::MONEY_DESTRUCTION]->account->account_number, $burnAmount, 'money_destruction'],
            ]
            : [
                [$funds[TreasuryService::OPERATIONS_SALARY]->account->account_number, $membershipFee, 'operations_salary'],
            ];

        if ($total !== $membershipFee) {
            Log::warning('NajmBahar membership split mismatch; falling back to operations/salary fund.', [
                'user_id' => $userId,
                'split_total' => $total,
                'membership_fee' => $membershipFee,
                'policy_version_id' => $policyVersionId,
            ]);
        }

        foreach ($transfers as [$targetAccount, $amount, $suffix]) {
            if ($amount <= 0) {
                continue;
            }

            $this->transactionService->transfer(
                $sourceAccountNumber,
                $targetAccount,
                $amount,
                'پرداخت حق عضویت سالانه EarthCoop',
                [
                    'type' => 'membership_fee',
                    'user_id' => $userId,
                    'split' => $suffix,
                    'user_initiated' => true,
                    'system_operation' => true,
                    'payment_source' => $paymentSource,
                    'payment_year' => $paymentYear,
                    'policy_version_id' => $policyVersionId,
                ],
                'membership-fee-' . $userId . '-' . $suffix . '-' . $paymentYear,
                'active',
                'membership_fee'
            );
        }

        return $membershipFee;
    }

    private function membershipSplit(): array
    {
        return [
            max(0, (int) $this->monetaryPolicy->parameter('membership_operations_gol', BaharMoney::toGolFromBahar(6))),
            max(0, (int) $this->monetaryPolicy->parameter('membership_insurance_gol', BaharMoney::toGolFromBahar(3))),
            max(0, (int) $this->monetaryPolicy->parameter('membership_burn_gol', BaharMoney::toGolFromBahar(3))),
        ];
    }

    private function membershipFeeAmount(): int
    {
        $policyAmount = (int) $this->monetaryPolicy->parameter('membership_fee_gol', 0);
        return $policyAmount > 0 ? $policyAmount : $this->feeService->getMembershipFee();
    }
}
