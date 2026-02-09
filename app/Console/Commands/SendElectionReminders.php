<?php

namespace App\Console\Commands;

use App\Models\Election;
use App\Models\Group;
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
        // پیدا کردن انتخابات‌های فعال که هنوز تمام نشده‌اند
        $activeElections = Election::where('is_closed', 0)
            ->where('ends_at', '>', now())
            ->where('starts_at', '<=', now())
            ->with('group')
            ->get();

        if ($activeElections->isEmpty()) {
            $this->info('هیچ انتخابات فعالی یافت نشد.');
            return 0;
        }

        $totalSent = 0;

        foreach ($activeElections as $election) {
            $group = $election->group;
            if (!$group) {
                continue;
            }

            // پیدا کردن کاربرانی که می‌توانند رای دهند (status=1, role>=1)
            $eligibleUsers = GroupUser::where('group_id', $group->id)
                ->where('status', 1)
                ->where('role', '>=', 1)
                ->pluck('user_id')
                ->all();

            if (empty($eligibleUsers)) {
                continue;
            }

            // پیدا کردن کاربرانی که قبلاً رای داده‌اند
            $votedUserIds = Vote::where('election_id', $election->id)
                ->distinct()
                ->pluck('voter_id')
                ->all();

            // کاربرانی که هنوز رای نداده‌اند
            $nonVotedUserIds = array_diff($eligibleUsers, $votedUserIds);

            if (empty($nonVotedUserIds)) {
                $this->info("گروه {$group->name}: همه کاربران رای داده‌اند.");
                continue;
            }

            // محاسبه زمان باقیمانده
            $endsAt = Jalalian::fromCarbon($election->ends_at);
            $now = Jalalian::now();
            $remainingDays = $endsAt->diffInDays($now);
            $remainingHours = $endsAt->diffInHours($now) % 24;

            $timeRemaining = '';
            if ($remainingDays > 0) {
                $timeRemaining = "{$remainingDays} روز";
            } elseif ($remainingHours > 0) {
                $timeRemaining = "{$remainingHours} ساعت";
            } else {
                $timeRemaining = "کمتر از یک ساعت";
            }

            $endsAtFormatted = $endsAt->format('Y/m/d H:i');

            $title = 'یادآوری: انتخابات گروه ' . ($group->name ?? '');
            $preview = "انتخابات گروه {$group->name} در حال برگزاری است. {$timeRemaining} تا پایان انتخابات باقی مانده است. (تا {$endsAtFormatted})";
            
            $url = route('groups.chat', $group->id);
            $context = [
                'group_id' => $group->id,
                'election_id' => $election->id,
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
        return 0;
    }
}

