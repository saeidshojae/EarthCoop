<?php
namespace App\Services\NajmHoda\FounderOps;
use App\Models\Blog;use App\Models\FounderContentDraft;use App\Services\GroupChat\GroupFeedService;use App\Services\GroupChat\HtmlSanitizer;use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;use Illuminate\Support\Facades\DB;
class FounderContentDecisionService
{
 public function __construct(protected FounderActionRequestService $requests,protected FounderActionExecutionService $execution,protected NajmHodaAutonomyApprovalService $approvals){}
 public function requestPublish(FounderContentDraft $draft,int $actorId):array{if($draft->status!=='draft'||!$draft->group_id)return ['success'=>false,'status'=>'invalid_state'];return $this->requests->prepare('blog','publish_post',['entity_type'=>'founder_content_draft','entity_id'=>$draft->id,'requested_by'=>$actorId,'reason_code'=>$draft->reason_code?:'blog-draft-'.$draft->id]);}
 public function decideAndExecute(string $requestId,string $decision,int $founderId,?string $reason=null):array{
  if(!in_array($founderId,$this->founderIds(),true))return ['success'=>false,'status'=>'forbidden','reason'=>'founder_not_authorized'];$pending=collect($this->approvals->pending(200))->first(fn(array $i):bool=>(string)($i['id']??'')===$requestId);
  if(!is_array($pending)||(string)data_get($pending,'plan_item.domain')!=='blog'||(string)data_get($pending,'plan_item.domain_action')!=='publish_post'||(string)data_get($pending,'context.entity_type')!=='founder_content_draft')return ['success'=>false,'status'=>'invalid_request'];
  $draft=FounderContentDraft::query()->whereKey((int)data_get($pending,'context.entity_id',0))->where('status','draft')->first();if(!$draft||!$draft->group_id)return ['success'=>false,'status'=>'not_found'];$decided=$this->approvals->decide($requestId,$decision,$founderId,$reason);if(!($decided['success']??false))return $decided;if($decision==='reject'){$draft->update(['status'=>'rejected']);return ['success'=>true,'status'=>'rejected','draft_id'=>$draft->id];}
  return $this->execution->execute('blog','publish_post',function()use($draft,$founderId){$sanitized=app(HtmlSanitizer::class)->sanitize($draft->body);$blog=DB::transaction(function()use($draft,$founderId,$sanitized){$blog=Blog::query()->create(['title'=>$draft->title,'content'=>$sanitized,'user_id'=>$founderId,'group_id'=>$draft->group_id,'category_id'=>$draft->category_id]);app(GroupFeedService::class)->record((int)$draft->group_id,'post',(int)$blog->id,$founderId,$blog->created_at);return $blog;});$draft->update(['status'=>'published','approved_by'=>$founderId,'published_at'=>now()]);return ['draft_id'=>$draft->id,'blog_id'=>$blog->id];},$requestId,['entity_type'=>'founder_content_draft','entity_id'=>$draft->id,'requested_by'=>$founderId]);
 }
 protected function founderIds():array{return array_values(array_filter(array_map('intval',(array)config('najm-hoda-founder-action-policy.founder_approval.user_ids',[]))));}
}
