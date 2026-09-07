<?php

namespace App\Services\Blog;

use App\Events\GroupFeedUpdated;
use App\Models\Blog;
use App\Services\GroupChat\GroupEventPublisher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BlogLifecycleService
{
    public function __construct(
        protected GroupEventPublisher $events,
    ) {}

    /** @return array<string,int|string> */
    public function delete(Blog $blog, int $actorId): array
    {
        $groupId = (int) $blog->group_id;
        $blogId = (int) $blog->id;

        DB::transaction(function () use ($blog, $groupId, $blogId): void {
            $blog->delete();

            $cacheKey = 'group.' . $groupId . '.deleted_post_ids';
            $existing = Cache::get($cacheKey, []);
            $existing[] = $blogId;
            Cache::put($cacheKey, array_values(array_unique(array_slice($existing, -200))), 600);
        });

        $this->events->publish(new GroupFeedUpdated(
            $groupId,
            'post_deleted',
            ['post_id' => $blogId],
            $actorId
        ));

        return [
            'blog_id' => $blogId,
            'group_id' => $groupId,
            'status' => 'deleted',
        ];
    }
}
