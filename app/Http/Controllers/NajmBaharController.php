<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\NajmBaharAgreement;
use App\Models\User;
use App\Models\UserExperience;
use App\Models\Address;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\TransactionService;
use App\Modules\NajmBahar\Services\FeeService;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use App\Models\InvitationCode;
use App\Models\Setting;
use App\Helpers\BaharMoney;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NajmBaharController extends Controller
{
    protected $accountService;
    protected $transactionService;
    protected $feeService;

    public function __construct(
        AccountService $accountService,
        TransactionService $transactionService,
        FeeService $feeService
    ) {
        $this->accountService = $accountService;
        $this->transactionService = $transactionService;
        $this->feeService = $feeService;
    }

    /**
     * نمایش صفحه توافقنامه نجم بهار
     */
    public function showAgreement()
    {
        $user = auth()->user();
        abort_if($user->isSystemIdentity(), 403, 'System identities cannot use Najm Bahar.');
        
        // بررسی اینکه آیا کاربر قبلاً حساب نجم بهار دارد یا نه
        $hasAcceptedAgreement = $this->accountService->hasMainAccount($user->id)
            || ! is_null($user->najm_bahar_agreement_accepted_at);

        // دریافت توافقنامه‌های نجم بهار
        $agreements = NajmBaharAgreement::whereNull('parent_id')
            ->with('descendants')
            ->orderBy('order')
            ->get();
        $hasExperience = UserExperience::where('user_id', $user->id)->exists();
        $hasAddress = Address::where('user_id', $user->id)->exists();
        $step1Complete = ($user->first_name && $user->last_name && $user->gender && $user->national_id && $user->phone);
        $isProfileComplete = $step1Complete && $hasExperience && $hasAddress;

        return view('najm-bahar.agreement', compact(
            'agreements',
            'isProfileComplete',
            'hasAcceptedAgreement'
        ));
    }

    /**
     * پردازش تایید توافقنامه و ایجاد حساب مالی
     */
    public function processAgreement(Request $request)
    {
        $request->validate([
            'agreement_accepted' => 'required|accepted'
        ], [
            'agreement_accepted.required' => 'لطفاً توافقنامه نجم بهار را بپذیرید',
            'agreement_accepted.accepted' => 'لطفاً توافقنامه نجم بهار را بپذیرید'
        ]);

        $user = auth()->user();
        abort_if($user->isSystemIdentity(), 403, 'System identities cannot use Najm Bahar.');
        
        // بررسی مجدد اینکه آیا کاربر قبلاً حساب دارد
        if ($this->accountService->hasMainAccount($user->id)) {
            return redirect()->route('najm-bahar.dashboard')
                ->with('info', 'شما قبلاً حساب نجم بهار دارید.');
        }

        try {
            DB::transaction(function () use ($user) {
                // 1. ایجاد حساب اصلی کاربر
                $userAccount = $this->accountService->createMainAccountForUser(
                    $user->id,
                    'حساب نجم بهار ' . $user->fullName()
                );

                // 2. واریز اولیه با تقسیم اکتیو/کمرنگ بر اساس تنظیمات
                $this->ensureInitialFundingAndMembershipFee($user, $userAccount);

                // حذف کسر خودکار حق عضویت - کاربر باید خودش با زدن دکمه پرداخت کند
                // $membershipFee = $this->distributeMembershipFee($userAccount->account_number, $user->id);

                // 3. پاداش معرف (در صورت وجود)
                $this->processReferralBonus($user, $userAccount);

                // 4. ثبت تاریخ پذیرش توافقنامه
                $user->update([
                    'najm_bahar_agreement_accepted_at' => now()
                ]);

                Log::info('NajmBahar account created successfully', [
                    'user_id' => $user->id,
                    'account_number' => $userAccount->account_number,
                ]);
            });

            return redirect()->route('najm-bahar.dashboard')
                ->with('success', 'حساب نجم بهار شما با موفقیت ایجاد شد! برای فعالسازی کامل، حق عضویت سالانه را پرداخت کنید.');

        } catch (\Exception $e) {
            Log::error('NajmBahar account creation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'خطا در ایجاد حساب نجم بهار. لطفاً مجدداً تلاش کنید.');
        }
    }

    /**
     * پردازش پاداش معرف
     */
    protected function processReferralBonus(User $user, $userAccount)
    {
        $invitationCheck = InvitationCode::where('used_by', $user->id)->first();
        
        if ($invitationCheck && $invitationCheck->user_id != 171) { // 171 = حساب سیستم
            $referrerAccount = $this->accountService->getMainAccountForUser($invitationCheck->user_id);
            
            if ($referrerAccount) {
                $bonusAmount = BaharMoney::toGolFromBahar(10); // 10 بهار پاداش معرف
                $bonusIdempotencyKey = 'referral-bonus-' . $user->id;
                
                $this->transactionService->transfer(
                    $userAccount->account_number,
                    $referrerAccount->account_number,
                    $bonusAmount,
                    'پاداش معرف - انتقال ۱۰ بهار جهت ریزمجموعه شدن کاربر جدید',
                    [
                        'type' => 'referral_bonus',
                        'referrer_id' => $invitationCheck->user_id,
                        'new_user_id' => $user->id,
                        'system_operation' => true,
                    ],
                    $bonusIdempotencyKey,
                    'faded',
                    'referral_bonus'
                );
            }
        }
    }

    /**
     * نمایش داشبورد نجم بهار
     */
    public function dashboard()
    {
        $user = auth()->user();
        $account = $this->accountService->getMainAccountForUser($user->id);
        
        if (!$account) {
            return redirect()->route('najm-bahar.agreement')
                ->with('info', 'ابتدا باید حساب نجم بهار خود را ایجاد کنید.');
        }

        $this->ensureInitialFundingAndMembershipFee($user, $account);

        $settings = Setting::firstNajmBaharSettings();
        $userCount = User::members()->count();
        $userThreshold = (int) ($settings?->najm_bahar_user_threshold ?? 1111111);
        $initialAmount = (int) ($settings?->najm_bahar_initial_amount ?? BaharMoney::toGolFromBahar(10000));
        $isThresholdMet = $userCount >= $userThreshold;
        $remainingUsers = max(0, $userThreshold - $userCount);
        $totalMinted = $userCount * $initialAmount;

        $membershipAccountCode = $settings?->najm_bahar_membership_fee_account ?? '0000000000-001';
        $membershipSubAccount = $this->accountService->getSystemSubAccountByCode($membershipAccountCode);
        $membershipBalance = (int) ($membershipSubAccount?->balance ?? 0);

        $accountIds = $this->transactionService->getUserAccountIds($user->id);
        $userTransactionsCount = empty($accountIds)
            ? 0
            : NajmTransaction::where(function ($query) use ($accountIds) {
                $query->whereIn('from_account_id', $accountIds)
                    ->orWhereIn('to_account_id', $accountIds);
            })->count();

        return view('najm-bahar.dashboard', compact(
            'account',
            'userCount',
            'userThreshold',
            'isThresholdMet',
            'remainingUsers',
            'totalMinted',
            'membershipAccountCode',
            'membershipBalance',
            'userTransactionsCount'
        ));
    }

    /**
     * نمایش کیف پول نجم بهار
     */
    public function wallet()
    {
        $user = auth()->user();
        $account = $this->accountService->getMainAccountForUser($user->id);
        
        if (!$account) {
            return redirect()->route('najm-bahar.agreement')
                ->with('info', 'ابتدا باید حساب نجم بهار خود را ایجاد کنید.');
        }

        $this->ensureInitialFundingAndMembershipFee($user, $account);

        // دریافت تراکنش‌های اخیر
        $recentTransactions = $this->transactionService->getUserTransactions($user->id, 10);
        $accountIds = $this->transactionService->getUserAccountIds($user->id);

        // دریافت امتیازات کاربر
        $userPoint = \App\Models\UserPoint::where('user_id', $user->id)->first();
        $totalPoints = $userPoint ? $userPoint->points : 0;
        $userLevel = $userPoint ? $userPoint->level : 'Bronze';

        // امتیازات نقد شده
        $cashedPoints = \App\Models\UserPointTransaction::where('user_id', $user->id)
            ->where('is_cashed', true)
            ->where('delta', '>', 0)
            ->sum('delta');

        // امتیازات قابل نقد
        $uncashedPoints = \App\Models\UserPointTransaction::where('user_id', $user->id)
            ->where('is_cashed', false)
            ->where('delta', '>', 0)
            ->sum('delta');

        return view('najm-bahar.wallet', compact(
            'account',
            'recentTransactions',
            'accountIds',
            'totalPoints',
            'userLevel',
            'cashedPoints',
            'uncashedPoints'
        ));
    }

    private function getInitialAmount(): int
    {
        $settings = Setting::firstNajmBaharSettings();

        return (int) ($settings?->najm_bahar_initial_amount ?? BaharMoney::toGolFromBahar(10000));
    }

    private function ensureInitialFundingAndMembershipFee(User $user, $account): void
    {
        $hasInitialFunding = NajmTransaction::where('to_account_id', $account->id)
            ->where('metadata->type', 'initial_funding')
            ->exists();

        if (! $hasInitialFunding) {
            $settings = Setting::firstNajmBaharSettings();
            $initialAmount = $this->getInitialAmount();
            
            // دریافت تنظیمات اکتیو/فیدد
            $activeType = $settings?->najm_bahar_initial_active_type ?? 'percentage';
            $activePercentage = (int) ($settings?->najm_bahar_initial_active_percentage ?? 30);
            $activeFixedAmount = (int) ($settings?->najm_bahar_initial_active_fixed_amount ?? 0);

            // محاسبه مقادیر برای metadata
            if ($activeType === 'fixed_amount') {
                $activeAmount = min($activeFixedAmount, $initialAmount);
            } else {
                $activeAmount = intval(($initialAmount * $activePercentage) / 100);
            }
            $fadedAmount = $initialAmount - $activeAmount;

            // واریز اولیه با تقسیم موجودی
            $updatedAccount = $this->transactionService->depositInitialFunding(
                $account->account_number,
                $initialAmount,
                $activePercentage,
                $activeFixedAmount,
                $activeType
            );

            // ثبت تراکنش برای auditing و idempotency
            NajmTransaction::create([
                'from_account_id' => null,
                'to_account_id' => $updatedAccount->id,
                'amount' => $initialAmount,
                'type' => 'immediate',
                'status' => 'completed',
                'metadata' => [
                    'type' => 'initial_funding',
                    'user_id' => $user->id,
                    'system_operation' => true,
                    'active_type' => $activeType,
                    'active_percentage' => $activePercentage,
                    'active_fixed_amount' => $activeFixedAmount,
                    'active_amount' => $activeAmount,
                    'faded_amount' => $fadedAmount,
                ],
                'description' => 'واریز اولیه جهت افتتاح حساب نجم بهار',
            ]);
        } elseif (intval($account->balance) > 0
            && intval($account->balance_active) === 0
            && intval($account->balance_faded) === 0
        ) {
            $settings = Setting::firstNajmBaharSettings();
            $activeType = $settings?->najm_bahar_initial_active_type ?? 'percentage';
            $activePercentage = (int) ($settings?->najm_bahar_initial_active_percentage ?? 30);
            $activeFixedAmount = (int) ($settings?->najm_bahar_initial_active_fixed_amount ?? 0);

            $initialAmount = intval($account->balance);
            if ($activeType === 'fixed_amount') {
                $activeAmount = min($activeFixedAmount, $initialAmount);
            } else {
                $activeAmount = intval(($initialAmount * $activePercentage) / 100);
            }
            $account->balance_active = $activeAmount;
            $account->balance_faded = $initialAmount - $activeAmount;
            $account->save();
        }

        // حذف پرداخت خودکار حق عضویت - کاربر باید خودش پرداخت کند
        // if (! $this->hasCompleteMembershipFeeSplits($account->id)) {
        //     $this->distributeMembershipFee($account->account_number, $user->id);
        // }
    }
    private function distributeMembershipFee(string $fromAccountNumber, int $userId): int
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

            $this->transactionService->transfer(
                $fromAccountNumber,
                $membershipAccount,
                $membershipFee,
                'پرداخت حق عضویت سالانه EarthCoop',
                ['type' => 'membership_fee', 'user_id' => $userId, 'split' => 'membership', 'system_operation' => true],
                'membership-fee-' . $userId . '-membership',
                'faded',
                'membership_fee'
            );

            return $membershipFee;
        }

        $transfers = [
            [$membershipAccount, $membershipAmount, 'membership'],
            [$insuranceAccount, $insuranceAmount, 'insurance'],
            [$burnAccount, $burnAmount, 'burn'],
        ];

        foreach ($transfers as [$targetAccount, $amount, $suffix]) {
            if ($amount <= 0) {
                continue;
            }

            $this->transactionService->transfer(
                $fromAccountNumber,
                $targetAccount,
                $amount,
                'پرداخت حق عضویت سالانه EarthCoop',
                ['type' => 'membership_fee', 'user_id' => $userId, 'split' => $suffix, 'system_operation' => true],
                'membership-fee-' . $userId . '-' . $suffix,
                'faded',
                'membership_fee'
            );
        }

        return $total;
    }

    private function hasCompleteMembershipFeeSplits(int $accountId): bool
    {
        $settings = Setting::firstNajmBaharSettings();
        $membershipAmount = (int) ($settings?->najm_bahar_membership_fee_membership_amount ?? BaharMoney::toGolFromBahar(6));
        $insuranceAmount = (int) ($settings?->najm_bahar_membership_fee_insurance_amount ?? BaharMoney::toGolFromBahar(3));
        $burnAmount = (int) ($settings?->najm_bahar_membership_fee_burn_amount ?? BaharMoney::toGolFromBahar(3));

        $checks = [
            ['membership', $membershipAmount],
            ['insurance', $insuranceAmount],
            ['burn', $burnAmount],
        ];

        foreach ($checks as [$split, $amount]) {
            if ($amount <= 0) {
                continue;
            }

            $exists = NajmTransaction::where('from_account_id', $accountId)
                ->where('metadata->type', 'membership_fee')
                ->where('metadata->split', $split)
                ->exists();

            if (! $exists) {
                return false;
            }
        }

        return true;
    }
}
