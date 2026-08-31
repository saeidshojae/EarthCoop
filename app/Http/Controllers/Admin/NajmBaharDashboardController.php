<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\Fee;
use App\Modules\NajmBahar\Models\ScheduledTransaction;
use App\Modules\NajmBahar\Policy\NajmBaharConstitution;
use App\Models\AdminActionLog;
use App\Models\Setting;
use App\Models\User;
use App\Helpers\BaharMoney;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Http\Request;

class NajmBaharDashboardController extends Controller
{
    private const DEFAULT_USER_THRESHOLD = 1111111;

    /**
     * نمایش Dashboard ادمین
     */
    public function index()
    {
        $settings = Setting::firstNajmBaharSettings();
        $userThreshold = (int) ($settings->najm_bahar_user_threshold ?? self::DEFAULT_USER_THRESHOLD);
        $initialAmount = NajmBaharConstitution::initialMembershipGol();
        $userCount = User::members()->count();
        $isThresholdMet = $userCount >= $userThreshold;
        $totalMinted = $userCount * $initialAmount;
        $activeUserIds = User::members()->select('id');

        // آمار کلی
        $stats = [
            'total_accounts' => Account::where('type', 'user')->whereIn('user_id', $activeUserIds)->count(),
            'total_transactions' => Transaction::where('status', 'completed')->count(),
            'total_balance' => Account::where('type', 'user')->whereIn('user_id', $activeUserIds)->sum('balance'),
            'total_minted' => $totalMinted,
            'total_sub_accounts' => SubAccount::where('status', 1)->count(),
            'active_fees' => Fee::where('is_active', true)->count(),
            'pending_scheduled' => ScheduledTransaction::where('status', 'scheduled')->count(),
        ];

        // تراکنش‌های امروز
        $todayTransactions = Transaction::whereDate('created_at', today())
            ->where('status', 'completed')
            ->count();
        
        $todayVolume = Transaction::whereDate('created_at', today())
            ->where('status', 'completed')
            ->sum('amount');

        // تراکنش‌های هفته گذشته
        $weekTransactions = Transaction::where('created_at', '>=', Carbon::now()->subWeek())
            ->where('status', 'completed')
            ->count();
        
        $weekVolume = Transaction::where('created_at', '>=', Carbon::now()->subWeek())
            ->where('status', 'completed')
            ->sum('amount');

        // نمودار تراکنش‌های 30 روز گذشته
        $dailyTransactions = Transaction::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as volume')
            )
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->where('status', 'completed')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // تراکنش‌های اخیر
        $recentTransactions = Transaction::with(['fromAccount', 'toAccount'])
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // توزیع نوع تراکنش‌ها
        $transactionTypes = Transaction::select(
                'type',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as volume')
            )
            ->where('status', 'completed')
            ->groupBy('type')
            ->get();

        // حساب‌های با بیشترین موجودی
        $topAccounts = Account::where('type', 'user')
            ->whereIn('user_id', $activeUserIds)
            ->orderBy('balance', 'desc')
            ->limit(10)
            ->get();

        $adminLogs = AdminActionLog::with('adminUser')
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        $membershipSplit = [
            'membership_account' => $settings->najm_bahar_membership_fee_account ?? '0000000000-001',
            'insurance_account' => $settings->najm_bahar_membership_fee_insurance_account ?? '0000000000-002',
            'burn_account' => $settings->najm_bahar_membership_fee_burn_account ?? '0000000000-000',
            'membership_fee_amount' => (int) ($settings->najm_bahar_membership_fee_amount ?? BaharMoney::toGolFromBahar(12)),
            'membership_amount' => (int) ($settings->najm_bahar_membership_fee_membership_amount ?? BaharMoney::toGolFromBahar(6)),
            'insurance_amount' => (int) ($settings->najm_bahar_membership_fee_insurance_amount ?? BaharMoney::toGolFromBahar(3)),
            'burn_amount' => (int) ($settings->najm_bahar_membership_fee_burn_amount ?? BaharMoney::toGolFromBahar(3)),
        ];

