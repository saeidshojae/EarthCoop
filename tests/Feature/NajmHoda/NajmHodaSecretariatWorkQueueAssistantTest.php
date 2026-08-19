<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatDispatch;
use App\Modules\Secretariat\Services\SecretariatDispatchService;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use App\Services\NajmHoda\Context\NajmHodaSecretariatReferralAssistant;
use App\Services\NajmHoda\Context\NajmHodaSecretariatWorkQueueAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NajmHodaSecretariatWorkQueueAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_work_queue_is_read_only_and_reports_only_explicit_overdue_items(): void
    {
        [$actor, $recipient, $office] = $this->fixture();
        $record = $this->formalRecord($office, $actor, 'Explicit deadline');
        $other = $this->formalRecord($office, $actor, 'No deadline');
        $dispatches = app(SecretariatDispatchService::class);
        $dispatches->create($record, $actor, [
            'dispatch_type' => 'referral', 'channel' => 'internal', 'target_user_id' => $recipient->id,
            'expects_response' => true, 'due_at' => now()->subHour(), 'follow_up_at' => now()->subMinutes(20),
        ]);
        $dispatches->create($other, $actor, [
            'dispatch_type' => 'referral', 'channel' => 'internal', 'target_user_id' => $recipient->id,
        ]);

        $recordCount = \App\Modules\Secretariat\Models\SecretariatRecord::query()->count();
        $dispatchCount = SecretariatDispatch::query()->count();
        $result = app(NajmHodaSecretariatWorkQueueAssistant::class)->intercept(
            $actor,
            ['page_kind'=>'secretariat_office','resource_type'=>'secretariat_office','resource_id'=>$office->id],
            'کارهای معوق و پیگیری دبیرخانه را بگو'
        );

        $this->assertSame('work_queue', $result['status']);
        $this->assertSame(1, $result['counts']['overdue_dispatches']);
        $this->assertSame(1, $result['counts']['follow_up_due']);
        $this->assertSame(1, $result['counts']['unanswered_correspondence']);
        $this->assertSame($recordCount, \App\Modules\Secretariat\Models\SecretariatRecord::query()->count());
        $this->assertSame($dispatchCount, SecretariatDispatch::query()->count());
    }

    public function test_guided_referral_persists_only_explicit_schedule_after_confirmation(): void
    {
        [$actor, $recipient, $office] = $this->fixture();
        $record = $this->formalRecord($office, $actor, 'Scheduled referral');
        $assistant = app(NajmHodaSecretariatReferralAssistant::class);
        $context = [
            'page_kind'=>'secretariat_record','resource_type'=>'secretariat_record','resource_id'=>$record->id,
            'resource'=>['office_id'=>$office->id],
        ];

        $preview = $assistant->intercept(
            $actor,
            $context,
            "ارجاع بده | کاربر: {$recipient->id} | دستور: بررسی | پاسخ لازم: بله | موعد: 2026-08-25 17:00 | پیگیری: 2026-08-24 10:00",
            910
        );
        $this->assertSame('awaiting_confirmation', $preview['status']);
        $this->assertDatabaseCount('secretariat_dispatches', 0);

        $saved = $assistant->intercept($actor, $context, 'تأیید ارجاع', 910);
        $this->assertSame('dispatch_pending', $saved['status']);
        $dispatch = SecretariatDispatch::query()->firstOrFail();
        $this->assertTrue((bool) $dispatch->expects_response);
        $this->assertSame('2026-08-25 17:00', $dispatch->due_at->format('Y-m-d H:i'));
        $this->assertSame('2026-08-24 10:00', $dispatch->follow_up_at->format('Y-m-d H:i'));
        $this->assertSame('pending', $dispatch->status);
        $this->assertNull($dispatch->dispatched_at);
    }

    private function fixture(): array
    {
        $actor = User::factory()->create(['is_admin' => 1]);
        $recipient = User::factory()->create();
        $group = Group::query()->create(['name' => 'S7 Queue Group', 'group_type' => '0']);
        GroupUser::query()->create(['group_id'=>$group->id,'user_id'=>$recipient->id,'role'=>0,'status'=>1]);
        $office = app(SecretariatOfficeService::class)->create([
            'code'=>'S7Q-'.$group->id,'name'=>'S7 Queue Office','office_type'=>'group','scope_type'=>'group','scope_id'=>$group->id,
        ]);
        return [$actor, $recipient, $office];
    }

    private function formalRecord($office, User $actor, string $title)
    {
        $service = app(SecretariatRecordService::class);
        $record = $service->createDraft($office, $actor, [
            'record_type'=>'official_note','direction'=>'internal','title'=>$title,'body'=>'Body','confidentiality'=>'office_members',
        ]);
        $service->submitForApproval($record, $actor);
        return $service->register($record->fresh(), $actor)->load('office');
    }
}
