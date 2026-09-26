<?php

namespace App\Services\Home;

use App\Models\Poll;
use App\Models\User;
use App\Modules\NajmBahar\Services\AccountService;
use App\Services\Elections\CurrentElectionCenterService;
use App\Services\InvitationLifecycleService;
use App\Services\ProfileCompletionService;

class HomeCivicDashboardService
{
    public function __construct(
        private readonly ProfileCompletionService $profileCompletion,
        private readonly AccountService $accounts,
        private readonly InvitationLifecycleService $invitations,
        private readonly CurrentElectionCenterService $elections,
    ) {
    }

    public function forUser(User $user, array $groupCounts, int $pendingLocationGroupCount = 0): array
    {
        $residenceComplete = $this->profileCompletion->hasRequiredResidence($user);
        $hasNajmBaharAccount = $this->accounts->hasMainAccount((int) $user->id);
        $remainingInvitationSlots = max(0, $this->invitations->remainingSlots($user));
        $electionSnapshot = $this->elections->forUser($user);
        $electionActionRequired = (int) data_get($electionSnapshot, 'summary.action_required', 0);
        $pollActionRequired = $this->actionablePollCount($user);

        $publicGroups = max(0, (int) ($groupCounts['public'] ?? 0));
        $specializedGroups = max(0, (int) ($groupCounts['specialized'] ?? 0));
        $exclusiveGroups = max(0, (int) ($groupCounts['exclusive'] ?? 0));
        $totalGroups = $publicGroups + $specializedGroups + $exclusiveGroups;

        $journey = [
            'residence' => [
                'status' => $residenceComplete ? 'complete' : 'needs_attention',
                'complete' => $residenceComplete,
            ],
            'najm_bahar' => [
                'status' => $hasNajmBaharAccount ? 'active' : 'needs_attention',
                'active' => $hasNajmBaharAccount,
            ],
            'groups' => [
                'status' => $totalGroups > 0 ? 'active' : 'empty',
                'total' => $totalGroups,
                'public' => $publicGroups,
                'specialized' => $specializedGroups,
                'exclusive' => $exclusiveGroups,
            ],
            'invitation' => [
                'status' => $remainingInvitationSlots > 0 ? 'available' : 'complete',
                'remaining_slots' => $remainingInvitationSlots,
            ],
        ];

        $today = [
            'unread_notifications' => $user->unreadNotifications()->count(),
            'election_action_required' => $electionActionRequired,
            'poll_action_required' => $pollActionRequired,
            'pending_location_groups' => max(0, $pendingLocationGroupCount),
        ];

        return [
            'journey' => $journey,
            'today' => $today,
            'next_action' => $this->nextAction(
                $residenceComplete,
                $hasNajmBaharAccount,
                $electionActionRequired,
                $pollActionRequired,
                $remainingInvitationSlots,
            ),
        ];
    }

    private function actionablePollCount(User $user): int
    {
        return Poll::query()
            ->where('main_type', 1)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->whereHas('group.groupUser', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->where('status', 1);
            })
            ->whereDoesntHave('votes', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->count();
    }

    private function nextAction(
        bool $residenceComplete,
        bool $hasNajmBaharAccount,
        int $electionActionRequired,
        int $pollActionRequired,
        int $remainingInvitationSlots,
    ): array {
        if (! $residenceComplete) {
            return [
                'key' => 'residence',
                'route' => 'register.step3',
                'label' => 'تکمیل مکان و حکمرانی',
                'description' => 'مکان پایهٔ حکمرانی خود را تکمیل کنید تا مسیر مشارکت شما کامل شود.',
            ];
        }

        if (! $hasNajmBaharAccount) {
            return [
                'key' => 'najm_bahar',
                'route' => 'najm-bahar.dashboard',
                'label' => 'راه‌اندازی نجم بهار',
                'description' => 'حساب اصلی نجم بهار را فعال کنید تا بخش اقتصادی ارث‌کوپ برای شما آماده شود.',
            ];
        }

        if ($electionActionRequired > 0) {
            return [
                'key' => 'election',
                'route' => 'history.election',
                'label' => 'رسیدگی به انتخابات جاری',
                'description' => 'در انتخابات جاری موردی وجود دارد که به اقدام شما نیاز دارد.',
            ];
        }

        if ($pollActionRequired > 0) {
            return [
                'key' => 'poll',
                'route' => 'history.poll',
                'label' => 'شرکت در نظرسنجی جاری',
                'description' => 'یک نظرسنجی فعال در گروه‌های شما هنوز منتظر رأی شماست.',
            ];
        }

        if ($remainingInvitationSlots > 0) {
            return [
                'key' => 'invitation',
                'route' => 'my-invation-code',
                'label' => 'دعوت و گسترش مشارکت',
                'description' => 'از سهمیهٔ دعوت آزاد خود برای افزودن اعضای واقعی به شبکه استفاده کنید.',
            ];
        }

        return [
            'key' => 'civic_anchor',
            'route' => 'location-governance.me',
            'label' => 'مشاهده مکان و حکمرانی من',
            'description' => 'وضعیت محلی و جایگاه حکمرانی خود را مرور کنید.',
        ];
    }
}