        return view('admin.najm-bahar.dashboard', compact(
            'userThreshold',
            'initialAmount',
            'userCount',
            'isThresholdMet',
            'totalMinted',
            'stats',
            'todayTransactions',
            'todayVolume',
            'weekTransactions',
            'weekVolume',
            'dailyTransactions',
            'recentTransactions',
            'transactionTypes',
            'topAccounts',
            'adminLogs',
            'membershipSplit'
        ));
    }

    /**
     * بروزرسانی حدنصاب کاربران برای باز شدن تراکنش‌ها
     */
    public function updateUserThreshold(Request $request)
    {
        $validated = $request->validate([
            'najm_bahar_user_threshold' => 'required|integer|min:1',
        ]);

        $settings = Setting::firstNajmBaharSettings();
        $settings->najm_bahar_user_threshold = (int) $validated['najm_bahar_user_threshold'];
        $settings->save();

        return redirect()->route('admin.najm-bahar.dashboard')
            ->with('success', 'حدنصاب کاربران نجم بهار بروزرسانی شد.');
    }

    /**
     * مبلغ صدور اولیه عضو یک invariant قانون اساسی نجم بهار است و از پنل ادمین قابل تغییر نیست.
     * endpoint قدیمی عمداً برای سازگاری فرم‌های موجود باقی می‌ماند اما هیچ state مالی را تغییر نمی‌دهد.
     */
    public function updateInitialAmount(Request $request)
    {
        return redirect()->route('admin.najm-bahar.dashboard')
            ->with('info', 'مبلغ صدور اولیه هر عضو طبق قانون اساسی نجم بهار ثابت و غیرقابل تغییر است.');
    }

    /**
     * بروزرسانی نحوه تقسیم حق عضویت
     */
    public function updateMembershipSplit(Request $request)
    {
        $validated = $request->validate([
            'najm_bahar_membership_fee_account' => 'required|string',
            'najm_bahar_membership_fee_insurance_account' => 'required|string',
            'najm_bahar_membership_fee_burn_account' => 'required|string',
            'najm_bahar_membership_fee_amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'najm_bahar_membership_fee_membership_amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'najm_bahar_membership_fee_insurance_amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'najm_bahar_membership_fee_burn_amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
        ]);

        $membershipFeeAmount = BaharMoney::parseToGol($validated['najm_bahar_membership_fee_amount']);
        $membershipAmount = BaharMoney::parseToGol($validated['najm_bahar_membership_fee_membership_amount']);
        $insuranceAmount = BaharMoney::parseToGol($validated['najm_bahar_membership_fee_insurance_amount']);
        $burnAmount = BaharMoney::parseToGol($validated['najm_bahar_membership_fee_burn_amount']);

        $total = $membershipAmount + $insuranceAmount + $burnAmount;

        if ($total !== $membershipFeeAmount) {
            return redirect()->route('admin.najm-bahar.dashboard')
                ->with('error', 'مجموع مبالغ تقسیم باید برابر مبلغ حق عضویت باشد.');
        }

        if ($total <= 0) {
            return redirect()->route('admin.najm-bahar.dashboard')
                ->with('error', 'مجموع مبالغ تقسیم حق عضویت باید بزرگتر از صفر باشد.');
        }

        $validated['najm_bahar_membership_fee_amount'] = $membershipFeeAmount;
        $validated['najm_bahar_membership_fee_membership_amount'] = $membershipAmount;
        $validated['najm_bahar_membership_fee_insurance_amount'] = $insuranceAmount;
        $validated['najm_bahar_membership_fee_burn_amount'] = $burnAmount;

        $settings = Setting::firstNajmBaharSettings();
        $settings->fill($validated);
        $settings->save();

        return redirect()->route('admin.najm-bahar.dashboard')
            ->with('success', 'تنظیمات تقسیم حق عضویت بروزرسانی شد.');
    }
}

