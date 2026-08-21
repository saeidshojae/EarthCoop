<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\FounderFinancialRiskFinding;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\ScheduledTransaction;
use App\Modules\NajmBahar\Models\Transaction;

class FounderNajmBaharRiskService
{
    public function inspectScheduled(ScheduledTransaction $scheduled): array
    {
        $findings=[];
        $status=(string)$scheduled->status;
        $terminal=in_array($status,['completed','executed','cancelled'],true);

        if (! $terminal && $scheduled->execute_at && $scheduled->execute_at->isPast()) {
            $findings[]=$this->finding($scheduled,'scheduled_overdue','high','Scheduled Najm Bahar transaction is overdue.',['status'=>$status,'attempts'=>(int)$scheduled->attempts]);
        }
        if (! $terminal && (int)$scheduled->attempts >= 3) {
            $findings[]=$this->finding($scheduled,'repeated_attempts','high','Scheduled Najm Bahar transaction has repeated execution attempts.',['attempts'=>(int)$scheduled->attempts]);
        }

        $transaction=$scheduled->transaction_id ? Transaction::query()->find($scheduled->transaction_id) : null;
        if ($scheduled->transaction_id && ! $transaction) {
            $findings[]=$this->finding($scheduled,'missing_transaction','high','Scheduled transaction references a missing Najm Bahar transaction.',[]);
        }
        if ($transaction) {
            if ((int)$transaction->amount <= 0) {
                $findings[]=$this->finding($scheduled,'non_positive_amount','high','Referenced Najm Bahar transaction has a non-positive amount.',['transaction_id'=>(int)$transaction->id]);
            }
            if ((string)$transaction->status === 'completed' && LedgerEntry::query()->where('transaction_id',$transaction->id)->count() === 0) {
                $findings[]=$this->finding($scheduled,'completed_without_ledger','critical','Completed Najm Bahar transaction has no ledger entries.',['transaction_id'=>(int)$transaction->id]);
            }
        }

        return ['success'=>true,'status'=>'inspected','scheduled_transaction_id'=>(int)$scheduled->id,'finding_count'=>count($findings),'findings'=>$findings];
    }

    protected function finding(ScheduledTransaction $scheduled,string $code,string $severity,string $summary,array $context): array
    {
        $row=FounderFinancialRiskFinding::query()->updateOrCreate(
            ['domain'=>'najm_bahar','entity_type'=>'scheduled_transaction','entity_id'=>(int)$scheduled->id,'risk_code'=>$code],
            ['severity'=>$severity,'status'=>'open','summary'=>$summary,'context'=>$context,'resolved_at'=>null]
        );
        return ['id'=>(int)$row->id,'risk_code'=>$code,'severity'=>$severity,'summary'=>$summary];
    }
}
