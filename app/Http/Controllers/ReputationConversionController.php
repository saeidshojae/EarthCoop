<?php

namespace App\Http\Controllers;

use App\Models\UserPoint;
use App\Models\UserPointTransaction;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\MonetaryPolicyService;
use App\Modules\NajmBahar\Services\MonetaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        $cashedPoints = UserPointTransaction::where('user_id', $user->id)
            ->where('is_cashed', true)
            ->where('delta', '>', 0)
            ->sum('delta');

        $uncashedPoints = UserPointTransaction::where('user_id', $user->id)
            ->where('is_cashed', false)
            ->where('delta', '>', 0)
            ->sum('delta');

        $ratio = max(1, (int) data_get($policy, 'parameters.reputation_to_gol_ratio', 100));
        $hasEnoughFaded = $account->balance_faded >= intval($uncashedPoints / $ratio);

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
        $pointsToConvert = $request->points;
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

        try {
            DB::transaction(function () use (
                $user,
                $account,
                $pointsToConvert,
                $ratio,
                $policyVersionId,
                $policyVersion
            ) {
                $uncashedTransactions = UserPointTransaction::where('user_id', $user->id)
                    ->where('is_cashed', false)
                    ->where('delta', '>', 0)
                    ->orderBy('created_at', 'asc')
                    ->lockForUpdate()
                    ->get();

                $availablePoints = $uncashedTransactions->sum('delta');

                if ($pointsToConvert > $availablePoints) {
                    throw new \Exception("امتیازات قابل نقد کافی نیست. امتیاز قابل نقد: {$availablePoints}");
                }

                $amountInGol = intval($pointsToConvert / $ratio);

                if ($amountInGol <= 0) {
                    throw new \Exception("امتیازات وارد شده برای تبدیل کافی نیست. حداقل {$ratio} امتیاز نیاز است.");
                }

                if ($account->balance_faded < $amountInGol) {
                    throw new \Exception('موجودی کمرنگ شما برای تبدیل کافی نیست');
                }

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

                $idempotencyKey = 'reputation-conversion-' . $user->id . '-' . now()->format('YmdHisv');

                $this->monetaryService->activateDim(
                    $account,
                    $amountInGol,
                    "تبدیل {$pointsToConvert} امتیاز به پول فعال",
                    [
                        'type' => 'reputation_conversion',
                        'user_id' => $user->id,
                        'points_converted' => $pointsToConvert,
                        'ratio' => $ratio,
                        'policy_version_id' => $policyVersionId,
                        'policy_version' => $policyVersion,
                    ],
                    $idempotencyKey,
                    false
                );

                Log::info('Reputation converted to active money', [
                    'user_id' => $user->id,
                    'points' => $pointsToConvert,
                    'amount_gol' => $amountInGol,
                    'ratio' => $ratio,
                    'policy_version_id' => $policyVersionId,
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
