<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\Category;
use App\Models\FounderContentDraft;
use App\Services\Blog\BlogPublicationService;
use App\Services\GroupChat\HtmlSanitizer;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use App\Services\SystemIdentityService;

class FounderContentDecisionService
{
    public function __construct(
        protected FounderActionRequestService $requests,
        protected FounderActionExecutionService $execution,
        protected NajmHodaAutonomyApprovalService $approvals,
        protected BlogPublicationService $blogs,
        protected HtmlSanitizer $sanitizer,
        protected SystemIdentityService $systemIdentities
    ) {}

    public function requestPublish(FounderContentDraft $draft,int $actorId): array
    {
        if($draft->status!=='draft'||!$draft->group_id)return ['success'=>false,'status'=>'invalid_state','reason'=>'group_required'];
        if(!$this->hasValidCategory($draft))return ['success'=>false,'status'=>'invalid_state','reason'=>'category_required'];
        return $this->requests->prepare('blog','publish_post',[
            'entity_type'=>'founder_content_draft','entity_id'=>$draft->id,'requested_by'=>$actorId,
            'reason_code'=>$draft->reason_code?:'blog-draft-'.$draft->id,
        ]);
    }

    public function decideAndExecute(string $requestId,string $decision,int $founderId,?string $reason=null): array
    {
        if(!in_array($founderId,$this->founderIds(),true))return ['success'=>false,'status'=>'forbidden','reason'=>'founder_not_authorized'];
        $pending=collect($this->approvals->pending(200))->first(fn(array $i):bool=>(string)($i['id']??'')===$requestId);
        if(!is_array($pending)||(string)data_get($pending,'plan_item.domain')!=='blog'||(string)data_get($pending,'plan_item.domain_action')!=='publish_post'||(string)data_get($pending,'context.entity_type')!=='founder_content_draft')return ['success'=>false,'status'=>'invalid_request'];

        $draft=FounderContentDraft::query()->whereKey((int)data_get($pending,'context.entity_id',0))->where('status','draft')->first();
        if(!$draft||!$draft->group_id)return ['success'=>false,'status'=>'not_found'];
        if(!$this->hasValidCategory($draft))return ['success'=>false,'status'=>'invalid_state','reason'=>'category_required'];

        $decided=$this->approvals->decide($requestId,$decision,$founderId,$reason);
        if(!($decided['success']??false))return $decided;
        if($decision==='reject'){
            $draft->update(['status'=>'rejected']);
            return ['success'=>true,'status'=>'rejected','draft_id'=>$draft->id];
        }

        $management = $this->systemIdentities->management();

        return $this->execution->execute('blog','publish_post',function()use($draft,$management,$founderId){
            $blog=$this->blogs->create([
                'title'=>$draft->title,
                'content'=>$this->sanitizer->sanitize($draft->body),
                'group_id'=>(int)$draft->group_id,
                'category_id'=>(int)$draft->category_id,
            ],(int)$management->id);
            $draft->update(['status'=>'published','approved_by'=>$founderId,'published_at'=>now()]);
            return ['draft_id'=>$draft->id,'blog_id'=>$blog->id,'publisher_identity_id'=>(int)$management->id];
        },$requestId,['entity_type'=>'founder_content_draft','entity_id'=>$draft->id,'requested_by'=>$founderId]);
    }

    protected function hasValidCategory(FounderContentDraft $draft): bool
    {
        $categoryId = (int) ($draft->category_id ?? 0);
        return $categoryId > 0 && Category::query()->whereKey($categoryId)->exists();
    }

    protected function founderIds(): array
    {
        return array_values(array_filter(array_map('intval',(array)config('najm-hoda-founder-action-policy.founder_approval.user_ids',[]))));
    }
}
