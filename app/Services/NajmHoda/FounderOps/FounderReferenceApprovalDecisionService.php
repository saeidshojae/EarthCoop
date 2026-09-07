<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use App\Services\ReferenceData\ReferenceDataApprovalService;

class FounderReferenceApprovalDecisionService
{
    public function __construct(
        protected FounderActionRequestService $requests,
        protected FounderActionExecutionService $execution,
        protected NajmHodaAutonomyApprovalService $approvals,
        protected ReferenceDataApprovalService $references
    ) {}

    /** @return array<string,mixed> */
    public function requestApprove(string $type, int $id, int $requestedBy): array
    {
        $domain = in_array($type, ['experience','occupational'], true) ? 'reference_data' : 'locations';
        $this->references->find($type, $id);

        return $this->requests->prepare($domain, 'approve', [
            'entity_type' => 'reference_candidate_' . $type,
            'entity_id' => $id,
            'requested_by' => $requestedBy,
            'reason_code' => 'approve-' . $type . '-' . $id,
            'source_event' => 'founder_ops_reference_candidate',
        ]);
    }

    /** @return array<string,mixed> */
    public function decideAndExecute(string $requestId, string $decision, int $founderId, ?string $reason = null): array
    {
        if (! in_array($founderId, $this->founderIds(), true)) {
            return ['success'=>false,'status'=>'forbidden','reason'=>'founder_not_authorized'];
        }

        $pending = collect($this->approvals->pending(200))->first(fn(array $item): bool => (string)($item['id']??'')===$requestId);
        if (! is_array($pending)) return ['success'=>false,'status'=>'not_found','reason'=>'approval_request_not_pending'];

        $entityType=(string)data_get($pending,'context.entity_type','');
        if (! str_starts_with($entityType,'reference_candidate_') || (string)data_get($pending,'plan_item.domain_action')!=='approve') {
            return ['success'=>false,'status'=>'invalid_request','reason'=>'approval_contract_mismatch'];
        }
        $type=substr($entityType,strlen('reference_candidate_'));
        $id=(int)data_get($pending,'context.entity_id',0);
        $domain=in_array($type,['experience','occupational'],true)?'reference_data':'locations';
        if ((string)data_get($pending,'plan_item.domain')!==$domain || $id<=0) {
            return ['success'=>false,'status'=>'invalid_request','reason'=>'approval_context_mismatch'];
        }

        $decisionResult=$this->approvals->decide($requestId,$decision,$founderId,$reason);
        if (! (bool)($decisionResult['success']??false)) return $decisionResult;
        if ($decision==='reject') {
            return ['success'=>true,'status'=>'rejected_request_only','type'=>$type,'id'=>$id];
        }

        return $this->execution->execute(
            $domain,'approve',
            fn()=> $this->references->approve($type,$id),
            $requestId,
            ['entity_type'=>$entityType,'entity_id'=>$id,'requested_by'=>$founderId]
        );
    }

    /** @return array<int,int> */
    protected function founderIds(): array
    {
        return array_values(array_filter(array_map('intval',(array)config('najm-hoda-founder-action-policy.founder_approval.user_ids',[]))));
    }
}
