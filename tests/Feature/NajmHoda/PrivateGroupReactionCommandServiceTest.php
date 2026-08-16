<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Blog;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Group;
use App\Models\GroupSyncEvent;
use App\Models\GroupUser;
use App\Models\Reaction;
use App\Models\User;
use App\Services\NajmHoda\NajmHodaPrivateGroupReactionCommandService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PrivateGroupReactionCommandServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'najm-hoda.enabled' => true,
            'najm-hoda.group_assistant.enabled' => true,
            'najm-hoda.group_assistant.action_executor.enabled' => true,
            'najm-hoda.group_assistant.action_executor.dry_run' => false,
            'najm-hoda.group_assistant.action_executor.propose_before_execute' => false,
            'najm-hoda.group_assistant.action_executor.allow_react_post' => true,
            'najm-hoda.group_assistant.action_executor.allow_react_comment' => true,
            'najm-hoda.group_assistant.action_executor.permitted_roles' => [2, 3],
            'group-chat.enabled' => true,
            'group-chat.transport' => 'polling',
        ]);
    }

    public function test_manager_can_confirm_exact_post_like_and_realtime_event_is_emitted(): void
    {
        [$group, $manager] = $this->makeGroupAndManager();
        $post = $this->makePost($group, $manager, 'پست واکنش');
        $service = app(NajmHodaPrivateGroupReactionCommandService::class);

        $proposal = $service->intercept($manager, $this->pageContext($group), "پست #{$post->id} را لایک کن", 2101);
        $this->assertIsArray($proposal);
        $this->assertSame('awaiting_confirmation', $proposal['action_status']);
        $this->assertStringContainsString("پست #{$post->id}", $proposal['message']);
        $this->assertStringContainsString('واکنش: لایک', $proposal['message']);
        $this->assertSame(0, Reaction::query()->where('blog_id', $post->id)->count());

        $executed = $service->intercept($manager, $this->pageContext($group), 'تأیید', 2101);
        $this->assertSame('executed', $executed['action_status']);

        $bot = User::query()->where('email', config('najm-hoda.group_assistant.bot_email', 'najm-hoda-bot@local.invalid'))->firstOrFail();
        $this->assertDatabaseHas('reactions', [
            'blog_id' => $post->id,
            'user_id' => $bot->id,
            'type' => 1,
        ]);
        $this->assertTrue(GroupSyncEvent::query()
            ->where('group_id', $group->id)
            ->where('action', 'post_reaction')
            ->where('content_type', 'post')
            ->where('content_id', $post->id)
            ->exists());
    }

    public function test_manager_can_confirm_exact_comment_dislike(): void
    {
        [$group, $manager] = $this->makeGroupAndManager();
        $post = $this->makePost($group, $manager, 'پست نظر');
        $comment = Comment::create([
            'user_id' => $manager->id,
            'blog_id' => $post->id,
            'message' => 'نظر هدف',
        ]);
        $service = app(NajmHodaPrivateGroupReactionCommandService::class);

        $proposal = $service->intercept($manager, $this->pageContext($group), "نظر #{$comment->id} را دیس‌لایک کن", 2102);
        $this->assertSame('awaiting_confirmation', $proposal['action_status']);
        $this->assertStringContainsString("نظر #{$comment->id}", $proposal['message']);
        $this->assertStringContainsString('واکنش: دیس‌لایک', $proposal['message']);

        $executed = $service->intercept($manager, $this->pageContext($group), 'تأیید', 2102);
        $this->assertSame('executed', $executed['action_status']);

        $bot = User::query()->where('email', config('najm-hoda.group_assistant.bot_email', 'najm-hoda-bot@local.invalid'))->firstOrFail();
        $this->assertDatabaseHas('reactions', [
            'comment_id' => $comment->id,
            'user_id' => $bot->id,
            'type' => 0,
            'react_type' => 1,
        ]);
        $this->assertTrue(GroupSyncEvent::query()
            ->where('group_id', $group->id)
            ->where('action', 'comment_reaction')
            ->where('content_type', 'comment')
            ->where('content_id', $comment->id)
            ->exists());
    }

    private function makeGroupAndManager(): array
    {
        $group = Group::create(['name' => 'Reaction test ' . uniqid('', true), 'is_open' => 1]);
        $manager = User::create([
            'email' => uniqid('reaction-manager-', true) . '@example.test',
            'password' => Hash::make('password'),
            'status' => 1,
            'first_name' => 'Reaction',
            'last_name' => 'Manager',
            'is_system' => false,
        ]);
        GroupUser::create(['group_id' => $group->id, 'user_id' => $manager->id, 'role' => 3, 'status' => 1]);
        return [$group, $manager];
    }

    private function makePost(Group $group, User $author, string $title): Blog
    {
        $category = Category::firstOrCreate(['name' => 'Najm Hoda reaction test']);
        return Blog::create([
            'title' => $title,
            'content' => 'محتوای تست واکنش',
            'group_id' => $group->id,
            'user_id' => $author->id,
            'category_id' => $category->id,
        ]);
    }

    private function pageContext(Group $group): array
    {
        return [
            'page_kind' => 'group_chat',
            'resource_type' => 'group',
            'resource_id' => $group->id,
            'resource' => ['type' => 'group', 'id' => $group->id],
        ];
    }
}
