<?php

namespace App\Enums\Elections;

enum ElectionLifecycleStatus: string
{
    case Scheduled = 'scheduled';
    case Open = 'open';
    case Closed = 'closed';
    case Tallying = 'tallying';
    case AwaitingAcceptance = 'awaiting_acceptance';
    case Appointing = 'appointing';
    case Filled = 'filled';
    case Exhausted = 'exhausted';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Filled, self::Exhausted, self::Cancelled => true,
            default => false,
        };
    }
}
