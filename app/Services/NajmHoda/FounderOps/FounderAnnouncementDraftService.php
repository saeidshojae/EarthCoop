<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\FounderAnnouncementDraft;
use Illuminate\Validation\ValidationException;

class FounderAnnouncementDraftService
{
    /** @param array<string,mixed> $attributes */
    public function draft(array $attributes,?string $reasonCode=null,?int $actorId=null): array
    {
        $title=trim((string)($attributes['title']??''));
        $content=trim((string)($attributes['content']??''));
        $groupLevel=trim((string)($attributes['group_level']??''));
        if($title===''||$content===''||$groupLevel===''){
            throw ValidationException::withMessages(['announcement'=>'Title, content and group level are required.']);
        }

        if($reasonCode){
            $existing=FounderAnnouncementDraft::query()->where('status','draft')->where('reason_code',$reasonCode)->first();
            if($existing)return ['success'=>true,'status'=>'already_prepared','draft_id'=>$existing->id];
        }

        $draft=FounderAnnouncementDraft::query()->create([
            'title'=>mb_substr($title,0,255),
            'content'=>$content,
            'group_level'=>mb_substr($groupLevel,0,64),
            'image'=>$attributes['image']??null,
            'should_pin'=>(bool)($attributes['should_pin']??false),
            'status'=>'draft','reason_code'=>$reasonCode,'created_by'=>$actorId,
        ]);

        return ['success'=>true,'status'=>'drafted','draft_id'=>$draft->id];
    }
}
