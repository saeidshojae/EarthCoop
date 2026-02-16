<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Services\NajmHoda\NajmHodaGroupAssistantService;
use Illuminate\Console\Command;

class NajmHodaBootstrapGroups extends Command
{
    protected $signature = 'najm-hoda:bootstrap-groups {--chunk=200 : Chunk size for processing groups}';

    protected $description = 'Bootstrap Najm Hoda group assistant for existing groups';

    public function __construct(private NajmHodaGroupAssistantService $assistant)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $chunk = max(50, (int) $this->option('chunk'));

        $bot = $this->assistant->ensureBotUser();
        $this->info('Najm Hoda bot user: #' . $bot->id . ' (' . $bot->email . ')');

        $count = 0;
        Group::query()->orderBy('id')->chunk($chunk, function ($groups) use (&$count) {
            foreach ($groups as $group) {
                $this->assistant->ensureGroupAssistantSetup($group);
                $count++;
            }
        });

        $this->info("Bootstrapped {$count} groups.");
        return self::SUCCESS;
    }
}
