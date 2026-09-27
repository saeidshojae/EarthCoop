<?php

namespace App\Http\Controllers;

use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\Api\NajmBaharActivationApplicationService;
use App\Modules\NajmBahar\Services\Api\NajmBaharActivationException;
use App\Modules\NajmBahar\Services\MonetaryPolicyService;
use App\Services\ParticipationPointSummaryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReputationConversionController extends Controller
{
    protected $accountService;
    protected $monetaryPolicyService;
    protected $participationPointSummaryService;
    protected $activationApplicationService;

    public function __construct(
        AccountService $accountService,
        MonetaryPolicyService $monetaryPolicyService,
        ParticipationPointSummaryService $participationPointSummaryService,
        NajmBaharActivationApplicationService $activationApplicationService,
    ) {
        $this->accountService = $accountService;
        $this->monetaryPolicyService = $monetaryPolicyService;
        $this->participationPointSummaryService = $participationPointSummaryService;
        $this->activationApplicationService = $activationApplicationService;
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

        $pointSummary = $this->participationPointSummaryService->forUser($user->id);
        $totalPoints = $pointSummary['total_points'];
        $uncashedPoints = $pointSummary['remaining_convertible_points'];
        $cashedPoints = $pointSummary['cashed_points'];

        $ratio = max(1, (int) data_get($policy, 'parameters.reputation_to_gol_ratio', 100));
        $hasEnoughFaded = $account->balance_faded >= intdiv($uncashedPoints, $ratio);

        return response()->json([
            'total_points' => $totalPoints,
            'uncashed_points' => $uncashedPoints,
            'cashed_points' => $cashedPoints,
            'convertible_awarded_points' => $pointSummary['convertible_awarded_points'],
            'ledger_consumed_points' => $pointSummary['ledger_consumed_points'],
            'legacy_cashed_points' => $pointSummary['legacy_cashed_points'],
            'participation_reversal_points' => $pointSummary['participation_reversal_points'],
            'remaining_convertible_points' => $pointSummary['remaining_convertible_points'],
            'conversion_ratio' => $ratio,
            'conversion_ratio_text' => "هر {$ratio} امتیاز = 1 گل",
            'policy_version' => $policy['version'],
            'policy_source' => $policy['source'],
            'balance_faded' => $account->balance_faded,
            'balance_faded_formatted' => \App\Helpers\BaharMoney::formatDecimal($account->balance_faded),
            'balance_active' => $account->balance_active,
            'balance_active_formatted' => \App\Helpers\BaharMoney::formatDecimal($account->balance_active),
            'has_enough_faded' => $hasEnoughFaded,
            'level' => $pointSummary['level'],
        ]);
    }

    public function convert(Request $request)
    {
        $request->validate([
            'points' => 'required|integer|min:1',
        ]);

        $user = Auth::user();
        $pointsToConvert = (int) $request->points;

        $requestedConversionKey = trim((string) $request->header('Idempotency-Key', ''));
        if ($requestedConversionKey === '') {
            $requestedConversionKey = trim((string) $request->input('idempotency_key', ''));
        }
        $requestKey = $requestedConversionKey !== ''
            ? $requestedConversionKey
            : (string) Str::uuid();

        try {
            $result = $this->activationApplicationService->activate(
                $user,
                $pointsToConvert,
                $requestKey,
            );

            $completedPoints = (int) $result['consumed_points'];
            $completedAmountInGol = (int) $result['activated_gol'];
            $amountFormatted = \App\Helpers\BaharMoney::formatDecimal($completedAmountInGol);
            $message = (bool) $result['already_applied']
                ? "درخواست تبدیل {$completedPoints} امتیاز قبلاً با موفقیت انجام شده است."
                : "{$completedPoints} امتیاز با موفقیت به {$amountFormatted} بهار پول فعال تبدیل شد!";

            return redirect()->route('najm-bahar.wallet')->with('success', $message);
        } catch (NajmBaharActivationException $exception) {
            return back()->with('error', $this->webErrorMessage($exception));
        } catch (ModelNotFoundException) {
            return back()->with('error', 'حساب نجم بهار یافت نشد');
        } catch (\Throwable $exception) {
            Log::error('Reputation conversion failed', [
                'user_id' => $user?->id,
                'points' => $pointsToConvert,
                'request_key' => $requestKey,
                'error' => $exception->getMessage(),
            ]);

            return back()->with('error', 'خطا در تبدیل امتیاز: '.$exception->getMessage());
        }
    }

    private function webErrorMessage(NajmBaharActivationException $exception): string
    {
        return match ($exception->errorCode) {
            'activation_disabled' => 'تبدیل امتیاز به پول فعلاً غیرفعال است',
            'activation_not_eligible' => 'امتیازات قابل نقد کافی برای این تبدیل وجود ندارد',
            'insufficient_dim' => 'موجودی کمرنگ شما برای تبدیل کافی نیست',
            'idempotency_key_reused' => 'این شناسه درخواست قبلاً برای درخواست دیگری استفاده شده است',
            default => 'خطا در تبدیل امتیاز: '.$exception->getMessage(),
        };
    }
}
