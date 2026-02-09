<?php

namespace App\Listeners;

use App\Events\GroupInvitation;
use App\Services\NotificationService;

class SendGroupInvitationNotifications
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function handle(GroupInvitation $event): void
    {
        $group = $event->group;
        $invitedUser = $event->invitedUser;
        $inviter = $event->inviter;

        $inviterName = $inviter ? $inviter->fullName() : 'سیستم';
        
        $title = 'دعوت به گروه ' . ($group->name ?? '');
        $preview = "{$inviterName} شما را به گروه {$group->name} دعوت کرده است.";
        
        $url = route('groups.chat', $group->id);
        $context = [
            'group_id' => $group->id,
            'inviter_id' => $inviter?->id,
        ];

        $this->notifications->notifyUser(
            $invitedUser->id,
            $title,
            $preview,
            $url,
            'group.invitation',
            $context
        );
    }
}

