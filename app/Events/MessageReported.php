<?php

namespace App\Events;

use App\Models\ReportedMessage;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReported
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ReportedMessage $report,
        public Group $group,
        public User $reporter
    ) {}
}

