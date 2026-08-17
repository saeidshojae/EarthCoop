<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupSession;
use App\Models\GroupUser;
use App\Models\Message;
use App\Models\NajmHodaGroupMeetingMinute;
use App\Models\User;
use App\Services\GroupChat\GroupSessionService;
use App\Services\NajmHoda\NajmHodaGroupMeetingMinutesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GroupMeetingMinutesServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_evidence_is_limited_to_official_session_window_and_draft_is_not_approved(): void
    {
        [$group, $manager] = $this->makeGroupAndManager();
        $session = GroupSession::create([
            'group_id' => $group->id,
            'created_by' => $manager->id,
            'title' => 'نشست رسمی تست',
            'subject' => 'موضوع تست',
            'agenda' => 'دستور جلسه تست',
            'status' => 'ended',
            'starts_at' => now()->subHour(),
            'started_at' => now()->subHour(),
            'ended_at' => now()->subMinutes(10),
            'ended_by' => $manager->id,
        ]);

        $before = Message::create(['group_id' => $group->id, 'user_id' => $manager->id, 'message' => 'قبل از نشست']);
        $before->forceFill(['created_at' => now()->subHours(2), 'updated_at' => now()->subHours(2)])->saveQuietly();

        $inside = Message::create(['group_id' => $group->id, 'user_id' => $manager->id, 'message' => 'این پیام داخل نشست و قابل استناد است']);
        $inside->forceFill(['created_at' => now()->subMinutes(30), 'updated_at' => now()->subMinutes(30)])->saveQuietly();

        $after = Message::create(['group_id' => $group->id, 'user_id' => $manager->id, 'message' => 'بعد از نشست']);
        $after->forceFill(['created_at' => now()->subMinutes(5), 'updated_at' => now()->subMinutes(5)])->saveQuietly();

        $minute = app(NajmHodaGroupMeetingMinutesService::class)->generateDraft($session, $manager);

        $this->assertSame('draft', $minute->status);
        $this->assertNull($minute->approved_at);
        $this->assertSame(1, data_get($minute->evidence_snapshot, 'counts.messages'));
        $this->assertSame($inside->id, data_get($minute->evidence_snapshot, 'messages.0.id'));
        $this->assertStringContainsString('هنوز موردی به عنوان مصوبه قطعی تأیید نشده است', $minute->minutes);
    }

    public function test_ending_canonical_session_automatically_creates_grounded_minutes_draft(): void
    {
        [$group, $manager] = $this->makeGroupAndManager();
        $session = GroupSession::create([
            'group_id' => $group->id,
            'created_by' => $manager->id,
            'title' => 'نشست خودکار',
            'status' => 'active',
            'starts_at' => now()->subMinutes(20),
            'started_at' => now()->subMinutes(20),
        ]);
        $group->update(['is_open' => false]);

        Message::create([
            'group_id' => $group->id,
            'user_id' => $manager->id,
            'message' => 'گزارش جلسه باید تا فردا آماده شود.',
        ]);

        app(GroupSessionService::class)->end($group->fresh(), $manager->id);

        $minute = NajmHodaGroupMeetingMinute::query()->where('group_session_id', $session->id)->firstOrFail();
        $this->assertSame('draft', $minute->status);
        $this->assertSame(1, data_get($minute->evidence_snapshot, 'counts.messages'));
        $this->assertNotNull($minute->generated_at);
        $this->assertTrue((bool) $group->fresh()->is_open);
    }

    public function test_draft_becomes_official_only_after_explicit_approval(): void
    {
        [$group, $manager] = $this->makeGroupAndManager();
        $session = GroupSession::create([
            'group_id' => $group->id,
            'created_by' => $manager->id,
            'title' => 'نشست تأیید',
            'status' => 'ended',
            'starts_at' => now()->subHour(),
            'started_at' => now()->subHour(),
            'ended_at' => now(),
            'ended_by' => $manager->id,
        ]);

        $minute = app(NajmHodaGroupMeetingMinutesService::class)->generateDraft($session, $manager);
        $approved = app(NajmHodaGroupMeetingMinutesService::class)->approve($minute, $manager);

        $this->assertSame('approved', $approved->status);
        $this->assertSame($manager->id, (int) $approved->approved_by);
        $this->assertNotNull($approved->approved_at);
    }

    /** @return array{0:Group,1:User} */
    private function makeGroupAndManager(): array
    {
        $group = Group::create([
            'name' => 'Meeting minutes test ' . uniqid('', true),
            'is_open' => 1,
        ]);

        $user = User::create([
            'email' => uniqid('meeting-minutes-', true) . '@example.test',
            'password' => Hash::make('password'),
            'status' => 1,
            'first_name' => 'Test',
            'last_name' => 'Manager',
            'is_system' => false,
        ]);

        GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => 3,
            'status' => 1,
        ]);

        return [$group, $user];
    }
}
