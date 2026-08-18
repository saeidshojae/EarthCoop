<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Blog;
use App\Models\Category;
use App\Models\Group;
use App\Models\Message;
use App\Models\NajmHodaGroupActionItem;
use App\Models\User;
use App\Services\NajmHoda\Context\NajmHodaGroundedPageResponder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GroupKnowledgeGroundedResponderTest extends TestCase
{
    use DatabaseTransactions;

    public function test_today_summary_is_built_from_real_group_snapshot_without_llm(): void
    {
        [$group, $user] = $this->seedGroupActivity();

        $response = (new NajmHodaGroundedPageResponder())->respond(
            'مطالب امروز گروه را خلاصه کن',
            $this->context($group)
        );

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
        $this->assertTrue($response['grounded_page_response']);
        $this->assertStringContainsString('خلاصهٔ داده‌محور امروز', $response['message']);
        $this->assertStringContainsString('گزارش جلسه آزمایشی', $response['message']);
        $this->assertStringContainsString('بررسی بودجه تا فردا', $response['message']);
        $this->assertStringContainsString('پیگیری بودجه', $response['message']);
        $this->assertStringNotContainsString('example.com', $response['message']);
    }

    public function test_minutes_draft_does_not_invent_unregistered_decisions(): void
    {
        [$group] = $this->seedGroupActivity(false);

        $response = (new NajmHodaGroundedPageResponder())->respond(
            'از مطالب امروز گروه یک صورتجلسه تهیه کن',
            $this->context($group)
        );

        $this->assertIsArray($response);
        $this->assertStringContainsString('پیش‌نویس صورتجلسهٔ داده‌محور', $response['message']);
        $this->assertStringContainsString('Action Item ثبت‌شده‌ای وجود ندارد', $response['message']);
        $this->assertStringContainsString('چیزی را به‌عنوان مصوبه قطعی حدس نمی‌زند', $response['message']);
    }

    /** @return array{0:Group,1:User} */
    private function seedGroupActivity(bool $withActionItem = true): array
    {
        $group = Group::create(['name' => 'گروه مدیریت آزمایشی', 'is_open' => 1]);
        $user = User::create([
            'email' => uniqid('grounded-knowledge-', true) . '@example.test',
            'password' => Hash::make('password'),
            'status' => 1,
            'first_name' => 'مدیر',
            'last_name' => 'آزمایشی',
            'is_system' => false,
        ]);
        $category = Category::firstOrCreate(['name' => 'Najm Hoda grounded knowledge test']);

        Message::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'message' => 'بررسی بودجه تا فردا انجام شود.',
        ]);
        Blog::create([
            'title' => 'گزارش جلسه آزمایشی',
            'content' => 'در این جلسه درباره برنامه هفته آینده گفتگو شد.',
            'group_id' => $group->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);

        if ($withActionItem) {
            NajmHodaGroupActionItem::create([
                'group_id' => $group->id,
                'title' => 'پیگیری بودجه',
                'details' => 'گزارش بودجه آماده شود',
                'assignee_name' => 'مدیر آزمایشی',
                'priority' => 'normal',
                'status' => 'open',
            ]);
        }

        return [$group, $user];
    }

    /** @return array<string,mixed> */
    private function context(Group $group): array
    {
        return [
            'page_kind' => 'group_chat',
            'page_label' => 'گفتگوی گروه',
            'resource_id' => (int) $group->id,
            'resource' => ['id' => (int) $group->id, 'type' => 'group'],
            'capability_contracts' => [],
            'delegated_actions' => [],
        ];
    }
}
