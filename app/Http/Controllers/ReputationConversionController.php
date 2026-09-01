<?php

namespace App\Http\Controllers;

use App\Models\UserPoint;
use App\Models\UserPointConsumption;
use App\Models\UserPointConversion;
use App\Models\UserPointTransaction;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\MonetaryPolicyService;
use App\Modules\NajmBahar\Services\MonetaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReputationConversionController extends Controller
{
    protected $accountService;
    protected $monetaryService;
    protected $monetaryPolicyService;

    public function __construct(
        AccountService $accountService,
        MonetaryService $monetaryService,
        MonetaryPolicyService $monetaryPolicyService
    ) {
        $this->accountService = $accountService;
        $this->monetaryService = $monetaryService;
        $this->monetaryPolicyService = $monetaryPolicyService;
    }

    private function convertibleTransactions(int $userId)
    {
        return UserPointTransaction::where('user_id', $userId)
            ->where('delta', '>', 0)
            ->where('convertible', true)
            ->where('dimension', 'participation')
            ->where('is_cashed', false)
            ->withSum('consumptions as consumptions_sum_points_consumed', 'points_consumed')
            ->orderBy('created_at', 'asc');
    }

    private function legacyCashedPoints(int $userId): int
    {
        return (int) UserPointTransaction::where('user_id', $userId)
            ->where('delta', '>', 0)
            ->where('convertible', true)
            ->where('dimension', 'participation')
            ->where('is_cashed', true)
            ->sum('delta');
    }

    public function getInfo()
    {
        $user = Auth::user();
        $policy = $this->monetaryPolicyService->current();
        $enabled = (bool) data_get($policy, 'parameters.reputation_conversion_enabled', false);

        if (!$enabled) {
            return response()->json(['error' => 'تبدیل امتیاز به پول فعلاً غیرفعال است'], 403);
        }

        $account = $this->accountService->getMainAccountForUser($user->id);
        if (!$account) {
            return response()->json(['error' => 'حساب نجم بهار یافت نشد'], 404);
        }

        $userPoint = UserPoint::where('user_id', $user->id)->first();
        $totalPoints = $userPoint ? $userPoint->points : 0;
        $transactions = $this->convertibleTransactions($user->id)->get();
        $convertibleAwarded = (int) $transactions->sum('delta');
        $ledgerConsumedPoints = (int) $transactions->sum(fn ($tx) => (int) ($tx->consumptions_sum_points_consumed ?? 0));
        $legacyCashedPoints = $this->legacyCashedPoints($user->id);
        $cashedPoints = $legacyCashedPoints + $ledgerConsumedPoints;
        $uncashedPoints = max(0, $convertibleAwarded - $ledgerConsumedPoints);

        $ratio = max(1, (int) data_get($policy, 'parameters.reputation_to_gol_ratio', 100));
        $hasEnoughFaded = $account->balance_faded >= intdiv($uncashedPoints, $ratio);

        return response()->json([
            'total_points' => $totalPoints,
            'uncashed_points' => $uncashedPoints,
            'cashed_points' => $cashedPoints,
            'conversion_ratio' => $ratio,
            'conversion_ratio_text' => "هر {$ratio} امتیاز = 1 گل",
            'policy_version' => $policy['version'],
            'policy_source' => $policy['source'],
            'balance_faded' => $account->balance_faded,
            'balance_faded_formatted' => \App\Helpers\BaharMoney::formatDecimal($account->balance_faded),
            'balance_active' => $account->balance_active,
            'balance_active_formatted' => \App\Helpers\BaharMoney::formatDecimal($account->balance_active),
            'has_enough_faded' => $hasEnoughFaded,
            'level' => $userPoint ? $userPoint->level : 'Bronze',
        ]);
    }

    public function convert(Request $request)
    {
        $request->validate([
            'points' => 'required|integer|min:1',
        ]);

        $user = Auth::user();
        $pointsToConvert = (int) $request->points;
        $policy = $this->monetaryPolicyService->current();
        $enabled = (bool) data_get($policy, 'parameters.reputation_conversion_enabled', false);

        if (!$enabled) {
            return back()->with('error', 'تبدیل امتیاز به پول فعلاً غیرفعال است');
        }

        $account = $this->accountService->getMainAccountForUser($user->id);
        if (!$account) {
            return back()->with('error', 'حساب نجم بهار یافت نشد');
        }

        $ratio = max(1, (int) data_get($policy, 'parameters.reputation_to_gol_ratio', 100));
        $policyVersionId = $policy['version_id'];
        $policyVersion = $policy['version'];
        $convertiblePoints = intdiv($pointsToConvert, $ratio) * $ratio;
        $amountInGol = intdiv($convertiblePoints, $ratio);

        if ($amountInGol <= 0) {
            return back()->with('error', "امتیازات وارد شده برای تبدیل کافی نیست. حداقل {$ratio} امتیاز نیاز است.");
        }

        $requestedConversionKey = trim((string) $request->header('Idempotency-Key', ''));
        if ($requestedConversionKey === '') {
            $requestedConversionKey = trim((string) $request->input('idempotency_key', ''));
        }
        $requestKey = $requestedConversionKey !== ''
            ? $requestedConversionKey
            : (string) Str::uuid();
        $conversionKey = 'reputation-conversion:' . $user->id . ':' . $requestKey;

        try {
            $alreadyConverted = false;
            $completedPoints = $convertiblePoints;
            $completedAmountInGol = $amountInGol;

            DB::transaction(function () use (
                $user,
                $account,
                $pointsToConvert,
                $convertiblePoints,
                $amountInGol,
                $ratio,
                $policyVersionId,
                $policyVersion,
                $requestKey,
                $conversionKey,
                &$alreadyConverted,
                &$completedPoints,
                &$completedAmountInGol
            ) {
                $identity = UserPointConversion::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'request_key' => $requestKey,
                    ],
                    [
                        'conversion_key' => $conversionKey,
                        'requested_points' => $pointsToConvert,
                        'consumed_points' => 0,
                        'amount_gol' => $amountInGol,
                        'ratio' => $ratio,
                        'policy_version_id' => $policyVersionId,
                        'policy_version' => $policyVersion,
                        'status' => 'pending',
                    ]
                );

                $identity = UserPointConversion::whereKey($identity->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($identity->status === 'applied') {
                    $alreadyConverted = true;
                    $completedPoints = (int) $identity->consumed_points;
                    $completedAmountInGol = (int) $identity->amount_gol;
                    return;
                }

                $transactions = $this->convertibleTransactions($user->id)
                    ->lockForUpdate()
                    ->get();

                $availablePoints = (int) $transactions->sum(function ($tx) {
                    return max(0, (int) $tx->delta - (int) ($tx->consumptions_sum_points_consumed ?? 0));
                });

                if ($convertiblePoints > $availablePoints) {
                    throw new \Exception("امتیازات قابل نقد کافی نیست. امتیاز قابل نقد: {$availablePoints}");
                }

                if ($account->balance_faded < $amountInGol) {
                    throw new \Exception('موجودی کمرنگ شما برای تبدیل کافی نیست');
                }

                $remaining = $convertiblePoints;

                foreach ($transactions as $tx) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $alreadyConsumed = (int) ($tx->consumptions_sum_points_consumed ?? 0);
                    $availableFromTransaction = max(0, (int) $tx->delta - $alreadyConsumed);
                    $toConsume = min($availableFromTransaction, $remaining);

                    if ($toConsume <= 0) {
                        continue;
                    }

                    UserPointConsumption::create([
                        'user_id' => $user->id,
                        'user_point_conversion_id' => $identity->id,
                        'user_point_transaction_id' => $tx->id,
                        'points_consumed' => $toConsume,
                        'conversion_key' => $conversionKey,
                        'policy_version_id' => $policyVersionId,
                        'policy_version' => $policyVersion,
                    ]);

                    $remaining -= $toConsume;
                }

                if ($remaining !== 0) {
                    throw new \Exception('ثبت مصرف امتیاز ناقص ماند و تبدیل لغو شد');
                }

                $this->monetaryService->activateDim(
                    $account,
                    $amountInGol,
                    "تبدیل {$convertiblePoints} امتیاز به پول فعال",
                    [
                        'type' => 'reputation_conversion',
                        'user_id' => $user->id,
                        'points_converted' => $convertiblePoints,
                        'ratio' => $ratio,
                        'policy_version_id' => $policyVersionId,
                        'policy_version' => $policyVersion,
                        'user_point_conversion_id' => $identity->id,
                    ],
                    $conversionKey,
                    false
                );

                $identity->update([
                    'consumed_points' => $convertiblePoints,
                    'status' => 'applied',
                ]);

                $completedPoints = $convertiblePoints;
                $completedAmountInGol = $amountInGol;

                Log::info('Reputation converted to active money', [
                    'user_id' => $user->id,
                    'user_point_conversion_id' => $identity->id,
                    'points' => $convertiblePoints,
                    'amount_gol' => $amountInGol,
                    'ratio' => $ratio,
                    'policy_version_id' => $policyVersionId,
                    'conversion_key' => $conversionKey,
                ]);
            });

            $amountFormatted = \App\Helpers\BaharMoney::formatDecimal($completedAmountInGol);
            $message = $alreadyConverted
                ? "درخواست تبدیل {$completedPoints} امتیاز قبلاً با موفقیت انجام شده است."
                : "{$completedPoints} امتیاز با موفقیت به {$amountFormatted} بهار پول فعال تبدیل شد!";

            return redirect()->route('najm-bahar.wallet')->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Reputation conversion failed', [
                'user_id' => $user->id,
                'points' => $convertiblePoints,
                'conversion_key' => $conversionKey,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'خطا در تبدیل امتیاز: ' . $e->getMessage());
        }
    }
}
