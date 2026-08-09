<?php

namespace App\Console\Commands;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Services\IdleCapitalObservationService;
use Illuminate\Console\Command;

class NajmBaharObserveIdleCapital extends Command
{
    protected $signature = 'najm-bahar:observe-idle-capital {--user=} {--record : Persist assessment snapshots}';

    protected $description = 'Observe active-money inactivity without charging tax or moving funds.';

    public function handle(IdleCapitalObservationService $service): int
    {
        $query = Account::query()
            ->where('type', 'user')
            ->whereNotNull('user_id')
            ->orderBy('id');

        if ($userId = $this->option('user')) {
            $query->where('user_id', (int) $userId);
        }

        $count = 0;
        $candidates = 0;

        $query->chunkById(200, function ($accounts) use ($service, &$count, &$candidates) {
            foreach ($accounts as $account) {
                $observation = $service->observeUser((int) $account->user_id);
                $count++;
                if ($observation['is_idle_candidate']) {
                    $candidates++;
                }

                if ($this->option('record')) {
                    $service->recordUser((int) $account->user_id, $observation['as_of']);
                }

                $this->line(sprintf(
                    'user=%d active=%d candidate=%d idle=%s',
                    $account->user_id,
                    $observation['active_balance_gol'],
                    $observation['idle_candidate_gol'],
                    $observation['is_idle_candidate'] ? 'yes' : 'no'
                ));
            }
        });

        $this->info("Observed {$count} member wallets; {$candidates} are idle candidates. No tax was charged.");

        return self::SUCCESS;
    }
}
