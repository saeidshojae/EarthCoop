<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Blog;
use App\Models\Category;
use App\Models\Group;
use App\Models\Message;
use App\Models\NajmHodaGroupActionItem;
use App\Models\User;
use App\Services\NajmHoda\NajmHodaGroupKnowledgeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GroupKnowledgeServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_snapshot_is_group_scoped_time_scoped_and_strips_html(): void
    {
        $group = Group::create(['name' => 'Knowledge group', 'is_open' => 1]);
        $other = Group::create(['name' => 'Other group', 'is_open' => 1]);
        $user = User::create([
            'email' => uniqid('knowledge-', true) . '@example.test',
            'password' => Hash::make('password'),
            'status' => 1,
            'first_name' => 'کاربر',
            'last_name' => 'آزمایشی',
            'is_system' => false,
        ]);
        $category = Category::firstOrCreate(['name' => 'Najm Hoda knowledge test']);

        $insideMessage = Message::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'message' => '<b>تصمیم جلسه:</b> بررسی بودجه تا فردا',
        ]);
        Blog::create([
            'title' => '<i>گزارش جلسه</i>',
            'content' => '<p>برنامه هفته آینده بررسی شد.</p>',
            'group_id' => $group->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);
        NajmHodaGroupActionItem::create([
            'group_id' => $group->id,
            'title' => 'پیگیری بودجه',
            'details' => 'تا فردا گزارش آماده شود',
            'assignee_name' => 'کاربر آزمایشی',
            'priority' => 'normal',
            'status' => 'open',
        ]);
        Message::create([
            'group_id' => $other->id,
            'user_id' => $user->id,
            'message' => 'نباید وارد snapshot شود',
        ]);

        $from = now()->subMinute();
        $to = now()->addMinute();
        $snapshot = app(NajmHodaGroupKnowledgeService::class)->snapshot($group, $from, $to);

        $this->assertSame((int) $group->id, $snapshot['group']['id']);
        $this->assertSame(1, $snapshot['counts']['messages']);
        $this->assertSame(1, $snapshot['counts']['posts']);
        $this->assertSame(1, $snapshot['counts']['action_items']);
        $this->assertSame((int) $insideMessage->id, $snapshot['messages'][0]['id']);
        $this->assertSame('تصمیم جلسه: بررسی بودجه تا فردا', $snapshot['messages'][0]['text']);
        $this->assertSame('گزارش جلسه', $snapshot['posts'][0]['title']);
        $this->assertStringNotContainsString('<', $snapshot['posts'][0]['text']);
    }
}
