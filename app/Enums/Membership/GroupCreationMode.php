<?php

namespace App\Enums\Membership;

enum GroupCreationMode: string
{
    case Automatic = 'automatic';
    case Threshold = 'threshold';
    case OnDemand = 'on_demand';
    case Disabled = 'disabled';
}
