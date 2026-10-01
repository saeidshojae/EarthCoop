<?php

namespace App\Listeners;

use App\Events\ElectionStarted;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\NotificationService;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;

class SendElectionStartedNotifications
{
    public function __construct(
        private NotificationService $notifications,
        private TemporalService $temporal,
        private TemporalContextResolver $temporalContexts,
    ) {
    }

    public function handle(ElectionStarted $event): void
    {
        $group = $event->group;
        $election = $event->election;

        // گیرندگان: اعضای فعال گروه (status=1) که می‌توانند رای دهند
        $recipientIds = GroupUser::where('group_id', $group->id)
            ->where('status', 1)
            ->whereIn('role', [1, 2, 3]) // عضو فعال، بازرس و مدیر منتخب همگی حق رأی دارند
            ->pluck('user_id')
            ->all();

        if (empty($recipientIds)) {
            return;
        }

        $recipients = User::query()->whereIn('id', $recipientIds)->get();
        $recipientGroups = $recipients->groupBy(function (User $recipient): string {
            $context = $this->temporalContexts->forRecipient($recipient);

            return implode('|', [
                $context->locale(),
                $context->calendar(),
                $context->timezone(),
                $context->numberingSystem(),
            ]);
        });

        $title = 'انتخابات جدید در گروه ' . ($group->name ?? '');
        $url = route('groups.chat', $group->id);
        $notificationContext = [
            'group_id' => $group->id,
            'election_id' => $election->id,
            'ends_at' => $election->ends_at->toIso8601String(),
        ];

        foreach ($recipientGroups as $members) {
            /** @var \App\Models\User $representative */
            $representative = $members->first();
            $temporalContext = $this->temporalContexts->forRecipient($representative);
            $endsAt = $this->temporal->dateTime($election->ends_at, $temporalContext, 'short');
            $preview = "انتخابات برای انتخاب هیأت مدیره و بازرسان شروع شد. مهلت رای‌گیری تا {$endsAt} است.";

            $this->notifications->notifyMany(
                $members->pluck('id')->map(fn ($id): int => (int) $id)->all(),
                $title,
                $preview,
                $url,
                'group.election.started',
                $notificationContext
            );
        }
    }
}
