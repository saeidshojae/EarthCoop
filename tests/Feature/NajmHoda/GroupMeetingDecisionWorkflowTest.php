<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupSession;
use App\Models\GroupUser;
use App\Models\NajmHodaGroupActionItem;
use App\Models\NajmHodaGroupMeetingMinute;
use App\Models\User;
use App\Services\NajmHoda\NajmHodaGroupActionCandidateService;
use App\Services\NajmHoda\NajmHodaGroupDecisionCandidateService;
use App\Services\NajmHoda\NajmHodaPrivateGroupMeetingCommandService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class GroupMeetingDecisionWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        if (! Schema::hasTable('najm_hoda_group_meeting_minutes')) {
            Artisan::call('migrate', [
                '--path' => 'database/migrations/2026_08_18_010500_create_najm_hoda_group_meeting_minutes_table.php',
                '--force' => true,
            ]);
        }
    }

    public function test_leadership_confirms_evidence_grounded_decisions_into_minutes(): void
    {
        [$group, $manager, $session, $minute] = $this->meetingFixture();

        $candidate = [
            'title' => 'تأیید برنامه هفتگی',
            'decision' => 'مقرر شد گزارش پیشرفت هر هفته منتشر شود.',
            'source' => 'message:101',
            'evidence' => 'مقرر شد گزارش پیشرفت هر هفته منتشر شود.',
            'fingerprint' => hash('sha256', 'decision-1'),
            'state' => 'candidate',
        ];

        $decisions = Mockery::mock(NajmHodaGroupDecisionCandidateService::class);
        $decisions->shouldReceive('extract')->once()->andReturn([
            'available' => true,
            'candidates' => [$candidate],
            'snapshot' => [],
        ]);
        $this->app->instance(NajmHodaGroupDecisionCandidateService::class, $decisions);

        $service = $this->app->make(NajmHodaPrivateGroupMeetingCommandService::class);
        $context = $this->context($group);

        $preview = $service->intercept($manager, $context, 'مصوبات نشست را استخراج کن', 7001);
        $this->assertSame('awaiting_confirmation', $preview['action_status']);
        $this->assertStringContainsString('مقرر شد گزارش پیشرفت هر هفته منتشر شود', $preview['message']);
        $this->assertSame('candidate', $minute->fresh()->decision_candidates[0]['state']);

        $confirmed = $service->intercept($manager, $context, 'تأیید', 7001);
        $this->assertSame('executed', $confirmed['action_status']);

        $minute = $minute->fresh();
        $this->assertSame('confirmed', $minute->decision_candidates[0]['state']);
        $this->assertSame($manager->id, (int) $minute->decision_candidates[0]['confirmed_by_user_id']);
        $this->assertStringContainsString('تصمیمات/مصوبات تأییدشده', $minute->minutes);
        $this->assertStringContainsString('مقرر شد گزارش پیشرفت هر هفته منتشر شود', $minute->minutes);
        $this->assertSame('draft', $minute->status, 'Confirming decision candidates must not silently approve the entire minutes document.');
    }

    public function test_confirmed_action_from_same_source_links_back_to_confirmed_decision(): void
    {
        [$group, $manager, $session, $minute] = $this->meetingFixture();

        $decisionFingerprint = hash('sha256', 'decision-link');
        $minute->update(['decision_candidates' => [[
            'title' => 'انتشار گزارش هفتگی',
            'decision' => 'مقرر شد گزارش پیشرفت هر هفته منتشر شود.',
            'source' => 'message:501',
            'evidence' => 'مقرر شد گزارش پیشرفت هر هفته منتشر شود.',
            'fingerprint' => $decisionFingerprint,
            'state' => 'confirmed',
            'confirmed_by_user_id' => $manager->id,
            'confirmed_at' => now()->toIso8601String(),
        ]]]);

        $actions = Mockery::mock(NajmHodaGroupActionCandidateService::class);
        $actions->shouldReceive('extract')->once()->andReturn([
            'available' => true,
            'candidates' => [[
                'title' => 'انتشار گزارش پیشرفت',
                'details' => 'گزارش هفتگی آماده و منتشر شود.',
                'assignee_name' => null,
                'due_text' => null,
                'priority' => 'medium',
                'source' => 'message:501',
                'evidence' => 'مقرر شد گزارش پیشرفت هر هفته منتشر شود.',
            ]],
            'snapshot' => [],
        ]);
        $this->app->instance(NajmHodaGroupActionCandidateService::class, $actions);

        $service = $this->app->make(NajmHodaPrivateGroupMeetingCommandService::class);
        $context = $this->context($group);

        $preview = $service->intercept($manager, $context, 'موارد اقدام جلسه را استخراج کن', 7002);
        $this->assertSame('awaiting_confirmation', $preview['action_status']);

        $result = $service->intercept($manager, $context, 'تأیید', 7002);
        $this->assertSame('executed', $result['action_status']);

        $item = NajmHodaGroupActionItem::query()
            ->where('group_id', $group->id)
            ->where('meta->meeting_minute_id', $minute->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($decisionFingerprint, $item->meta['decision_fingerprint'] ?? null);
        $this->assertSame('انتشار گزارش هفتگی', $item->meta['decision_title'] ?? null);
        $this->assertSame('message:501', $item->meta['source'] ?? null);
    }

    /** @return array{0:Group,1:User,2:GroupSession,3:NajmHodaGroupMeetingMinute} */
    private function meetingFixture(): array
    {
        $group = Group::create(['name' => 'Decision workflow ' . uniqid('', true), 'is_open' => true]);
        $manager = User::create([
            'email' => uniqid('decision-manager-', true) . '@example.test',
            'password' => Hash::make('password'),
            'status' => 1,
            'first_name' => 'مدیر',
            'last_name' => 'آزمایشی',
            'is_system' => false,
        ]);
        GroupUser::create(['group_id' => $group->id, 'user_id' => $manager->id, 'role' => 3, 'status' => 1]);

        $session = GroupSession::create([
            'group_id' => $group->id,
            'created_by' => $manager->id,
            'title' => 'نشست تصمیمات',
            'status' => 'ended',
            'starts_at' => now()->subHour(),
            'started_at' => now()->subHour(),
            'ended_at' => now(),
            'ended_by' => $manager->id,
        ]);

        $minute = NajmHodaGroupMeetingMinute::create([
            'group_session_id' => $session->id,
            'group_id' => $group->id,
            'status' => 'draft',
            'summary' => 'خلاصه نشست',
            'minutes' => "پیش‌نویس صورتجلسه رسمی\n\nتصمیمات/مصوبات قطعی:\n• هنوز موردی به عنوان مصوبه قطعی تأیید نشده است.\n\nپایان",
            'evidence_snapshot' => [],
            'decision_candidates' => [],
            'action_candidates' => [],
            'generated_by' => $manager->id,
            'generated_at' => now(),
        ]);

        return [$group, $manager, $session, $minute];
    }

    /** @return array<string,mixed> */
    private function context(Group $group): array
    {
        return [
            'page_kind' => 'group_chat',
            'resource_id' => $group->id,
            'resource' => ['id' => $group->id, 'name' => $group->name],
        ];
    }
}
