<?php

namespace App\Services\Blog;

use App\Models\Blog;
use App\Services\GroupChat\GroupFeedService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BlogPublicationService
{
    public function __construct(protected GroupFeedService $feed) {}

    /** @param array<string,mixed> $attributes */
    public function create(array $attributes, int $actorId): Blog
    {
        $groupId=(int)($attributes['group_id']??0);
        if($groupId<=0) throw new InvalidArgumentException('A valid group_id is required for blog publication.');

        $attributes['group_id']=$groupId;
        $attributes['user_id']=$actorId;

        return DB::transaction(function() use($attributes,$groupId,$actorId): Blog {
            $blog=Blog::query()->create($attributes);
            $this->feed->record($groupId,'post',(int)$blog->id,$actorId,$blog->created_at);
            return $blog->refresh();
        });
    }
}
