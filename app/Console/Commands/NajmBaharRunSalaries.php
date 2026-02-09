<?php

namespace App\Console\Commands;

use App\Modules\NajmBahar\Services\SalaryService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class NajmBaharRunSalaries extends Command
{
    protected $signature = 'najm-bahar:run-salaries {--date=} {--process}';
    protected $description = 'Create Najm Bahar salary run items and optionally process payments.';

    public function handle(SalaryService $salaryService): int
    {
        $dateOption = $this->option('date');
        $runDate = $dateOption ? Carbon::parse($dateOption) : Carbon::today();

        $run = $salaryService->createRun($runDate);
        $this->info('Salary run created: ' . $run->id);

        if ($this->option('process')) {
            $result = $salaryService->processRun($run);
            $this->info('Processed: ' . $result['processed'] . ' | Blocked: ' . $result['blocked'] . ' | Failed: ' . $result['failed']);
        }

        return self::SUCCESS;
    }
}
