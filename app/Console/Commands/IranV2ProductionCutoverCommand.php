<?php

namespace App\Console\Commands;

use App\Services\LocationGovernance\IranV2ProductionCutoverService;
use Illuminate\Console\Command;
use Throwable;

final class IranV2ProductionCutoverCommand extends Command
{
    protected $signature = 'location:iran-v2-production-cutover
        {--dry-run : Inspect the complete v1 -> v2 Production cutover without writing}
        {--apply : Stage v2 topology, migrate verified live dependencies, and activate v2 runtime}
        {--confirm= : Exact confirmation required for apply}';

    protected $description = 'Fail-closed final Iran 1404 v2 Production cutover with explicit activation boundary.';

    public function handle(IranV2ProductionCutoverService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $apply = (bool) $this->option('apply');

        if ($dryRun === $apply) {
            $this->error('Choose exactly one of --dry-run or --apply.');
            return self::FAILURE;
        }

        if ($apply) {
            $expected = 'CUTOVER-IR-1404-V2-PRODUCTION';
            if ((string) $this->option('confirm') !== $expected) {
                $this->error('Iran v2 Production cutover requires the exact confirmation token.');
                return self::FAILURE;
            }
            if (! app()->environment(['production', 'local', 'testing'])) {
                $this->error('Iran v2 Production cutover is not permitted in this environment.');
                return self::FAILURE;
            }
        }

        try {
            if ($dryRun) {
                $report = $service->preflight();
                $this->renderPreflight($report);

                return $report['ready'] ? self::SUCCESS : self::FAILURE;
            }

            $result = $service->apply();
            $this->info('Iran 1404 v2 Production cutover completed.');
            $this->line('runtime_active: '.($result['runtime_active'] ? 'YES' : 'NO'));
            $this->line('topology create: '.(int) $result['topology']['create']);
            $this->line('topology update: '.(int) $result['topology']['update']);
            $this->line('topology conflict: '.(int) $result['topology']['conflict']);
            $this->line('topology unchanged: '.(int) $result['topology']['unchanged']);
            foreach ($result['migrated'] as $key => $count) {
                $this->line('migrated '.$key.': '.(int) $count);
            }

            $post = $service->preflight();
            $this->line('post runtime_active: '.($post['runtime_active'] ? 'YES' : 'NO'));
            $this->line('post blocker_total: '.(int) $post['blocker_total']);

            return $result['runtime_active'] ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    private function renderPreflight(array $report): void
    {
        $this->info('Iran v1 -> v2 final cutover preflight (READ ONLY)');
        $this->line('runtime_active: '.($report['runtime_active'] ? 'YES' : 'NO'));
        $this->line('reference_v2_count: '.(int) $report['reference_v2_count']);
        $this->line('verified_pairs: '.(int) $report['verified_pairs']);
        $this->line('v1_identity_count: '.(int) $report['v1_identity_count']);
        $this->line('unmapped_v1_identity_count: '.(int) $report['unmapped_v1_identity_count']);
        $this->line('topology create: '.(int) $report['topology']['create']);
        $this->line('topology update: '.(int) $report['topology']['update']);
        $this->line('topology conflict: '.(int) $report['topology']['conflict']);
        $this->line('topology unchanged: '.(int) $report['topology']['unchanged']);
        foreach ($report['blockers'] as $key => $count) {
            $this->line('blocker '.$key.': '.(int) $count);
        }
        $this->line('blocker_total: '.(int) $report['blocker_total']);
        $this->line('READY_FOR_FINAL_CUTOVER: '.($report['ready'] ? 'YES' : 'NO'));
        $this->warn('Dry run only. No database writes or runtime activation are performed.');
    }
}
