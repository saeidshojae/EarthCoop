<?php

namespace App\Http\Controllers;

use App\Helpers\BaharMoney;
use App\Models\Setting;
use App\Models\User;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\FeeService;
use App\Modules\NajmBahar\Services\MonetaryService;
use App\Modules\NajmBahar\Services\TransactionService;
use App\Modules\NajmBahar\Services\TreasuryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NajmBaharMembershipFeeController extends Controller
{
    public function __construct(
        protected TransactionService $transactionService,
        protected AccountService $accountService,
        protected FeeService $feeService,
        protected MonetaryService $monetaryService,
        protected TreasuryService $treasuryService
    ) {
    }

    public function getInfo()
    {
        $user = Auth::user();
        $account = $this->accountService->getMainAccountForUser($user->id);

        if (! $account) {
            return response()->json(['error' => 'حساب نجم بهار یافت نشد'], 404);
        }

        $settings = Setting::firstNajmBaharSettings();
        $membershipFee = $this->feeService->getMembershipFee();
        $hasPaid = $this->hasPaidCurrentYearMembershipFee($user->id, $account->id);

        $membershipDate = $user->created_at;
        $currentYear = now()->year;
        $nextAnniversary = $membershipDate->copy()->setYear($currentYear);
        if (now()->greaterThanOrEqualTo($nextAnniversary)) {
            $nextAnniversary->addYear();
        }

        [$operationsAmount, $insuranceAmount, $burnAmount] = $this->membershipSplit($settings);
        $total = $operationsAmount + $insuranceAmount + $burnAmount;

        $subAccounts = SubAccount::where('account_id', $account->id)
            ->where('status', 1)
            ->orderBy('created_at')
            ->get();
        $defaultSubAccount = $subAccounts->first();

        $funds = $this->treasuryService->ensureDefaultFunds();

        return response()->json([
            'has_paid' => $hasPaid,
            'total_fee' => $total,
            'total_fee_formatted' => BaharMoney::formatDecimal($total),
            'balance_dim' => (int) ($account->balance_faded ?? 0),
            'balance_dim_formatted' => BaharMoney::formatDecimal((int) ($account->balance_faded ?? 0)),
            'balance_active' => (int) ($account->balance_active ?? 0),
            'balance_active_formatted' => BaharMoney::formatDecimal((int) ($account->balance_active ?? 0)),
            'can_pay_from_dim' => (int) ($account->balance_faded ?? 0) >= $total,
            'can_pay_from_active' => (int) ($account->balance_active ?? 0) >= $total
                || $subAccounts->contains(fn ($sub) => (int) ($sub->balance_active ?? 0) >= $total),
            'default_payment_source' => (int) ($account->balance_faded ?? 0) >= $total ? 'dim' : 'active',
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
            'payment_source' => 'nullable|in:dim,active',
            'sub_account_id' => 'nullable|integer',
        ]);

        $user = Auth::user();
        $account = $this->accountService->getMainAccountForUser($user->id);
        if (! $account) {
            return back()->with('error', 'حساب نجم بهار یافت نشد');
        }

        if ($this->hasPaidCurrentYearMembershipFee($user->id, $account->id)) {
            return back()->with('error', 'شما برای سال جاری حق عضویت سالانه را پرداخت کرده‌اید');
        }

        $settings = Setting::firstNajmBaharSettings();
        [$operationsAmount, $insuranceAmount, $burnAmount] = $this->membershipSplit($settings);
        $total = $operationsAmount + $insuranceAmount + $burnAmount;
        $currentYear = $this->membershipPaymentYear($user);

        $paymentSource = $validated['payment_source']
            ?? ((int) ($account->balance_faded ?? 0) >= $total ? 'dim' : 'active');

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
                $burnAmount
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
                        throw new \RuntimeException('موجودی فعال برای پرداخت حق عضویت کافی نیست.');
                    }
                }

                $this->distributeMembershipFee(
                    $sourceAccountNumber,
                    $user->id,
                    $currentYear,
                    $operationsAmount,
                    $insuranceAmount,
                    $burnAmount,
                    $paymentSource
                );
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

    private function distributeMembershipFee(
        string $sourceAccountNumber,
        int $userId,
        int $paymentYear,
        int $operationsAmount,
        int $insuranceAmount,
        int $burnAmount,
        string $paymentSource
    ): int {
        $membershipFee = $this->feeService->getMembershipFee();
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
                ],
                'membership-fee-' . $userId . '-' . $suffix . '-' . $paymentYear,
                'active',
                'membership_fee'
            );
        }

        return $membershipFee;
    }

    private function membershipSplit(?Setting $settings): array
    {
        return [
            (int) ($settings?->najm_bahar_membership_fee_membership_amount ?? BaharMoney::toGolFromBahar(6)),
            (int) ($settings?->najm_bahar_membership_fee_insurance_amount ?? BaharMoney::toGolFromBahar(3)),
            (int) ($settings?->najm_bahar_membership_fee_burn_amount ?? BaharMoney::toGolFromBahar(3)),
        ];
    }

    private function membershipPaymentYear(User $user): int
    {
        $currentYear = now()->year;
        $currentAnniversary = $user->created_at->copy()->setYear($currentYear);

        return now()->lessThan($currentAnniversary) ? $currentYear - 1 : $currentYear;
    }

    private function hasPaidCurrentYearMembershipFee(int $userId, int $accountId): bool
    {
        $user = User::find($userId);
        if (! $user) {
            return false;
        }

        $paymentYear = $this->membershipPaymentYear($user);
        $expected = ['operations_salary', 'central_insurance', 'money_destruction'];

        $actual = NajmTransaction::where('metadata->type', 'membership_fee')
            ->where('metadata->user_id', $userId)
            ->where('metadata->payment_year', $paymentYear)
            ->pluck('metadata')
            ->map(fn ($m) => $m['split'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        // Legacy split names remain valid for already-paid historical years.
        $legacy = ['membership', 'insurance', 'burn'];
        sort($expected);
        sort($legacy);
        sort($actual);

        return $expected === $actual || $legacy === $actual;
    }
}
