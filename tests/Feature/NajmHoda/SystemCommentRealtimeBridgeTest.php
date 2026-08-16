<?php

namespace Tests\Feature\NajmHoda;

use App\Events\CommentCreated;
use App\Models\Blog;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Group;
use App\Models\GroupFeedItem;
use App\Models\GroupSyncEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SystemCommentRealtimeBridgeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'group-chat.enabled' => true,
            'group-chat.transport' => 'polling',
            'group-chat.features.feed_sequence_v1' => true,
        ]);
    }

    public function test_system_authored_comment_enters_canonical_feed_and_sync_stream(): void
    {
        $group = Group::create([
            'name' => 'Najm Hoda comment bridge test ' . uniqid('', true),
            'is_open' => 1,
        ]);

        $bot = User::create([
            'email' => uniqid('najm-hoda-system-comment-', true) . '@example.test',
            'password' => Hash::make('password'),
            'status' => 1,
            'first_name' => 'Najm',
            'last_name' => 'Hoda',
            'is_system' => true,
        ]);

        $category = Category::firstOrCreate(['name' => 'Najm Hoda comment bridge category']);
        $post = Blog::create([
            'title' => 'Bridge target post',
            'content' => 'Bridge target content',
            'group_id' => $group->id,
            'user_id' => $bot->id,
            'category_id' => $category->id,
        ]);

        $comment = Comment::create([
            'user_id' => $bot->id,
            'blog_id' => $post->id,
            'message' => 'نظر سیستمی آزمایشی',
        ]);

        event(new CommentCreated($comment, $post, $group, $bot));

        $this->assertTrue(
            GroupFeedItem::query()
                ->where('group_id', $group->id)
                ->where('type', 'comment')
                ->where('content_id', $comment->id)
                ->exists()
        );

        $sync = GroupSyncEvent::query()
            ->where('group_id', $group->id)
            ->where('action', 'comment_created')
            ->where('content_type', 'comment')
            ->where('content_id', $comment->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(0, (int) ($sync->actor_id ?? 0));
        $this->assertTrue((bool) data_get($sync->payload, 'system_authored'));
        $this->assertSame((int) $bot->id, (int) data_get($sync->payload, 'system_actor_id'));
        $this->assertSame((int) $post->id, (int) data_get($sync->payload, 'blog_id'));
    }
}
