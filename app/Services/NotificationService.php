<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\GenericNotification;
use App\Services\Push\PushNotificationDispatcher;
use App\Support\Notifications\NotificationLink;
use App\Support\Notifications\NotificationLinkRegistry;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class NotificationService
{
    public function __construct(
        private readonly NotificationLinkRegistry $links,
        private readonly PushNotificationDispatcher $push,
    ) {
    }

    public function notifyUser(
        User|int $user,
        string $title,
        string $message,
        ?string $url = null,
        string $type = 'info',
        array $context = [],
        ?NotificationLink $link = null,
    ): void {
        $model = $user instanceof User ? $user : User::find($user);
        if (! $model) {
            return;
        }

        if ($link !== null) {
            $this->links->validate($link);
        }

        $settings = \App\Models\NotificationSetting::forUser($model->id);
        if (! $settings->isEnabled($type)) {
            return;
        }

        $model->notify(new GenericNotification($title, $message, $url, $type, $context, $link));
        $this->push->dispatch($model, $this->payload($title, $message, $url, $type, $context, $link));
    }

    public function notifyMany(
        array|Collection|EloquentCollection $users,
        string $title,
        string $message,
        ?string $url = null,
        string $type = 'info',
        array $context = [],
        ?NotificationLink $link = null,
    ): void {
        $collection = $this->normalizeUsers($users);
        if ($collection->isEmpty()) {
            return;
        }

        if ($link !== null) {
            $this->links->validate($link);
        }

        $enabledUsers = $collection->filter(function ($user) use ($type) {
            $settings = \App\Models\NotificationSetting::forUser($user->id);

            return $settings->isEnabled($type);
        });

        if ($enabledUsers->isEmpty()) {
            return;
        }

        NotificationFacade::send($enabledUsers, new GenericNotification($title, $message, $url, $type, $context, $link));
        $payload = $this->payload($title, $message, $url, $type, $context, $link);
        foreach ($enabledUsers as $user) {
            $this->push->dispatch($user, $payload);
        }
    }

    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function latest(User $user, int $limit = 10)
    {
        return $user->notifications()->latest()->take($limit)->get();
    }

    private function payload(
        string $title,
        string $message,
        ?string $url,
        string $type,
        array $context,
        ?NotificationLink $link,
    ): array {
        $payload = compact('title', 'message', 'url', 'type', 'context');
        if ($link !== null) {
            $payload['link'] = $link->toArray();
        }

        return $payload;
    }

    private function normalizeUsers(array|Collection|EloquentCollection $users): EloquentCollection
    {
        if ($users instanceof EloquentCollection) {
            return $users;
        }
        if ($users instanceof Collection) {
            return User::whereIn('id', $users->map(fn ($u) => $u instanceof User ? $u->id : $u)->all())->get();
        }

        $ids = array_map(fn ($u) => $u instanceof User ? $u->id : $u, $users);

        return User::whereIn('id', $ids)->get();
    }
}
