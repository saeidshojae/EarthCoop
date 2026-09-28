<?php

namespace App\Support\Notifications;

use InvalidArgumentException;

class NotificationLinkRegistry
{
    private const ROUTES = [
        'project.detail',
        'group.thread',
        'group.detail',
        'election.detail',
        'najm-bahar.account',
        'notification.list',
    ];

    public function validate(NotificationLink $link): void
    {
        if ($link->version !== 1) {
            throw new InvalidArgumentException('Unsupported notification link version.');
        }

        if (! in_array($link->route, self::ROUTES, true)) {
            throw new InvalidArgumentException('Unsupported notification link route.');
        }
    }
}
