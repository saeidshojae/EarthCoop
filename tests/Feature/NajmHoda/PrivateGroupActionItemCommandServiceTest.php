<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\NajmHodaGroupActionItem;
use App\Models\User;
use App\Services\NajmHoda\NajmHodaGroupActionCandidateService;
use App\Services\NajmHoda\NajmHodaPrivateGroupActionItemCommandService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class PrivateGroupActionItemCommandServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    public function test_manager_preview_requires_confirmation_before_persisting_action_item(): void
    {
        [$group, $manager] = $this->seedMember(3);

        $extractor = Mockery::mock(NajmHodaGroupActionCandidateService::class);
        $extractor->shouldReceive('extract')->once()->andReturn([
            'available' => true,
            'candidates' => [[
                'title' => 'تهیه گزارش جلسه',
                'details' => 'گزارش جلسه آماده و برای مدیران ارسال شود.',
                'assignee_name' => null,
                'due_text' => 'فردا',
                'priority' => 'high',
                'source' => 'message:42',
                'evidence' => 'گزارش جلسه را تا فردا آماده کنید',
            ]],
            'snapshot' => [],
        ]);

        $service = new NajmHodaPrivateGroupActionItemCommandService($extractor);
        $pageContext = $this->pageContext($group);

        $preview = $service->intercept(
            $manager,
            $pageContext,
            'از مطالب امروز موارد اقدام پیشنهادی را استخراج کن',
            77
        );

        $this->assertIsArray($preview);
        $this->assertSame('awaiting_confirmation', $preview['status']);
        $this->assertStringContainsString('تهیه گزارش جلسه', $preview['message']);
        $this->assertSame(0, NajmHodaGroupActionItem::query()->where('group_id', $group->id)->count());

        $confirmed = $service->intercept($manager, $pageContext, 'تأیید', 77);

        $this->assertSame('executed', $confirmed['status']);
        $item = NajmHodaGroupActionItem::query()->where('group_id', $group->id)->firstOrFail();
        $this->assertSame('تهیه گزارش جلسه', $item->title);
        $this->assertSame('high', $item->priority);
        $this->assertSame('open', $item->status);
        $this->assertSame($manager->id, (int) data_get($item->meta, 'confirmed_by_user_id'));
        $this->assertSame('message:42', data_get($item->meta, 'source'));
    }

    public function test_non_leadership_member_cannot_request_action_item_extraction(): void
    {
        [$group, $member] = $this->seedMember(1);

        $extractor = Mockery::mock(NajmHodaGroupActionCandidateService::class);
        $extractor->shouldNotReceive('extract');

        $service = new NajmHodaPrivateGroupActionItemCommandService($extractor);
        $response = $service->intercept(
            $member,
            $this->pageContext($group),
            'از بحث امروز موارد اقدام را استخراج کن',
            88
        );

        $this->assertSame('blocked', $response['status']);
        $this->assertSame(0, NajmHodaGroupActionItem::query()->where('group_id', $group->id)->count());
    }

    public function test_provider_unavailable_returns_safe_message_without_mutation(): void
    {
        [$group, $manager] = $this->seedMember(3);

        $extractor = Mockery::mock(NajmHodaGroupActionCandidateService::class);
        $extractor->shouldReceive('extract')->once()->andReturn([
            'available' => false,
            'candidates' => [],
            'snapshot' => [],
        ]);

        $service = new NajmHodaPrivateGroupActionItemCommandService($extractor);
        $response = $service->intercept(
            $manager,
            $this->pageContext($group),
            'وظایف پیشنهادی امروز را استخراج کن',
            99
        );

        $this->assertSame('provider_unavailable', $response['status']);
        $this->assertSame(0, NajmHodaGroupActionItem::query()->where('group_id', $group->id)->count());
    }

    /** @return array{0:Group,1:User} */
    private function seedMember(int $role): array
    {
        $group = Group::create(['name' => 'Action queue group', 'is_open' => 1]);
        $user = User::create([
            'email' => uniqid('action-queue-', true) . '@example.test',
            'password' => Hash::make('password'),
            'status' => 1,
            'first_name' => 'مدیر',
            'last_name' => 'گروه',
            'is_system' => false,
        ]);
        GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 1,
        ]);
        return [$group, $user];
    }

    /** @return array<string,mixed> */
    private function pageContext(Group $group): array
    {
        return [
            'page_kind' => 'group_chat',
            'resource_id' => $group->id,
            'resource' => ['id' => $group->id, 'type' => 'group'],
        ];
    }
}
