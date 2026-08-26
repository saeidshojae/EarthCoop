<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\ModerationCaseSummary;
use App\Services\Moderation\ReportManagementService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;

class FounderModerationDecisionService
{
    public function __construct(
        protected FounderActionRequestService $requests,
        protected FounderActionExecutionService $execution,
        protected NajmHodaAutonomyApprovalService $approvals,
        protected ReportManagementService $reports
    ) {}

    /** @return array<string,mixed> */
    public function requestResolve(string $sourceType, int $sourceId, int $requestedBy): array
    {
        $this->reports->find($sourceType, $sourceId);
        return $this->requests->prepare('reports_moderation', 'resolve_report', [
            'entity_type'=>$sourceType,'entity_id'=>$sourceId,'requested_by'=>$requestedBy,
            'reason_code'=>'resolve-'.$sourceType.'-'.$sourceId,
            'source_event'=>'founder_ops_moderation_case',
        ]);
    }

    /** @return array<string,mixed> */
    public function decideAndExecute(string $requestId, string $decision, int $founderId, ?string $reason = null): array
    {
        if (! in_array($founderId, $this->founderIds(), true)) {
            return ['success'=>false,'status'=>'forbidden','reason'=>'founder_not_authorized'];
        }

        $pending = collect($this->approvals->pending(200))
            ->first(fn(array $item): bool => (string)($item['id'] ?? '') === $requestId);
        if (! is_array($pending)) return ['success'=>false,'status'=>'not_found','reason'=>'approval_request_not_pending'];

        $sourceType = (string) data_get($pending,'context.entity_type','');
        $sourceId = (int) data_get($pending,'context.entity_id',0);
        if (! in_array($sourceType,['report','reported_message'],true) || $sourceId <= 0
            || (string)data_get($pending,'plan_item.domain') !== 'reports_moderation'
            || (string)data_get($pending,'plan_item.domain_action') !== 'resolve_report') {
            return ['success'=>false,'status'=>'invalid_request','reason'=>'approval_contract_mismatch'];
        }

        $decisionResult = $this->approvals->decide($requestId,$decision,$founderId,$reason);
        if (! (bool)($decisionResult['success'] ?? false)) return $decisionResult;
        if ($decision === 'reject') {
            return ['success'=>true,'status'=>'rejected_request_only','source_type'=>$sourceType,'source_id'=>$sourceId];
        }

        $result = $this->execution->execute(
            'reports_moderation','resolve_report',
            fn()=> $this->reports->resolve($sourceType,$sourceId,$founderId,$reason),
            $requestId,
            ['entity_type'=>$sourceType,'entity_id'=>$sourceId,'requested_by'=>$founderId]
        );

        if ((bool)($result['success'] ?? false)) {
            ModerationCaseSummary::query()
                ->where('source_type',$sourceType)->where('source_id',$sourceId)->where('status','draft')
                ->update(['status'=>'resolved','updated_at'=>now()]);
        }
        return $result;
    }

    /** @return array<int,int> */
    protected function founderIds(): array
    {
        return array_values(array_filter(array_map('intval',(array)config('najm-hoda-founder-action-policy.founder_approval.user_ids',[]))));
    }
}
