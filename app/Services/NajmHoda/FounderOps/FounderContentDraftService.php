<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\FounderContentDraft;
use Illuminate\Validation\ValidationException;

class FounderContentDraftService
{
    public function draft(string $title, string $body, ?int $groupId = null, ?int $categoryId = null, ?string $reasonCode = null, ?int $actorId = null): array
    {
        if (blank($title) || blank($body)) throw ValidationException::withMessages(['content'=>'Title and body are required.']);
        if ($reasonCode) {
            $existing = FounderContentDraft::query()->where('status','draft')->where('reason_code',$reasonCode)->first();
            if ($existing) return ['success'=>true,'status'=>'already_prepared','draft_id'=>$existing->id];
        }
        $draft=FounderContentDraft::query()->create([
            'content_type'=>'blog_post','group_id'=>$groupId,'category_id'=>$categoryId,'title'=>$title,'body'=>$body,
            'status'=>'draft','reason_code'=>$reasonCode,'created_by'=>$actorId,
        ]);
        return ['success'=>true,'status'=>'drafted','draft_id'=>$draft->id];
    }
}
