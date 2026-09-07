<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\Invitation;
use App\Services\Invitation\InvitationManagementService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;

class FounderInvitationDecisionService
{
    public function __construct(
        protected FounderActionRequestService $requests,
        protected FounderActionExecutionService $execution,
        protected NajmHodaAutonomyApprovalService $approvals,
        protected InvitationManagementService $invitations
    ) {}

    public function requestDecision(Invitation $invitation,string $action,int $requestedBy,?string $note=null): array
    {
        if(!in_array($action,['issue_invitation','reject_invitation_request'],true))return ['success'=>false,'status'=>'invalid_action'];
        if((int)$invitation->status!==0)return ['success'=>false,'status'=>'invalid_state','reason'=>'invitation_not_pending'];
        return $this->requests->prepare('invitations',$action,[
            'entity_type'=>'invitation_request','entity_id'=>(int)$invitation->id,'requested_by'=>$requestedBy,
            'reason_code'=>$action.'-'.$invitation->id,'source_event'=>'founder_ops_invitation',
            'admin_note'=>$note,
        ]);
    }

    public function decideAndExecute(string $requestId,string $decision,int $founderId,?string $reason=null): array
    {
        if(!in_array($founderId,$this->founderIds(),true))return ['success'=>false,'status'=>'forbidden','reason'=>'founder_not_authorized'];
        $pending=collect($this->approvals->pending(200))->first(fn(array $item):bool=>(string)($item['id']??'')===$requestId);
        if(!is_array($pending))return ['success'=>false,'status'=>'not_found','reason'=>'approval_request_not_pending'];

        $domain=(string)data_get($pending,'plan_item.domain','');
        $action=(string)data_get($pending,'plan_item.domain_action','');
        $entityType=(string)data_get($pending,'context.entity_type','');
        $id=(int)data_get($pending,'context.entity_id',0);
        if($domain!=='invitations'||!in_array($action,['issue_invitation','reject_invitation_request'],true)||$entityType!=='invitation_request'||$id<=0){
            return ['success'=>false,'status'=>'invalid_request','reason'=>'approval_contract_mismatch'];
        }

        $decisionResult=$this->approvals->decide($requestId,$decision,$founderId,$reason);
        if(!(bool)($decisionResult['success']??false))return $decisionResult;
        if($decision==='reject')return ['success'=>true,'status'=>'rejected_request_only','invitation_id'=>$id,'action'=>$action];

        $invitation=Invitation::query()->find($id);
        if(!$invitation)return ['success'=>false,'status'=>'not_found','reason'=>'invitation_not_found'];
        $note=is_scalar(data_get($pending,'context.admin_note'))?(string)data_get($pending,'context.admin_note'):null;
        return $this->execution->execute(
            'invitations',$action,
            fn()=> $action==='issue_invitation'?$this->invitations->issue($invitation,$founderId):$this->invitations->reject($invitation,$founderId,$note),
            $requestId,
            ['entity_type'=>'invitation_request','entity_id'=>$id,'requested_by'=>$founderId]
        );
    }

    protected function founderIds(): array
    {
        return array_values(array_filter(array_map('intval',(array)config('najm-hoda-founder-action-policy.founder_approval.user_ids',[]))));
    }
}
