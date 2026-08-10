<?php

namespace App\Console\Commands;

use App\Jobs\PublishGroupChatOutbox;
use App\Models\GroupChatOutbox;
use Illuminate\Console\Command;

class DispatchGroupChatOutbox extends Command
{
    protected $signature = 'group-chat:dispatch-outbox {--limit=200}';
    protected $description = 'Dispatch pending group chat outbox records to the realtime queue.';

    public function handle(): int
    {
        if (! config('group-chat.features.realtime_envelope_v1', false)) {
            $this->warn('Realtime envelope feature is disabled.');
            return self::SUCCESS;
        }
        $rows = GroupChatOutbox::where('status', 'pending')->where('available_at', '<=', now())
            ->orderBy('id')->limit(min(1000, max(1, (int) $this->option('limit'))))->pluck('id');
        foreach ($rows as $id) PublishGroupChatOutbox::dispatch((int) $id);
        $this->info('Dispatched ' . $rows->count() . ' outbox record(s).');
        return self::SUCCESS;
    }
}
