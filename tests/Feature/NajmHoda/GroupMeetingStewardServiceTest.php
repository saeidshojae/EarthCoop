<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupSession;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\NajmHoda\NajmHodaGroupMeetingStewardService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GroupMeetingStewardServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_manager_preview_does_not_mutate_and_confirmed_future_meeting_uses_canonical_session_model(): void
    {
        [$group, $manager] = $this->makeGroupAndUser(3);
        $service = app(NajmHodaGroupMeetingStewardService::class);
        $future = now()->addDay()->setSecond(0);

        $preview = $service->preview($manager, $group, [
            'title' => 'نشست هفتگی مدیران',
            'subject' => 'بررسی امور جاری',
            'agenda' => 'گزارش اقدامات؛ تصمیم درباره مرحله بعد',
            'starts_at' => $future->format('Y-m-d H:i:s'),
        ]);

        $this->assertTrue($preview['allowed']);
        $this->assertFalse($preview['needs_input']);
        $this->assertStringContainsString('نشست رسمی گروه', $preview['preview']);
        $this->assertSame(0, GroupSession::query()->where('group_id', $group->id)->count());

        $result = $service->executeConfirmed($manager, $group, $preview['payload']);

        $this->assertSame('executed', $result['decision']);
        $this->assertSame('meeting_scheduled', $result['reason']);
        $session = GroupSession::query()->where('group_id', $group->id)->firstOrFail();
        $this->assertSame('scheduled', $session->status);
        $this->assertSame('نشست هفتگی مدیران', $session->title);
        $this->assertSame('بررسی امور جاری', $session->subject);
        $this->assertSame('گزارش اقدامات؛ تصمیم درباره مرحله بعد', $session->agenda);

        $state = $service->currentState($group);
        $this->assertNull($state['active']);
        $this->assertCount(1, $state['scheduled']);
    }

    public function test_immediate_meeting_starts_and_closes_general_group_participation(): void
    {
        [$group, $manager] = $this->makeGroupAndUser(3);
        $service = app(NajmHodaGroupMeetingStewardService::class);

        $preview = $service->preview($manager, $group, [
            'title' => 'نشست فوری',
            'starts_at' => 'الان',
        ]);
        $result = $service->executeConfirmed($manager, $group, $preview['payload']);

        $this->assertSame('meeting_started', $result['reason']);
        $session = GroupSession::query()->where('group_id', $group->id)->firstOrFail();
        $this->assertSame('active', $session->status);
        $this->assertNotNull($session->started_at);
        $this->assertFalse((bool) $group->fresh()->is_open);
    }

    public function test_regular_member_cannot_plan_or_start_official_meeting(): void
    {
        [$group, $member] = $this->makeGroupAndUser(1);
        $service = app(NajmHodaGroupMeetingStewardService::class);

        $preview = $service->preview($member, $group, [
            'title' => 'نشست غیرمجاز',
            'starts_at' => 'الان',
        ]);

        $this->assertFalse($preview['allowed']);
        $this->assertSame(0, GroupSession::query()->where('group_id', $group->id)->count());
    }

    /** @return array{0:Group,1:User} */
    private function makeGroupAndUser(int $role): array
    {
        $group = Group::create([
            'name' => 'Najm Hoda meeting test ' . uniqid('', true),
            'is_open' => 1,
        ]);

        $user = User::create([
            'email' => uniqid('najm-hoda-meeting-', true) . '@example.test',
            'password' => Hash::make('password'),
            'status' => 1,
            'first_name' => 'Test',
            'last_name' => 'Manager',
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
}
