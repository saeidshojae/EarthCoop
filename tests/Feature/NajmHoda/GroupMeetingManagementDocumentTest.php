<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupSession;
use App\Models\NajmHodaGroupActionItem;
use App\Models\NajmHodaGroupMeetingMinute;
use App\Models\User;
use App\Services\NajmHoda\NajmHodaGroupMeetingMinutesService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GroupMeetingManagementDocumentTest extends TestCase
{
    use DatabaseTransactions;

    public function test_management_document_renders_confirmed_decisions_and_execution_state(): void
    {
        $group = Group::create(['name' => 'Management minutes group', 'is_open' => 1]);
        $approver = User::create([
            'email' => uniqid('minutes-manager-', true) . '@example.test',
            'password' => Hash::make('password'),
            'status' => 1,
            'first_name' => 'مدیر',
            'last_name' => 'جلسه',
        ]);

        $session = GroupSession::create([
            'group_id' => $group->id,
            'created_by' => $approver->id,
            'ended_by' => $approver->id,
            'title' => 'نشست برنامه اجرایی',
            'subject' => 'اجرای مصوبه',
            'agenda' => 'تعیین اقدام و مسئول',
            'status' => 'ended',
            'starts_at' => now()->subHours(2),
            'started_at' => now()->subHours(2),
            'ended_at' => now()->subHour(),
        ]);

        $minute = NajmHodaGroupMeetingMinute::create([
            'group_session_id' => $session->id,
            'group_id' => $group->id,
            'status' => 'draft',
            'summary' => 'خلاصه',
            'minutes' => 'پیش‌نویس',
            'evidence_snapshot' => [
                'session' => ['title' => $session->title],
                'participants' => ['عضو اول', 'عضو دوم'],
                'counts' => ['messages' => 6, 'posts' => 1, 'polls' => 1],
            ],
            'decision_candidates' => [[
                'title' => 'تصمیم خرید تجهیز',
                'decision' => 'خرید تجهیز تا پایان هفته انجام شود.',
                'source' => 'message:22',
                'evidence' => 'تصویب شد خرید تجهیز تا پایان هفته انجام شود.',
                'state' => 'confirmed',
                'fingerprint' => 'decision-fingerprint-1',
                'confirmed_by_user_id' => $approver->id,
                'confirmed_at' => now()->toIso8601String(),
            ]],
            'action_candidates' => [],
            'generated_by' => $approver->id,
            'generated_at' => now(),
        ]);

        $action = NajmHodaGroupActionItem::create([
            'group_id' => $group->id,
            'title' => 'خرید تجهیز',
            'details' => 'پیگیری خرید تجهیز تصویب‌شده',
            'assignee_name' => 'مسئول تدارکات',
            'due_at' => now()->addDay(),
            'priority' => 'high',
            'status' => 'open',
            'meta' => [
                'origin' => 'najm_hoda_meeting_minutes',
                'meeting_minute_id' => $minute->id,
                'group_session_id' => $session->id,
                'decision_fingerprint' => 'decision-fingerprint-1',
                'decision_title' => 'تصمیم خرید تجهیز',
            ],
        ]);

        $service = app(NajmHodaGroupMeetingMinutesService::class);
        $draft = $service->renderManagementDocument($minute);

        $this->assertStringContainsString('تصمیمات/مصوبات تأییدشده', $draft);
        $this->assertStringContainsString('خرید تجهیز تا پایان هفته انجام شود.', $draft);
        $this->assertStringContainsString('خرید تجهیز', $draft);
        $this->assertStringContainsString('مسئول تدارکات', $draft);
        $this->assertStringContainsString('مرتبط با تصمیم: تصمیم خرید تجهیز', $draft);

        $approved = $service->approve($minute, $approver);
        $officialSnapshot = (string) $approved->minutes;
        $this->assertStringContainsString('وضعیت: باز', $officialSnapshot);
        $this->assertSame('approved', $approved->status);

        $action->forceFill(['status' => 'done'])->save();

        $live = $service->renderManagementDocument($approved->fresh(), true);
        $this->assertStringContainsString('وضعیت: انجام‌شده', $live);
        $this->assertStringContainsString('وضعیت اجرایی جاری:', $live);
        $this->assertStringContainsString('انجام‌شده: 1', $live);

        $this->assertSame(
            $officialSnapshot,
            (string) $approved->fresh()->minutes,
            'Post-approval execution changes must not rewrite the official meeting-minutes snapshot.'
        );
    }
}
