<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\UserPoint;
use App\Models\UserPointTransaction;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReputationConversionController extends Controller
{
    protected $accountService;
    protected $transactionService;

    public function __construct(
        AccountService $accountService,
        TransactionService $transactionService
    ) {
        $this->accountService = $accountService;
        $this->transactionService = $transactionService;
    }

    /**
     * API: دریافت اطلاعات امتیازات قابل نقد
     */
    public function getInfo()
    {
        $user = Auth::user();
        $settings = Setting::firstNajmBaharSettings();

        // بررسی فعال بودن تبدیل امتیاز
        if (!$settings->reputation_conversion_enabled) {
            return response()->json(['error' => 'تبدیل امتیاز به پول فعلاً غیرفعال است'], 403);
        }

        $account = $this->accountService->getMainAccountForUser($user->id);
        if (!$account) {
            return response()->json(['error' => 'حساب نجم بهار یافت نشد'], 404);
        }

        // دریافت مجموع امتیازات
        $userPoint = UserPoint::where('user_id', $user->id)->first();
        $totalPoints = $userPoint ? $userPoint->points : 0;

        // امتیازات نقد شده (با delta مثبت)
        $cashedPoints = UserPointTransaction::where('user_id', $user->id)
            ->where('is_cashed', true)
            ->where('delta', '>', 0)
            ->sum('delta');

        // امتیازات قابل نقد (پررنگ)
        $uncashedPoints = UserPointTransaction::where('user_id', $user->id)
            ->where('is_cashed', false)
            ->where('delta', '>', 0)
            ->sum('delta');

        // نسبت تبدیل
        $ratio = (int) ($settings->reputation_to_gol_ratio ?? 100);

        // محاسبه موجودی فیدد کافی
        $hasEnoughFaded = $account->balance_faded >= intval($uncashedPoints / $ratio);

        return response()->json([
            'total_points' => $totalPoints,
            'uncashed_points' => $uncashedPoints,
            'cashed_points' => $cashedPoints,
            'conversion_ratio' => $ratio,
            'conversion_ratio_text' => "هر {$ratio} امتیاز = 1 گل",
            'balance_faded' => $account->balance_faded,
            'balance_faded_formatted' => \App\Helpers\BaharMoney::formatDecimal($account->balance_faded),
            'balance_active' => $account->balance_active,
            'balance_active_formatted' => \App\Helpers\BaharMoney::formatDecimal($account->balance_active),
            'has_enough_faded' => $hasEnoughFaded,
            'level' => $userPoint ? $userPoint->level : 'Bronze',
        ]);
    }

    /**
     * نقد کردن مقدار مشخصی از امتیازات
     */
    public function convert(Request $request)
    {
        $request->validate([
            'points' => 'required|integer|min:1',
        ]);

        $user = Auth::user();
        $pointsToConvert = $request->points;

        $settings = Setting::firstNajmBaharSettings();

        // بررسی فعال بودن
        if (!$settings->reputation_conversion_enabled) {
            return back()->with('error', 'تبدیل امتیاز به پول فعلاً غیرفعال است');
        }

        $account = $this->accountService->getMainAccountForUser($user->id);
        if (!$account) {
            return back()->with('error', 'حساب نجم بهار یافت نشد');
        }

        // نسبت تبدیل
        $ratio = (int) ($settings->reputation_to_gol_ratio ?? 100);

        try {
            DB::transaction(function () use ($user, $account, $pointsToConvert, $ratio) {
                // دریافت امتیازات نقد نشده
                $uncashedTransactions = UserPointTransaction::where('user_id', $user->id)
                    ->where('is_cashed', false)
                    ->where('delta', '>', 0)
                    ->orderBy('created_at', 'asc')
                    ->get();

                $availablePoints = $uncashedTransactions->sum('delta');

                if ($pointsToConvert > $availablePoints) {
                    throw new \Exception("امتیازات قابل نقد کافی نیست. امتیاز قابل نقد: {$availablePoints}");
                }

                // محاسبه مبلغ تبدیل (به گل)
                $amountInGol = intval($pointsToConvert / $ratio);

                if ($amountInGol <= 0) {
                    throw new \Exception("امتیازات وارد شده برای تبدیل کافی نیست. حداقل {$ratio} امتیاز نیاز است.");
                }

                // بررسی موجودی faded کافی
                if ($account->balance_faded < $amountInGol) {
                    throw new \Exception('موجودی کمرنگ شما برای تبدیل کافی نیست');
                }

                // علامت‌گذاری تراکنش‌ها به عنوان نقد شده
                $remaining = $pointsToConvert;
                foreach ($uncashedTransactions as $tx) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $toMark = min($tx->delta, $remaining);
                    $tx->is_cashed = true;
                    $tx->cashed_at = now();
                    $tx->cashed_amount_gol = intval($toMark / $ratio);
                    $tx->save();

                    $remaining -= $toMark;
                }

                // تبدیل faded به active
                $account->balance_faded -= $amountInGol;
                $account->balance_active += $amountInGol;
                $account->save();

                // ثبت تراکنش برای auditing
                \App\Modules\NajmBahar\Models\Transaction::create([
                    'from_account_id' => $account->id,
                    'to_account_id' => $account->id,
                    'amount' => $amountInGol,
                    'balance_type' => 'conversion',
                    'description' => "تبدیل {$pointsToConvert} امتیاز به پول فعال",
                    'transaction_type' => 'reputation_conversion',
                    'idempotency_key' => 'reputation-conversion-' . $user->id . '-' . now()->timestamp,
                    'metadata' => [
                        'type' => 'reputation_conversion',
                        'user_id' => $user->id,
                        'points_converted' => $pointsToConvert,
                        'ratio' => $ratio,
                        'amount_gol' => $amountInGol,
                        'from_balance_type' => 'faded',
                        'to_balance_type' => 'active',
                    ],
                ]);

                Log::info('Reputation converted to active money', [
                    'user_id' => $user->id,
                    'points' => $pointsToConvert,
                    'amount_gol' => $amountInGol,
                    'ratio' => $ratio,
                ]);
            });

            $amountFormatted = \App\Helpers\BaharMoney::formatDecimal(intval($pointsToConvert / $ratio));
            return redirect()->route('najm-bahar.wallet')
                ->with('success', "{$pointsToConvert} امتیاز با موفقیت به {$amountFormatted} بهار پول فعال تبدیل شد!");

        } catch (\Exception $e) {
            Log::error('Reputation conversion failed', [
                'user_id' => $user->id,
                'points' => $pointsToConvert,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'خطا در تبدیل امتیاز: ' . $e->getMessage());
        }
    }
}
