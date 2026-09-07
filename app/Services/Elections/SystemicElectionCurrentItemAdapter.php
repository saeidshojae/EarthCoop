<?php

namespace App\Services\Elections;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Models\Election;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Support\Collection;

class SystemicElectionCurrentItemAdapter
{
    public function __construct(
        private readonly ElectionEligibilitySnapshotService $eligibility,
    ) {
    }

    public function itemsFor(User $user): Collection
    {
        $currentStatuses = collect(ElectionLifecycleStatus::cases())
            ->reject(fn (ElectionLifecycleStatus $status) => $status->isTerminal())
            ->map(fn (ElectionLifecycleStatus $status) => $status->value)
            ->all();

        $elections = Election::query()
            ->whereHas('group.users', fn ($query) => $query->whereKey($user->id))
            ->whereIn('lifecycle_status', $currentStatuses)
            ->with('group')
            ->orderByDesc('cycle_number')
            ->orderByDesc('id')
            ->get();

        if ($elections->isEmpty()) {
            return collect();
        }

        $votedElectionIds = Vote::query()
            ->where('voter_id', $user->id)
            ->whereIn('election_id', $elections->pluck('id'))
            ->pluck('election_id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        return $elections->map(function (Election $election) use ($user, $votedElectionIds): array {
            $state = $election->lifecycle_status instanceof ElectionLifecycleStatus
                ? $election->lifecycle_status
                : ElectionLifecycleStatus::from((string) $election->lifecycle_status);
            $eligibility = $this->eligibility->previewVoterEligibility($election, (int) $user->id);
            $eligible = (bool) $eligibility['eligible'];
            $hasVoted = $votedElectionIds->has((int) $election->id);
            $canVoteNow = $state === ElectionLifecycleStatus::Open && $eligible;
            $canEditVote = $canVoteNow && $hasVoted;

            $detailUrl = route('elections.portal', [
                'group' => $election->group,
                'election_id' => $election->id,
            ]);
            $voteUrl = route('groups.chat', $election->group);

            $primary = $canVoteNow
                ? [
                    'label' => $hasVoted ? 'ویرایش رأی' : 'ثبت رأی',
                    'url' => $voteUrl,
                    'kind' => 'vote',
                ]
                : [
                    'label' => 'مشاهده جزئیات',
                    'url' => $detailUrl,
                    'kind' => 'details',
                ];

            return [
                'source_type' => 'systemic',
                'source_id' => (int) $election->id,
                'group_id' => (int) $election->group_id,
                'group_name' => (string) ($election->group?->name ?? 'گروه'),
                'title' => 'چرخه ' . (int) ($election->cycle_number ?: 1) . ' انتخابات سیستمی',
                'status_key' => $state->value,
                'status_label' => $this->statusLabel($state),
                'is_current' => true,
                'eligible' => $eligible,
                'eligibility_label' => $this->eligibilityLabel($state, $eligible, $eligibility['reason'] ?? null),
                'has_voted' => $hasVoted,
                'can_vote_now' => $canVoteNow,
                'can_edit_vote' => $canEditVote,
                'starts_at' => $election->starts_at,
                'ends_at' => $election->ends_at,
                'deadline_label' => $election->ends_at ? $election->ends_at->diffForHumans() : null,
                'primary_action' => $primary,
                'secondary_action' => $canVoteNow ? [
                    'label' => 'جزئیات انتخابات',
                    'url' => $detailUrl,
                    'kind' => 'details',
                ] : null,
                'priority_bucket' => $canVoteNow && ! $hasVoted ? 'action_required' : 'related',
            ];
        });
    }

    private function statusLabel(ElectionLifecycleStatus $status): string
    {
        return match ($status) {
            ElectionLifecycleStatus::Scheduled => 'برنامه‌ریزی‌شده',
            ElectionLifecycleStatus::Open => 'در حال دریافت رأی',
            ElectionLifecycleStatus::Closed => 'رأی‌گیری متوقف شده',
            ElectionLifecycleStatus::Tallying => 'در حال شمارش',
            ElectionLifecycleStatus::AwaitingAcceptance => 'در انتظار پذیرش مسئولیت',
            ElectionLifecycleStatus::Appointing => 'در حال تکمیل انتصاب',
            ElectionLifecycleStatus::Filled => 'تکمیل‌شده',
            ElectionLifecycleStatus::Exhausted => 'پایان‌یافته',
            ElectionLifecycleStatus::Cancelled => 'لغوشده',
        };
    }

    private function eligibilityLabel(ElectionLifecycleStatus $state, bool $eligible, ?string $reason): string
    {
        if ($state !== ElectionLifecycleStatus::Open) {
            return 'مرحله دریافت رأی فعال نیست';
        }

        if ($eligible) {
            return 'واجد شرایط رأی دادن';
        }

        return match ($reason) {
            'observer_role' => 'به‌عنوان ناظر حق رأی ندارید',
            'guest_role' => 'به‌عنوان مهمان حق رأی ندارید',
            'inactive_membership' => 'عضویت فعال برای رأی دادن ندارید',
            'system_user' => 'حساب سیستمی حق رأی ندارد',
            default => 'در این انتخابات حق رأی ندارید',
        };
    }
}
