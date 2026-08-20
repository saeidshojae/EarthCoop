<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Services\StockPayeeAccountService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;

class FounderStockPayeeDecisionService
{
    public function __construct(
        protected FounderActionRequestService $requests,
        protected FounderActionExecutionService $execution,
        protected NajmHodaAutonomyApprovalService $approvals,
        protected StockPayeeAccountService $payees,
    ) {}

    public function requestConfigure(int $stockId,int $accountId,int $requestedBy): array
    {
        $stock=Stock::query()->findOrFail($stockId);
        $account=Account::query()->findOrFail($accountId);
        // Validate the proposed pair without persisting it.
        if ((string)$stock->issuer_type !== 'project') {
            return ['success'=>false,'status'=>'invalid_stock','reason'=>'payee_mapping_only_for_project_stock'];
        }
        if (!(bool)$account->status || !in_array((string)$account->type,['legal_entity','central'],true)) {
            return ['success'=>false,'status'=>'invalid_account','reason'=>'project_payee_requires_active_non_personal_account'];
        }

        return $this->requests->prepare('stock','configure_payee_account',[
            'entity_type'=>'stock_payee_mapping',
            'entity_id'=>$stock->id,
            'account_id'=>$account->id,
            'requested_by'=>$requestedBy,
            'reason_code'=>'stock-payee-'.$stock->id.'-'.$account->id,
            'source_event'=>'founder_ops_stock_payee_mapping',
        ]);
    }

    public function decideAndExecute(string $requestId,string $decision,int $founderId,?string $reason=null): array
    {
        if(!in_array($founderId,$this->founderIds(),true)){
            return ['success'=>false,'status'=>'forbidden','reason'=>'founder_not_authorized'];
        }
        $pending=collect($this->approvals->pending(200))->first(fn(array $item): bool => (string)($item['id']??'')===$requestId);
        if(!is_array($pending)) return ['success'=>false,'status'=>'not_found','reason'=>'approval_request_not_pending'];

        if((string)data_get($pending,'plan_item.domain')!=='stock'
            || (string)data_get($pending,'plan_item.domain_action')!=='configure_payee_account'
            || (string)data_get($pending,'context.entity_type')!=='stock_payee_mapping'){
            return ['success'=>false,'status'=>'invalid_request','reason'=>'approval_contract_mismatch'];
        }

        $stockId=(int)data_get($pending,'context.entity_id',0);
        $accountId=(int)data_get($pending,'context.account_id',0);
        if($stockId<=0||$accountId<=0) return ['success'=>false,'status'=>'invalid_request','reason'=>'approval_context_missing'];

        $decisionResult=$this->approvals->decide($requestId,$decision,$founderId,$reason);
        if(!(bool)($decisionResult['success']??false)) return $decisionResult;
        if($decision==='reject') return ['success'=>true,'status'=>'rejected_request_only','stock_id'=>$stockId,'account_id'=>$accountId];

        return $this->execution->execute(
            'stock','configure_payee_account',
            function() use($stockId,$accountId,$founderId){
                $stock=Stock::query()->findOrFail($stockId);
                $account=Account::query()->findOrFail($accountId);
                $mapping=$this->payees->configureProject($stock,$account,$founderId,['source'=>'founder_ops']);
                return ['mapping_id'=>$mapping->id,'stock_id'=>$stockId,'account_id'=>$accountId];
            },
            $requestId,
            ['entity_type'=>'stock_payee_mapping','entity_id'=>$stockId,'account_id'=>$accountId,'requested_by'=>$founderId]
        );
    }

    protected function founderIds(): array
    {
        return array_values(array_filter(array_map('intval',(array)config('najm-hoda-founder-action-policy.founder_approval.user_ids',[]))));
    }
}
