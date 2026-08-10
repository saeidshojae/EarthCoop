<?php

namespace Tests\Feature\GroupChat;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\GroupChat\GroupFeedService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use App\Events\GroupRealtimeEnvelope;
use App\Jobs\PublishGroupChatOutbox;
use App\Models\GroupChatOutbox;
use App\Models\Blog;
use App\Models\Category;
use App\Models\Comment;
use Tests\TestCase;

class FeedCursorTest extends TestCase
{
    use DatabaseTransactions;

    private bool $createdCursorColumn = false;
    private bool $createdFeedSequences = false;
    private bool $createdFeedItems = false;
    private bool $createdOutbox = false;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'group-chat.features.feed_sequence_v1' => true,
            'group-chat.features.feed_unread_v1' => true,
            'group-chat.features.transactional_outbox_v1' => true,
            'group-chat.features.realtime_envelope_v1' => true,
            'group-chat.features.delta_sync_v1' => true,
        ]);

        if (! Schema::hasColumn('group_user', 'last_read_feed_sequence')) {
            Schema::table('group_user', fn (Blueprint $table) => $table->unsignedBigInteger('last_read_feed_sequence')->default(0));
            $this->createdCursorColumn = true;
        }
        if (! Schema::hasTable('group_feed_sequences')) {
            Schema::create('group_feed_sequences', function (Blueprint $table) {
                $table->unsignedBigInteger('group_id')->primary();
                $table->unsignedBigInteger('last_sequence')->default(0);
                $table->timestamps();
            });
            $this->createdFeedSequences = true;
        }
        if (! Schema::hasTable('group_feed_items')) {
            Schema::create('group_feed_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('group_id');
                $table->unsignedBigInteger('sequence');
                $table->string('type', 30);
                $table->unsignedBigInteger('content_id');
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->timestamp('occurred_at');
                $table->timestamps();
                $table->unique(['group_id', 'sequence']);
                $table->unique(['type', 'content_id']);
            });
            $this->createdFeedItems = true;
        }
        if (! Schema::hasTable('group_chat_outbox')) {
            Schema::create('group_chat_outbox', function (Blueprint $table) {
                $table->id();
                $table->uuid('event_id')->unique();
                $table->unsignedBigInteger('group_id');
                $table->unsignedBigInteger('feed_item_id')->nullable();
                $table->unsignedBigInteger('sequence');
                $table->string('type', 60);
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->json('payload');
                $table->string('status', 20)->default('pending');
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->timestamp('available_at');
                $table->timestamp('published_at')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();
            });
            $this->createdOutbox = true;
        }
    }

    protected function tearDown(): void
    {
        if ($this->createdOutbox) Schema::dropIfExists('group_chat_outbox');
        if ($this->createdFeedItems) Schema::dropIfExists('group_feed_items');
        if ($this->createdFeedSequences) Schema::dropIfExists('group_feed_sequences');
        if ($this->createdCursorColumn && Schema::hasColumn('group_user', 'last_read_feed_sequence')) {
            Schema::table('group_user', fn (Blueprint $table) => $table->dropColumn('last_read_feed_sequence'));
        }
        parent::tearDown();
    }

    public function test_sequences_are_monotonic_and_recording_is_idempotent(): void
    {
        [$group, $member] = $this->makeGroupWithMember();
        $feed = app(GroupFeedService::class);

        $first = $feed->record($group->id, 'message', 1001, $member->id);
        $second = $feed->record($group->id, 'post', 1002, $member->id);
        $replayed = $feed->record($group->id, 'message', 1001, $member->id);

        $this->assertSame(1, (int) $first->sequence);
        $this->assertSame(2, (int) $second->sequence);
        $this->assertSame($first->id, $replayed->id);
    }

    public function test_unread_excludes_own_content_and_mark_read_advances_cursor_atomically(): void
    {
        [$group, $viewer] = $this->makeGroupWithMember();
        $other = $this->makeUser();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $other->id, 'role' => 1, 'status' => 1]);
        $feed = app(GroupFeedService::class);

        $feed->record($group->id, 'message', 2001, $other->id);
        $feed->record($group->id, 'file', 2002, $other->id);
        $feed->record($group->id, 'poll', 2003, $viewer->id);

        $counts = $feed->unreadCounts($group->id, $viewer->id);
        $this->assertSame(2, $counts['total']);
        $this->assertSame(2, $counts['messages']);

        $cursor = $feed->markRead($group->id, $viewer->id);
        $this->assertSame(3, $cursor);
        $this->assertSame(0, $feed->unreadCounts($group->id, $viewer->id)['total']);
    }

    public function test_mark_all_read_endpoint_advances_to_latest_group_sequence(): void
    {
        [$group, $viewer] = $this->makeGroupWithMember();
        $other = $this->makeUser();
        app(GroupFeedService::class)->record($group->id, 'post', 3001, $other->id);

        $this->actingAs($viewer)->postJson(route('groups.mark-all-read', $group))
            ->assertOk()
            ->assertJsonPath('cursor', 1)
            ->assertJsonPath('unread.total', 0)
            ->assertJsonPath('meta.api_version', '2026-08-05');
    }

    public function test_feed_write_creates_outbox_and_delta_returns_ordered_envelopes(): void
    {
        [$group, $member] = $this->makeGroupWithMember();
        $feed = app(GroupFeedService::class);
        $feed->record($group->id, 'message', 4001, $member->id);
        $feed->record($group->id, 'post', 4002, $member->id);

        $this->assertSame(2, GroupChatOutbox::where('group_id', $group->id)->count());
        $response = $this->actingAs($member)->getJson(route('groups.feed.delta', ['group' => $group, 'after_sequence' => 0]));
        $response->assertOk()->assertJsonPath('events.0.sequence', 1)->assertJsonPath('events.1.sequence', 2);
        $this->assertNotSame($response->json('events.0.event_id'), $response->json('events.1.event_id'));
    }

    public function test_outbox_worker_publishes_versioned_envelope(): void
    {
        Event::fake([GroupRealtimeEnvelope::class]);
        [$group, $member] = $this->makeGroupWithMember();
        app(GroupFeedService::class)->record($group->id, 'message', 5001, $member->id);
        $outbox = GroupChatOutbox::where('group_id', $group->id)->firstOrFail();

        (new PublishGroupChatOutbox($outbox->id))->handle();

        $this->assertSame('published', $outbox->fresh()->status);
        Event::assertDispatched(GroupRealtimeEnvelope::class, fn ($event) =>
            $event->envelope['event_id'] === $outbox->event_id
            && $event->envelope['sequence'] === 1
            && $event->envelope['version'] === 1
        );
    }

    public function test_comment_delta_contains_canonical_post_comment_count(): void
    {
        [$group, $member] = $this->makeGroupWithMember();
        $category = Category::create(['name' => 'Feed comments ' . fake()->unique()->word()]);
        $post = Blog::create([
            'title' => 'Post with comments',
            'content' => 'Body',
            'user_id' => $member->id,
            'group_id' => $group->id,
            'category_id' => $category->id,
        ]);
        Comment::create(['blog_id' => $post->id, 'user_id' => $member->id, 'message' => 'First']);
        $comment = Comment::create(['blog_id' => $post->id, 'user_id' => $member->id, 'message' => 'Second']);
        app(GroupFeedService::class)->record($group->id, 'comment', $comment->id, $member->id);

        $this->actingAs($member)
            ->getJson(route('groups.feed.delta', ['group' => $group, 'after_sequence' => 0]))
            ->assertOk()
            ->assertJsonPath('events.0.payload.content_type', 'comment')
            ->assertJsonPath('events.0.payload.blog_id', $post->id)
            ->assertJsonPath('events.0.payload.comments_count', 2);
    }

    private function makeGroupWithMember(): array
    {
        $group = Group::create(['group_type' => 'test', 'name' => 'Feed ' . fake()->unique()->word(), 'is_open' => 1]);
        $user = $this->makeUser();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $user->id, 'role' => 1, 'status' => 1]);
        return [$group, $user];
    }

    private function makeUser(): User
    {
        return User::create([
            'first_name' => 'Feed', 'last_name' => 'Tester',
            'email' => 'feed-' . Str::uuid() . '@example.test', 'password' => bcrypt('password123'),
        ]);
    }
}
