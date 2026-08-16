<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Blog;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Group;
use App\Models\GroupSyncEvent;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\NajmHoda\NajmHodaPrivateGroupCommentCommandService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PrivateGroupCommentCommandServiceTest extends TestCase
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
            'najm-hoda.group_assistant.action_executor.allow_create_comment' => true,
            'najm-hoda.group_assistant.action_executor.permitted_roles' => [2, 3],
            'group-chat.enabled' => true,
            'group-chat.transport' => 'polling',
            'group-chat.features.feed_sequence_v1' => true,
        ]);
    }

    public function test_post_target_comment_command_is_not_misclassified_and_executes_exact_preview(): void
    {
        [$group, $manager] = $this->makeGroupAndManager();
        $category = Category::firstOrCreate(['name' => 'Najm Hoda comment test']);
        $post = Blog::create([
            'title' => 'پست هدف',
            'content' => 'محتوای پست هدف',
            'group_id' => $group->id,
            'user_id' => $manager->id,
            'category_id' => $category->id,
        ]);

        $service = app(NajmHodaPrivateGroupCommentCommandService::class);
        $command = "روی پست #{$post->id} یک نظر بذار: این یک نظر آزمایشی از طرف نجم هداست.";

        $proposal = $service->intercept($manager, $this->pageContext($group), $command, 2020);

        $this->assertIsArray($proposal);
        $this->assertSame('awaiting_confirmation', $proposal['action_status']);
        $this->assertSame('create_comment', $proposal['action']);
        $this->assertStringContainsString("هدف: پست #{$post->id}", $proposal['message']);
        $this->assertStringContainsString('متن نظر: این یک نظر آزمایشی از طرف نجم هداست.', $proposal['message']);
        $this->assertSame(0, Comment::query()->where('blog_id', $post->id)->count());

        $executed = $service->intercept($manager, $this->pageContext($group), 'تأیید', 2020);

        $this->assertIsArray($executed);
        $this->assertSame('executed', $executed['action_status']);
        $comment = Comment::query()->where('blog_id', $post->id)->latest('id')->firstOrFail();
        $this->assertSame('این یک نظر آزمایشی از طرف نجم هداست.', $comment->message);

        $bot = User::query()
            ->where('email', config('najm-hoda.group_assistant.bot_email', 'najm-hoda-bot@local.invalid'))
            ->firstOrFail();
        $this->assertSame((int) $bot->id, (int) $comment->user_id);
        $this->assertTrue($bot->isSystemIdentity());

        $this->assertTrue(
            GroupSyncEvent::query()
                ->where('group_id', $group->id)
                ->where('action', 'comment_created')
                ->where('content_type', 'comment')
                ->where('content_id', $comment->id)
                ->exists(),
            'System-authored comments must emit the canonical realtime sync event.'
        );
    }

    public function test_comment_without_explicit_text_requests_input_without_mutation(): void
    {
        [$group, $manager] = $this->makeGroupAndManager();
        $category = Category::firstOrCreate(['name' => 'Najm Hoda comment test']);
        $post = Blog::create([
            'title' => 'پست هدف',
            'content' => 'محتوا',
            'group_id' => $group->id,
            'user_id' => $manager->id,
            'category_id' => $category->id,
        ]);

        $response = app(NajmHodaPrivateGroupCommentCommandService::class)->intercept(
            $manager,
            $this->pageContext($group),
            "روی پست #{$post->id} یک نظر بذار",
            2021
        );

        $this->assertIsArray($response);
        $this->assertSame('needs_input', $response['action_status']);
        $this->assertSame(0, Comment::query()->where('blog_id', $post->id)->count());
    }

    /** @return array{0:Group,1:User} */
    private function makeGroupAndManager(): array
    {
        $group = Group::create([
            'name' => 'Najm Hoda comment command test ' . uniqid('', true),
            'is_open' => 1,
        ]);

        $manager = User::create([
            'email' => uniqid('najm-hoda-comment-', true) . '@example.test',
            'password' => Hash::make('password'),
            'status' => 1,
            'first_name' => 'Test',
            'last_name' => 'Manager',
            'is_system' => false,
        ]);

        GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $manager->id,
            'role' => 3,
            'status' => 1,
        ]);

        return [$group, $manager];
    }

    /** @return array<string,mixed> */
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
