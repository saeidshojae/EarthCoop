<?php

namespace App\Http\Middleware;

use App\Services\MembershipParticipationEligibilityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMembershipParticipation
{
    public function __construct(
        protected MembershipParticipationEligibilityService $eligibility,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'برای مشارکت باید وارد حساب کاربری خود شوید.',
            ], 401);
        }

        $status = $this->eligibility->status($user);
        if ($status !== MembershipParticipationEligibilityService::ELIGIBLE) {
            return response()->json([
                'status' => 'error',
                'code' => $status,
                'message' => $status === MembershipParticipationEligibilityService::NO_NAJM_BAHAR_ACCOUNT
                    ? 'برای مشارکت در گروه، ابتدا توافقنامه مالی نجم بهار را تأیید و حساب خود را فعال کنید.'
                    : 'برای مشارکت در گروه، ابتدا حق عضویت دوره جاری EarthCoop را پرداخت کنید.',
                'action_url' => $status === MembershipParticipationEligibilityService::NO_NAJM_BAHAR_ACCOUNT
                    ? route('najm-bahar.agreement')
                    : route('najm-bahar.dashboard'),
            ], 403);
        }

        return $next($request);
    }
}
