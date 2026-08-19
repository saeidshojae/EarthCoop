<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\Blog;
use App\Models\FounderContentDraft;
use App\Services\GroupChat\GroupFeedService;
use App\Services\GroupChat\HtmlSanitizer;
use Illuminate\Support\Facades\DB;

class FounderContentDecisionService
{
    public function __construct(protected FounderActionRequestService $requests, protected FounderApprovalVerifierService $approvals) {}

    public function requestPublish(FounderContentDraft $draft, int $actorId): array
    {
        if ($draft->status !== 'draft' || ! $draft->group_id) return ['success'=>false,'status'=>'invalid_state'];
        return $this->requests->prepare('blog','publish_post',[
            'entity_type'=>'founder_content_draft','entity_id'=>$draft->id,'requested_by'=>$actorId,
            'reason_code'=>$draft->reason_code ?: substr(hash('sha256','blog|'.$draft->id),0,20),
        ]);
    }

    public function decideAndExecute(string $requestId, string $decision, int $actorId, ?string $reason = null): array
    {
        $approval=$this->approvals->decide($requestId,$decision,$actorId,$reason);
        if (! ($approval['success'] ?? false) || $decision !== 'approve') return $approval;
        $draftId=(int)data_get($approval,'request.context.entity_id',0);
        $draft=FounderContentDraft::query()->whereKey($draftId)->where('status','draft')->first();
        if (! $draft || ! $draft->group_id) return ['success'=>false,'status'=>'not_found'];

        $sanitized=app(HtmlSanitizer::class)->sanitize($draft->body);
        $blog=DB::transaction(function () use ($draft,$actorId,$sanitized) {
            $blog=Blog::query()->create(['title'=>$draft->title,'content'=>$sanitized,'user_id'=>$actorId,'group_id'=>$draft->group_id,'category_id'=>$draft->category_id]);
            app(GroupFeedService::class)->record((int)$draft->group_id,'post',(int)$blog->id,$actorId,$blog->created_at);
            return $blog;
        });
        $draft->forceFill(['status'=>'published','approved_by'=>$actorId,'published_at'=>now()])->save();
        return ['success'=>true,'status'=>'executed','draft_id'=>$draft->id,'blog_id'=>$blog->id];
    }
}
