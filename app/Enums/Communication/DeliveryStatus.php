<?php

namespace App\Enums\Communication;

enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Sending = 'sending';
    case Retrying = 'retrying';
    case Sent = 'sent';
    case Failed = 'failed';
    case Suppressed = 'suppressed';
    case Invalid = 'invalid';
    case Cancelled = 'cancelled';
}
