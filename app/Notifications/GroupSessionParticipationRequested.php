<?php

namespace App\Notifications;

use App\Models\Group;
use App\Models\GroupSessionParticipationRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class GroupSessionParticipationRequested extends Notification
{
    use Queueable;

    public function __construct(
        public GroupSessionParticipationRequest $participationRequest,
        public Group $group,
        public User $requester
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $name = trim(($this->requester->first_name ?? '') . ' ' . ($this->requester->last_name ?? '')) ?: 'یکی از اعضا';

        return [
            'title' => 'درخواست مشارکت در نشست گروه',
            'message' => "{$name} در نشست «{$this->group->name}» دست بلند کرده است.",
            'url' => route('groups.chat', $this->group) . '?session_requests=1',
            'type' => 'group.chat.request',
            'context' => [
                'group_id' => (int) $this->group->id,
                'request_id' => (int) $this->participationRequest->id,
                'requester_id' => (int) $this->requester->id,
                'kind' => 'session_participation',
            ],
        ];
    }
}
