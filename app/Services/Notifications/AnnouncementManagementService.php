<?php

namespace App\Services\Notifications;

use App\Models\Announcement;
use App\Models\Group;
use App\Models\Message;
use App\Models\PinnedMessage;
use Illuminate\Support\Facades\DB;

class AnnouncementManagementService
{
    /** @param array<string,mixed> $attributes */
    public function create(array $attributes, int $actorId): Announcement
    {
        $payload=$this->normalized($attributes);
        $payload['created_by']=$actorId;

        return DB::transaction(function() use($payload,$actorId): Announcement {
            $announcement=Announcement::query()->create($payload);
            if((bool)$announcement->should_pin)$this->syncPins($announcement,$actorId);
            return $announcement->refresh();
        });
    }

    /** @param array<string,mixed> $attributes */
    public function update(Announcement $announcement,array $attributes,int $actorId): Announcement
    {
        $payload=$this->normalized($attributes,$announcement);

        return DB::transaction(function() use($announcement,$payload,$actorId): Announcement {
            $announcement->fill($payload)->save();
            $this->removePinsAndGeneratedMessages($announcement);
            if((bool)$announcement->should_pin)$this->syncPins($announcement,$actorId);
            return $announcement->refresh();
        });
    }

    public function unpin(Announcement $announcement): Announcement
    {
        return DB::transaction(function() use($announcement): Announcement {
            $this->removePinsAndGeneratedMessages($announcement);
            $announcement->forceFill(['should_pin'=>false])->save();
            return $announcement->refresh();
        });
    }

    public function delete(Announcement $announcement): void
    {
        DB::transaction(function() use($announcement): void {
            $this->removePinsAndGeneratedMessages($announcement);
            $announcement->delete();
        });
    }

    protected function syncPins(Announcement $announcement,int $actorId): void
    {
        Group::query()->where('location_level',$announcement->group_level)->orderBy('id')->chunkById(200,function($groups)use($announcement,$actorId):void{
            foreach($groups as $group){
                $body=(string)$announcement->content;
                if($announcement->image)$body.="\n\n📷 تصویر اطلاعیه: ".asset($announcement->image);
                $message=Message::query()->create(['group_id'=>$group->id,'user_id'=>$actorId,'message'=>$body]);
                PinnedMessage::query()->create([
                    'message_id'=>$message->id,'group_id'=>$group->id,'pinned_by'=>$actorId,
                    'announcement_id'=>$announcement->id,
                ]);
            }
        });
    }

    protected function removePinsAndGeneratedMessages(Announcement $announcement): void
    {
        $pins=PinnedMessage::query()->where('announcement_id',$announcement->id)->get(['id','message_id']);
        $messageIds=$pins->pluck('message_id')->filter()->map(fn($id)=>(int)$id)->unique()->values()->all();
        if($pins->isNotEmpty())PinnedMessage::query()->whereIn('id',$pins->pluck('id')->all())->delete();
        if($messageIds!==[])Message::query()->whereIn('id',$messageIds)->delete();
    }

    /** @param array<string,mixed> $attributes @return array<string,mixed> */
    protected function normalized(array $attributes,?Announcement $current=null): array
    {
        return [
            'title'=>trim((string)($attributes['title']??$current?->title??'')),
            'content'=>trim((string)($attributes['content']??$current?->content??'')),
            'group_level'=>(string)($attributes['group_level']??$current?->group_level??''),
            'image'=>$attributes['image']??$current?->image,
            'should_pin'=>(bool)($attributes['should_pin']??$current?->should_pin??false),
        ];
    }
}
