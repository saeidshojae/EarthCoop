<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\FounderAnnouncementDraft;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use App\Services\Notifications\AnnouncementManagementService;

class FounderAnnouncementDecisionService
{
    public function __construct(
        protected FounderActionRequestService $requests,
        protected FounderActionExecutionService $execution,
        protected NajmHodaAutonomyApprovalService $approvals,
        protected AnnouncementManagementService $announcements
    ) {}

    public function requestPublish(FounderAnnouncementDraft $draft,int $actorId): array
    {
        if($draft->status!=='draft')return ['success'=>false,'status'=>'invalid_state'];
        return $this->requests->prepare('notifications','publish_announcement',[
            'entity_type'=>'founder_announcement_draft','entity_id'=>$draft->id,'requested_by'=>$actorId,
            'reason_code'=>$draft->reason_code?:'announcement-draft-'.$draft->id,
        ]);
    }

    public function decideAndExecute(string $requestId,string $decision,int $founderId,?string $reason=null): array
    {
        if(!in_array($founderId,$this->founderIds(),true))return ['success'=>false,'status'=>'forbidden','reason'=>'founder_not_authorized'];
        $pending=collect($this->approvals->pending(200))->first(fn(array $i):bool=>(string)($i['id']??'')===$requestId);
        if(!is_array($pending)||(string)data_get($pending,'plan_item.domain')!=='notifications'||(string)data_get($pending,'plan_item.domain_action')!=='publish_announcement'||(string)data_get($pending,'context.entity_type')!=='founder_announcement_draft')return ['success'=>false,'status'=>'invalid_request'];

        $draft=FounderAnnouncementDraft::query()->whereKey((int)data_get($pending,'context.entity_id',0))->where('status','draft')->first();
        if(!$draft)return ['success'=>false,'status'=>'not_found'];

        $decided=$this->approvals->decide($requestId,$decision,$founderId,$reason);
        if(!($decided['success']??false))return $decided;
        if($decision==='reject'){
            $draft->update(['status'=>'rejected']);
            return ['success'=>true,'status'=>'rejected','draft_id'=>$draft->id];
        }

        return $this->execution->execute('notifications','publish_announcement',function()use($draft,$founderId){
            $announcement=$this->announcements->create([
                'title'=>$draft->title,'content'=>$draft->content,'group_level'=>$draft->group_level,
                'image'=>$draft->image,'should_pin'=>(bool)$draft->should_pin,
            ],$founderId);
            $draft->update([
                'status'=>'published','approved_by'=>$founderId,'announcement_id'=>$announcement->id,'published_at'=>now(),
            ]);
            return ['draft_id'=>$draft->id,'announcement_id'=>$announcement->id];
        },$requestId,['entity_type'=>'founder_announcement_draft','entity_id'=>$draft->id,'requested_by'=>$founderId]);
    }

    protected function founderIds(): array
    {
        return array_values(array_filter(array_map('intval',(array)config('najm-hoda-founder-action-policy.founder_approval.user_ids',[]))));
    }
}
