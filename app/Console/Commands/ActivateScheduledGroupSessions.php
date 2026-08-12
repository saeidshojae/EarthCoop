<?php

namespace App\Console\Commands;

use App\Models\GroupSession;
use App\Services\GroupChat\GroupSessionService;
use Illuminate\Console\Command;

class ActivateScheduledGroupSessions extends Command
{
    protected $signature = 'group-chat:activate-sessions';
    protected $description = 'Activate due scheduled group sessions';

    public function handle(GroupSessionService $service): int
    {
        GroupSession::where('status', 'scheduled')->where('starts_at', '<=', now())
            ->orderBy('id')->chunkById(100, fn ($sessions) => $sessions->each(fn ($session) => $service->start($session, (int) $session->created_by)));
        return self::SUCCESS;
    }
}
