<?php
namespace App\Services\NajmHoda\FounderOps;
use App\Models\FounderEmailDraft;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use Illuminate\Support\Facades\Mail;
class FounderEmailDecisionService
{
 public function __construct(protected FounderActionRequestService $requests,protected FounderActionExecutionService $execution,protected NajmHodaAutonomyApprovalService $approvals){}
 public function requestSend(FounderEmailDraft $draft,int $actorId):array{if($draft->status!=='draft')return ['success'=>false,'status'=>'invalid_state'];return $this->requests->prepare('email','send_email',['entity_type'=>'founder_email_draft','entity_id'=>$draft->id,'requested_by'=>$actorId,'reason_code'=>$draft->reason_code?:'email-draft-'.$draft->id]);}
 public function decideAndExecute(string $requestId,string $decision,int $founderId,?string $reason=null):array{
  if(!in_array($founderId,$this->founderIds(),true))return ['success'=>false,'status'=>'forbidden','reason'=>'founder_not_authorized'];
  $pending=collect($this->approvals->pending(200))->first(fn(array $i):bool=>(string)($i['id']??'')===$requestId);
  if(!is_array($pending)||(string)data_get($pending,'plan_item.domain')!=='email'||(string)data_get($pending,'plan_item.domain_action')!=='send_email'||(string)data_get($pending,'context.entity_type')!=='founder_email_draft')return ['success'=>false,'status'=>'invalid_request'];
  $draft=FounderEmailDraft::query()->whereKey((int)data_get($pending,'context.entity_id',0))->where('status','draft')->first();if(!$draft)return ['success'=>false,'status'=>'not_found'];
  $decided=$this->approvals->decide($requestId,$decision,$founderId,$reason);if(!($decided['success']??false))return $decided;if($decision==='reject'){$draft->update(['status'=>'rejected']);return ['success'=>true,'status'=>'rejected','draft_id'=>$draft->id];}
  return $this->execution->execute('email','send_email',function()use($draft,$founderId){foreach((array)$draft->recipients as $recipient){Mail::html($draft->body,function($message)use($recipient,$draft){$message->to($recipient)->subject($draft->subject);});}$draft->update(['status'=>'sent','approved_by'=>$founderId,'sent_at'=>now()]);return ['draft_id'=>$draft->id,'recipient_count'=>count((array)$draft->recipients)];},$requestId,['entity_type'=>'founder_email_draft','entity_id'=>$draft->id,'requested_by'=>$founderId]);
 }
 protected function founderIds():array{return array_values(array_filter(array_map('intval',(array)config('najm-hoda-founder-action-policy.founder_approval.user_ids',[]))));}
}
