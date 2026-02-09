<?php

namespace App\Listeners;

use App\Events\ElectionStarted;
use App\Models\GroupUser;
use App\Services\NotificationService;
use Morilog\Jalali\Jalalian;

class SendElectionStartedNotifications
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function handle(ElectionStarted $event): void
    {
        $group = $event->group;
        $election = $event->election;

        // گیرندگان: اعضای فعال گروه (status=1) که می‌توانند رای دهند
        $recipientIds = GroupUser::where('group_id', $group->id)
            ->where('status', 1)
            ->where('role', '>=', 1) // فقط کاربران فعال (نه ناظر)
            ->pluck('user_id')
            ->all();

        if (empty($recipientIds)) {
            return;
        }

        $endsAt = Jalalian::fromCarbon($election->ends_at)->format('Y/m/d H:i');
        
        $title = 'انتخابات جدید در گروه ' . ($group->name ?? '');
        $preview = "انتخابات برای انتخاب هیأت مدیره و بازرسان شروع شد. مهلت رای‌گیری تا {$endsAt} است.";
        
        $url = route('groups.chat', $group->id);
        $context = [
            'group_id' => $group->id,
            'election_id' => $election->id,
            'ends_at' => $election->ends_at->toIso8601String(),
        ];

        $this->notifications->notifyMany(
            $recipientIds,
            $title,
            $preview,
            $url,
            'group.election.started',
            $context
        );
    }
}

