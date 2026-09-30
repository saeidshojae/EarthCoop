<?php

namespace App\Enums\Communication;

enum CommunicationStatus: string
{
    case Pending = 'pending';
    case Scheduled = 'scheduled';
    case Queued = 'queued';
    case Processing = 'processing';
    case Sent = 'sent';
    case PartiallyFailed = 'partially_failed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
