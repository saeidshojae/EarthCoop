<?php

namespace App\Http\Controllers;

use App\Helpers\BaharMoney;
use App\Models\Setting;
use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\FeeService;
use App\Modules\NajmBahar\Services\SubAccountService;
use App\Modules\NajmBahar\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NajmBaharMembershipFeeController extends Controller
{
    protected $transactionService;
    protected $accountService;
    protected $feeService;
    protected $subAccountService;

    public function __construct(
        TransactionService $transactionService,
        AccountService $accountService,
        FeeService $feeService,
        SubAccountService $subAccountService
    ) {
        $this->transactionService = $transactionService;
        $this->accountService = $accountService;
        $this->feeService = $feeService;
        $this->subAccountService = $subAccountService;
    }

    /**
     * API: دریافت اطلاعات حق عضویت برای نمایش در مدال
     */
    public function getInfo()
    {
        $user = Auth::user();
        $account = $this->accountService->getMainAccountForUser($user->id);

        if (!$account) {
            return response()->json(['error' => 'حساب نجم بهار یافت نشد'], 404);
        }

        $settings = Setting::firstNajmBaharSettings();
        $membershipFee = $this->feeService->getMembershipFee();

        // بررسی پرداخت قبلی برای سال جاری
        $hasPaid = $this->hasPaidCurrentYearMembershipFee($user->id, $account->id);

        // محاسبه سالگرد بعدی
        $membershipDate = $user->created_at;
        $currentYear = now()->year;
        $nextAnniversary = $membershipDate->copy()->setYear($currentYear);
        if (now()->greaterThanOrEqualTo($nextAnniversary)) {
            $nextAnniversary->addYear();
        }

        // محاسبه تقسیم‌بندی
        $membershipAmount = (int) ($settings?->najm_bahar_membership_fee_membership_amount ?? BaharMoney::toGolFromBahar(6));
        $insuranceAmount = (int) ($settings?->najm_bahar_membership_fee_insurance_amount ?? BaharMoney::toGolFromBahar(3));
        $burnAmount = (int) ($settings?->najm_bahar_membership_fee_burn_amount ?? BaharMoney::toGolFromBahar(3));

        $total = $membershipAmount + $insuranceAmount + $burnAmount;

        $subAccounts = SubAccount::where('account_id', $account->id)
            ->where('status', 1)
            ->orderBy('created_at')
            ->get();

        $defaultSubAccount = $subAccounts->first();
        $requiresSubAccount = $defaultSubAccount === null;
        $subAccountActiveBalance = $defaultSubAccount ? intval($defaultSubAccount->balance_active ?? 0) : 0;
        $mainActiveBalance = intval($account->balance_active ?? 0);

        // بررسی موجودی کافی در حساب فرعی
        $hasEnoughBalance = $subAccountActiveBalance >= $total;

        return response()->json([
            'has_paid' => $hasPaid,
            'total_fee' => $total,
            'total_fee_formatted' => BaharMoney::formatDecimal($total),
            'balance_active' => $subAccountActiveBalance,
            'balance_active_formatted' => BaharMoney::formatDecimal($subAccountActiveBalance),
            'main_active_balance' => $mainActiveBalance,
            'main_active_formatted' => BaharMoney::formatDecimal($mainActiveBalance),
            'has_enough_balance' => $hasEnoughBalance,
            'requires_sub_account' => $requiresSubAccount,
            'sub_account' => $defaultSubAccount ? [
                'id' => $defaultSubAccount->id,
                'code' => $defaultSubAccount->sub_account_code,
                'name' => $defaultSubAccount->name,
                'balance_active' => $subAccountActiveBalance,
                'balance_active_formatted' => BaharMoney::formatDecimal($subAccountActiveBalance),
            ] : null,
            'create_subaccount_url' => route('najm-bahar.sub-accounts.create'),
            'create_subaccount_store_url' => route('najm-bahar.sub-accounts.store'),
            'transfer_url' => route('najm-bahar.transfer'),
            'transfer_to_url' => $defaultSubAccount
                ? route('najm-bahar.sub-accounts.transfer-to', ['subAccount' => $defaultSubAccount->id])
                : null,
            'membership_date' => $membershipDate->format('Y-m-d'),
            'membership_date_formatted' => $membershipDate->locale('fa')->isoFormat('jYYYY/jMM/jDD'),
            'next_anniversary' => $nextAnniversary->format('Y-m-d'),
            'next_anniversary_formatted' => $nextAnniversary->locale('fa')->isoFormat('jYYYY/jMM/jDD'),
            'breakdown' => [
                [
                    'name' => 'حساب عضویت',
                    'account' => $settings?->najm_bahar_membership_fee_account ?? '0000000000-001',
                    'amount' => $membershipAmount,
                    'amount_formatted' => BaharMoney::formatDecimal($membershipAmount),
                ],
                [
                    'name' => 'حساب بیمه',
                    'account' => $settings?->najm_bahar_membership_fee_insurance_account ?? '0000000000-002',
                    'amount' => $insuranceAmount,
                    'amount_formatted' => BaharMoney::formatDecimal($insuranceAmount),
                ],
                [
                    'name' => 'حساب سوزاندن',
                    'account' => $settings?->najm_bahar_membership_fee_burn_account ?? '0000000000-000',
                    'amount' => $burnAmount,
                    'amount_formatted' => BaharMoney::formatDecimal($burnAmount),
                ],
            ],
        ]);
    }

    /**
     * پرداخت حق عضویت سالانه
     */
    public function pay(Request $request)
    {
        $user = Auth::user();
        $account = $this->accountService->getMainAccountForUser($user->id);

        if (!$account) {
            return back()->with('error', 'حساب نجم بهار یافت نشد');
        }

        // بررسی پرداخت قبلی برای سال جاری
        if ($this->hasPaidCurrentYearMembershipFee($user->id, $account->id)) {
            return back()->with('error', 'شما برای سال جاری حق عضویت سالانه را پرداخت کرده‌اید');
        }

        $subAccountId = $request->input('sub_account_id');
        $subAccount = null;
        if ($subAccountId) {
            $subAccount = SubAccount::where('id', $subAccountId)
                ->where('account_id', $account->id)
                ->where('status', 1)
                ->first();
        }

        if (! $subAccount) {
            $subAccount = SubAccount::where('account_id', $account->id)
                ->where('status', 1)
                ->orderBy('created_at')
                ->first();
        }

        if (! $subAccount) {
            return back()->with('error', 'برای پرداخت حق عضویت ابتدا یک حساب فرعی بسازید و موجودی فعال را به آن منتقل کنید.');
        }

        $this->accountService->ensureSubAccountAccount($subAccount);

        try {
            $totalPaid = $this->distributeMembershipFee($subAccount, $user->id);

            return redirect()->route('najm-bahar.dashboard')
                ->with('success', 'حق عضویت سالانه با موفقیت پرداخت شد. مبلغ: ' . BaharMoney::formatDecimal($totalPaid) . ' بهار');
                
        } catch (\Exception $e) {
            Log::error('NajmBahar membership fee payment failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'خطا در پرداخت حق عضویت: ' . $e->getMessage());
        }
    }

    /**
     * توزیع حق عضویت به حساب‌های سیستمی
     */
    private function distributeMembershipFee(SubAccount $fromSubAccount, int $userId): int
    {
        $settings = Setting::firstNajmBaharSettings();
        $systemAccount = $this->accountService->getSystemAccount();
        $this->accountService->ensureDefaultSystemSubAccounts($systemAccount);

        $membershipFee = $this->feeService->getMembershipFee();

        $membershipAccount = $settings?->najm_bahar_membership_fee_account ?? '0000000000-001';
        $insuranceAccount = $settings?->najm_bahar_membership_fee_insurance_account ?? '0000000000-002';
        $burnAccount = $settings?->najm_bahar_membership_fee_burn_account ?? '0000000000-000';

        $membershipAmount = (int) ($settings?->najm_bahar_membership_fee_membership_amount ?? BaharMoney::toGolFromBahar(6));
        $insuranceAmount = (int) ($settings?->najm_bahar_membership_fee_insurance_amount ?? BaharMoney::toGolFromBahar(3));
        $burnAmount = (int) ($settings?->najm_bahar_membership_fee_burn_amount ?? BaharMoney::toGolFromBahar(3));

        $total = $membershipAmount + $insuranceAmount + $burnAmount;

        if ($total <= 0 || $total !== $membershipFee) {
            Log::warning('NajmBahar membership split mismatch; falling back to membership account.', [
                'user_id' => $userId,
                'split_total' => $total,
                'membership_fee' => $membershipFee,
            ]);

            $currentYear = now()->year;
            $this->transactionService->transfer(
                $fromSubAccount->sub_account_code,
                $membershipAccount,
                $membershipFee,
                'پرداخت حق عضویت سالانه EarthCoop',
                ['type' => 'membership_fee', 'user_id' => $userId, 'split' => 'membership', 'user_initiated' => true, 'payment_year' => $currentYear],
                'membership-fee-' . $userId . '-membership-' . $currentYear,
                'active',
                'membership_fee'
            );

            return $membershipFee;
        }

        $transfers = [
            [$membershipAccount, $membershipAmount, 'membership'],
            [$insuranceAccount, $insuranceAmount, 'insurance'],
            [$burnAccount, $burnAmount, 'burn'],
        ];

        $currentYear = now()->year;
        foreach ($transfers as [$targetAccount, $amount, $suffix]) {
            if ($amount <= 0) {
                continue;
            }

            $this->transactionService->transfer(
                $fromSubAccount->sub_account_code,
                $targetAccount,
                $amount,
                'پرداخت حق عضویت سالانه EarthCoop',
                ['type' => 'membership_fee', 'user_id' => $userId, 'split' => $suffix, 'user_initiated' => true, 'payment_year' => $currentYear],
                'membership-fee-' . $userId . '-' . $suffix . '-' . $currentYear,
                'active',
                'membership_fee'
            );
        }

        return $total;
    }

    /**
     * بررسی پرداخت حق عضویت برای سال جاری (بر اساس سالگرد عضویت)
     */
    private function hasPaidCurrentYearMembershipFee(int $userId, int $accountId): bool
    {
        $user = User::find($userId);
        if (!$user) {
            return false;
        }

        // محاسبه سالگرد عضویت کاربر برای سال جاری
        $membershipDate = $user->created_at;
        $currentYear = now()->year;
        $currentAnniversary = $membershipDate->copy()->setYear($currentYear);
        
        // اگر سالگرد امسال هنوز نیامده، یعنی باید برای سال قبل چک کنیم
        if (now()->lessThan($currentAnniversary)) {
            $currentYear = $currentYear - 1;
        }

        // بررسی اینکه آیا برای این سال پرداخت شده
        $expected = ['membership', 'insurance', 'burn'];
        
        $actual = NajmTransaction::where('metadata->type', 'membership_fee')
            ->where('metadata->user_id', $userId)
            ->where('metadata->payment_year', $currentYear)
            ->pluck('metadata')
            ->map(fn ($m) => $m['split'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        sort($expected);
        sort($actual);

        return $expected === $actual;
    }

    /**
     * بررسی پرداخت کامل حق عضویت (قدیمی - برای سازگاری)
     */
    private function hasCompleteMembershipFeeSplits(int $accountId): bool
    {
        $expected = ['membership', 'insurance', 'burn'];
        
        $actual = NajmTransaction::where('from_account_id', $accountId)
            ->where('metadata->type', 'membership_fee')
            ->pluck('metadata')
            ->map(fn ($m) => $m['split'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        sort($expected);
        sort($actual);

        return $expected === $actual;
    }
}
