<?php

namespace App\Console\Commands;

use App\Modules\Stock\Services\StockCanonicalReadinessService;
use Illuminate\Console\Command;

class StockCanonicalReadiness extends Command
{
    protected $signature='stock:canonical-readiness {--fail-on-blocker : Return failure when any blocker exists}';
    protected $description='Audit Stock canonical cutover readiness and list blockers/warnings';

    public function handle(StockCanonicalReadinessService $service): int
    {
        $audit=$service->audit();
        $summary=(array)($audit['summary']??[]);

        $this->info('Stock canonical readiness');
        $this->line('Ready: '.(($audit['ready']??false)?'YES':'NO'));
        $this->line('Canonical auctions: '.(int)($summary['canonical_auctions']??0));
        $this->line('Blockers: '.(int)($summary['blockers']??0));
        $this->line('Warnings: '.(int)($summary['warnings']??0));

        foreach((array)($audit['blockers']??[]) as $item){
            $this->error('[BLOCKER] '.($item['code']??'unknown').' — '.($item['message']??''));
            if(!empty($item['context'])) $this->line(json_encode($item['context'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        }
        foreach((array)($audit['warnings']??[]) as $item){
            $this->warn('[WARNING] '.($item['code']??'unknown').' — '.($item['message']??''));
            if(!empty($item['context'])) $this->line(json_encode($item['context'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        }

        if($this->option('fail-on-blocker') && !($audit['ready']??false)) return self::FAILURE;
        return self::SUCCESS;
    }
}
