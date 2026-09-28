<?php

declare(strict_types=1);

namespace App\Services\Actors;

enum ActorType: string
{
    case User = 'user';
    case Group = 'group';
    case Organization = 'organization';
    case System = 'system';
}
