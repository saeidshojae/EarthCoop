<?php

namespace App\Services;

use App\Notifications\GenericNotification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use App\Models\User;

class NotificationService
{
    /**
     * Notify one user by id or model.
     */
    public function notifyUser(User|int $user, string $title, string $message, ?string $url = null, string $type = 'info', array $context = []): void
    {
        $model = $user instanceof User ? $user : User::find($user);
        if (!$model) return;
        
        // Check user notification settings
        $settings = \App\Models\NotificationSetting::forUser($model->id);
        if (!$settings->isEnabled($type)) {
            return; // User has disabled this notification type
        }
        
        $model->notify(new GenericNotification($title, $message, $url, $type, $context));
    }

    /**
     * Notify many users by ids or models.
     * @param array<int>|Collection|EloquentCollection $users
     */
    public function notifyMany(array|Collection|EloquentCollection $users, string $title, string $message, ?string $url = null, string $type = 'info', array $context = []): void
    {
        $collection = $this->normalizeUsers($users);
        if ($collection->isEmpty()) return;
        
        // Filter users based on their notification settings
        $enabledUsers = $collection->filter(function($user) use ($type) {
            $settings = \App\Models\NotificationSetting::forUser($user->id);
            return $settings->isEnabled($type);
        });
        
        if ($enabledUsers->isEmpty()) return;
        
        NotificationFacade::send($enabledUsers, new GenericNotification($title, $message, $url, $type, $context));
    }

    /**
     * Helpers for unread/latest — optionally keep here for reuse.
     */
    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function latest(User $user, int $limit = 10)
    {
        return $user->notifications()->latest()->take($limit)->get();
    }

    private function normalizeUsers(array|Collection|EloquentCollection $users): EloquentCollection
    {
        if ($users instanceof EloquentCollection) return $users;
        if ($users instanceof Collection) return User::whereIn('id', $users->map(fn($u) => $u instanceof User ? $u->id : $u)->all())->get();
        // array
        $ids = array_map(fn($u) => $u instanceof User ? $u->id : $u, $users);
        return User::whereIn('id', $ids)->get();
    }
}
