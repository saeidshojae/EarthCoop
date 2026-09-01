<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\InvitationCode;
use App\Models\ReputationRule;
use App\Services\InvitationLifecycleService;
use App\Services\MembershipParticipationEligibilityService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class MemberInvitationController extends Controller
{
    public function index(
        InvitationLifecycleService $invitations,
        MembershipParticipationEligibilityService $participationEligibility
    ): View {
        $user = auth()->user();
        $status = $participationEligibility->status($user);
        $rewardPoints = (int) (ReputationRule::where('key', 'invite_member')->value('weight')
            ?? config('reputation.weights.invite_member', 100));

        $codes = InvitationCode::where('user_id', $user->id)
            ->with('usedBy')
            ->orderByDesc('created_at')
            ->get();

        return view('profile.member-invitations', [
            'codes' => $codes,
            'quota' => $invitations->quota(),
            'expiryHours' => $invitations->expiryHours(),
            'rewardPoints' => $rewardPoints,
            'successfulInvitations' => $invitations->successfulInvitations($user),
            'occupiedSlots' => $invitations->occupiedSlots($user),
            'remainingSlots' => $invitations->remainingSlots($user),
            'canIssueInvitation' => $invitations->canIssueMemberInvitation($user),
            'participationStatus' => $status,
        ]);
    }

    public function __invoke(
        InvitationLifecycleService $invitations,
        MembershipParticipationEligibilityService $participationEligibility
    ): RedirectResponse {
        $user = auth()->user();

        if (! $user) {
            return back()->with('error', 'برای ساخت کد دعوت باید وارد حساب کاربری خود شوید.');
        }

        $participationStatus = $participationEligibility->status($user);
        if ($participationStatus === MembershipParticipationEligibilityService::NO_NAJM_BAHAR_ACCOUNT) {
            return redirect()->route('najm-bahar.agreement')
                ->with('info', 'برای دعوت اعضای جدید، ابتدا توافقنامه مالی نجم بهار را بپذیرید و حساب خود را فعال کنید.');
        }

        if ($participationStatus === MembershipParticipationEligibilityService::MEMBERSHIP_FEE_DUE) {
            return redirect()->route('najm-bahar.dashboard')
                ->with('info', 'برای دعوت اعضای جدید، ابتدا حق عضویت دوره جاری EarthCoop را پرداخت کنید.');
        }

        if (! $invitations->canIssueMemberInvitation($user)) {
            return back()->with('error', 'سهمیه دعوت موفق شما تکمیل شده یا شرایط عضویت شما برای صدور دعوت کامل نیست.');
        }

        try {
            $code = $invitations->issueMemberInvitation($user);
        } catch (RuntimeException $e) {
            return back()->with('error', 'سهمیه دعوت موفق شما تکمیل شده یا امکان صدور کد جدید وجود ندارد.');
        }

        return back()->with('success', 'کد دعوت جدید با موفقیت ساخته شد: ' . $code->code);
    }
}
