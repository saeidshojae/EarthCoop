<?php

namespace App\Console\Commands;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Models\Election;
use App\Models\GroupUser;
use App\Models\Vote;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Morilog\Jalali\Jalalian;

class SendElectionReminders extends Command
{
    protected $signature = 'elections:send-reminders';
    protected $description = 'ارسال اعلان دعوت به شرکت در انتخابات برای کاربرانی که هنوز رای نداده‌اند';

    public function __construct(private NotificationService $notifications)
    {
        parent::__construct();
    }

    public function handle()
    {
        // Only the canonical open lifecycle accepts ballots. Legacy is_closed is
        // a compatibility mirror and must not independently define reminder scope.
        $activeElections = Election::query()
            ->where('lifecycle_status', ElectionLifecycleStatus::Open->value)
            ->where('ends_at', '>', now())
            ->where('starts_at', '<=', now())
            ->with('group')
            ->get();

        if ($activeElections->isEmpty()) {
            $this->info('هیچ انتخابات فعالی یافت نشد.');
            return self::SUCCESS;
        }

        $totalSent = 0;

        foreach ($activeElections as $election) {
            $group = $election->group;
            if (! $group) {
                continue;
            }

            // Mirror the systemic ballot eligibility boundary for an open
            // continuous cycle: active group member, participatory role, and not
            // a system identity. Late-joining eligible members are intentionally
            // included because E0 admits them during the still-open window.
            $eligibleUsers = GroupUser::query()
                ->join('users', 'users.id', '=', 'group_user.user_id')
                ->where('group_user.group_id', $group->id)
                ->where('group_user.status', 1)
                ->where('group_user.role', '>=', 1)
                ->where('group_user.role', '!=', 4)
                ->where('users.is_system', false)
                ->pluck('group_user.user_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (empty($eligibleUsers)) {
                continue;
            }

            $votedUserIds = Vote::query()
                ->where('election_id', $election->id)
                ->distinct()
                ->pluck('voter_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $nonVotedUserIds = array_values(array_diff($eligibleUsers, $votedUserIds));

            if (empty($nonVotedUserIds)) {
                $this->info("گروه {$group->name}: همه کاربران رای داده‌اند.");
                continue;
            }

            $endsAt = Jalalian::fromCarbon($election->ends_at);
            $now = Jalalian::now();
            $remainingDays = $endsAt->diffInDays($now);
            $remainingHours = $endsAt->diffInHours($now) % 24;

            if ($remainingDays > 0) {
                $timeRemaining = "{$remainingDays} روز";
            } elseif ($remainingHours > 0) {
                $timeRemaining = "{$remainingHours} ساعت";
            } else {
                $timeRemaining = 'کمتر از یک ساعت';
            }

            $endsAtFormatted = $endsAt->format('Y/m/d H:i');
            $title = 'یادآوری: انتخابات گروه ' . ($group->name ?? '');
            $preview = "انتخابات گروه {$group->name} در حال برگزاری است. {$timeRemaining} تا پایان انتخابات باقی مانده است. (تا {$endsAtFormatted})";
            $url = route('groups.chat', $group->id);
            $context = [
                'group_id' => $group->id,
                'election_id' => $election->id,
                'cycle_number' => (int) ($election->cycle_number ?? 1),
                'ends_at' => $election->ends_at->toIso8601String(),
            ];

            $this->notifications->notifyMany(
                $nonVotedUserIds,
                $title,
                $preview,
                $url,
                'group.election.reminder',
                $context
            );

            $count = count($nonVotedUserIds);
            $totalSent += $count;
            $this->info("گروه {$group->name}: {$count} اعلان ارسال شد.");
        }

        $this->info("مجموعاً {$totalSent} اعلان ارسال شد.");
        return self::SUCCESS;
    }
}
