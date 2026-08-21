<?php

namespace App\Console\Commands;

use App\Services\NajmHoda\FounderOps\FounderExecutiveConnectivityService;
use Illuminate\Console\Command;

class NajmHodaExecutiveConnectivityAudit extends Command
{
    protected $signature='najm-hoda:executive-connectivity {--fail-on-gap : Exit non-zero when executable action gaps remain}';
    protected $description='Audit real Founder Ops executive connectivity against authority policy';

    public function handle(FounderExecutiveConnectivityService $connectivity): int
    {
        $report=$connectivity->report(); $summary=(array)($report['summary']??[]);
        $this->info('Najm Hoda Executive Connectivity');
        $this->line('Domains: '.(int)($summary['domains']??0));
        $this->line('Read-connected: '.(int)($summary['read_connected']??0));
        $this->line('Managed: '.(int)($summary['managed']??0).' | Partial: '.(int)($summary['partial']??0).' | Observed-only: '.(int)($summary['observed_only']??0));
        $this->line('Executable gaps: '.(int)($summary['missing_executable_actions']??0));
        $this->line('Executive coverage: '.number_format((float)($summary['executive_coverage_percent']??0),2).'%');

        $rows=[];
        foreach((array)($report['rollout_queue']??[]) as $item){$rows[]=[(string)($item['domain']??''),(int)($item['priority']??0),(string)($item['risk']??''),implode(', ',(array)($item['missing_actions']??[]))];}
        if($rows!==[])$this->table(['Domain','Priority','Risk','Missing executable actions'],$rows);

        $gaps=(int)($summary['missing_executable_actions']??0);
        if($this->option('fail-on-gap') && $gaps>0){$this->error('Executive connectivity gaps remain.');return self::FAILURE;}
        return self::SUCCESS;
    }
}
