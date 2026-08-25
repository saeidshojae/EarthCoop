<?php

namespace Tests\Feature\NajmHoda;

use App\Http\Controllers\Admin\FounderMinistryChatController;
use App\Services\NajmHoda\FounderOps\FounderApprovalInboxService;
use App\Services\NajmHoda\FounderOps\FounderAttentionService;
use App\Services\NajmHoda\FounderOps\FounderAuthoritySnapshotService;
use App\Services\NajmHoda\FounderOps\FounderExecutiveWorkQueueService;
use App\Services\NajmHoda\FounderOps\FounderMinistryChatService;
use App\Services\NajmHoda\FounderOps\FounderOperationsSnapshotService;
use Mockery;
use Tests\TestCase;

class FounderMinistryChatServiceTest extends TestCase
{
    protected function service(): FounderMinistryChatService
    {
        return new FounderMinistryChatService(
            Mockery::mock(FounderAttentionService::class),
            Mockery::mock(FounderExecutiveWorkQueueService::class),
            Mockery::mock(FounderApprovalInboxService::class),
            Mockery::mock(FounderOperationsSnapshotService::class),
            Mockery::mock(FounderAuthoritySnapshotService::class),
        );
    }

    protected function makeService($attention, $queue, $approvals, $snapshots, $authority = null): FounderMinistryChatService
    {
        return new FounderMinistryChatService(
            $attention,
            $queue,
            $approvals,
            $snapshots,
            $authority ?: Mockery::mock(FounderAuthoritySnapshotService::class),
        );
    }

    public function test_morning_brief_uses_canonical_founder_ops_read_models_and_global_command_bar(): void
    {
        $attention = Mockery::mock(FounderAttentionService::class);
        $queue = Mockery::mock(FounderExecutiveWorkQueueService::class);
        $approvals = Mockery::mock(FounderApprovalInboxService::class);
        $snapshots = Mockery::mock(FounderOperationsSnapshotService::class);

        $brief = [
            'generated_at' => '2026-08-25T08:00:00+00:00',
            'summary' => ['P0' => 1, 'P1' => 2, 'P2' => 3, 'P3' => 4],
        ];
        $workQueue = [
            'needs_founder_decision' => 5,
            'prepared_by_najm_hoda' => 6,
            'attention_only' => 7,
            'items' => [['kind' => 'attention', 'priority' => 'P0', 'domain' => 'support', 'title' => 'فوری']],
        ];

        $attention->shouldReceive('brief')->once()->with(24)->andReturn($brief);
        $queue->shouldReceive('snapshot')->once()->with(24, 100)->andReturn($workQueue);

        $service = $this->makeService($attention, $queue, $approvals, $snapshots);
        $result = $service->respond('morning_brief', 24);

        $this->assertTrue($result['success']);
        $this->assertSame('morning_brief', data_get($result, 'management.intent'));
        $this->assertSame(3, data_get($result, 'management.summary_cards.urgent'));
        $this->assertSame(5, data_get($result, 'management.global_summary_cards.founder_decisions'));
        $this->assertSame(6, data_get($result, 'management.global_summary_cards.prepared'));
        $this->assertSame(7, data_get($result, 'management.global_summary_cards.information'));
        $this->assertStringContainsString('5 تصمیم منتظر شما', $result['message']);
        $this->assertStringContainsString('#support', data_get($result, 'management.items.0.ui.workbench_url'));
    }

    public function test_communications_surfaces_only_communication_domains_and_existing_lifecycle_actions(): void
    {
        $attention = Mockery::mock(FounderAttentionService::class);
        $queue = Mockery::mock(FounderExecutiveWorkQueueService::class);
        $approvals = Mockery::mock(FounderApprovalInboxService::class);
        $snapshots = Mockery::mock(FounderOperationsSnapshotService::class);

        $workQueue = [
            'needs_founder_decision' => 1,
            'prepared_by_najm_hoda' => 2,
            'attention_only' => 1,
            'items' => [
                ['kind' => 'approval', 'domain' => 'email', 'priority' => 'P1', 'approval_request_id' => 'req-email-1', 'entity_type' => 'founder_email_draft', 'entity_id' => 8],
                ['kind' => 'proposal', 'domain' => 'blog', 'priority' => 'P2', 'entity_type' => 'founder_content_draft', 'entity_id' => 9],
                ['kind' => 'proposal', 'domain' => 'notifications', 'priority' => 'P2', 'entity_type' => 'founder_announcement_draft', 'entity_id' => 10],
                ['kind' => 'attention', 'domain' => 'stock', 'priority' => 'P0'],
            ],
        ];

        $queue->shouldReceive('snapshot')->once()->with(24, 100)->andReturn($workQueue);
        $attention->shouldReceive('brief')->once()->with(24)->andReturn(['summary'=>['P0'=>0,'P1'=>0]]);

        $service = $this->makeService($attention, $queue, $approvals, $snapshots);
        $result = $service->respond('communications', 24);

        $this->assertTrue($result['success']);
        $this->assertCount(3, data_get($result, 'management.items'));
        $this->assertSame(1, data_get($result, 'management.summary_cards.pending_decisions'));
        $this->assertSame(2, data_get($result, 'management.summary_cards.prepared'));

        $emailActions = data_get($result, 'management.items.0.ui.actions', []);
        $this->assertSame('decision', data_get($emailActions, '0.type'));
        $this->assertSame('approve', data_get($emailActions, '0.decision'));
        $this->assertStringContainsString('/email-approvals/req-email-1/decision', data_get($emailActions, '0.url'));

        $contentActions = data_get($result, 'management.items.1.ui.actions', []);
        $this->assertSame('request_approval', data_get($contentActions, '0.type'));
        $this->assertStringContainsString('/content-drafts/9/request-publish', data_get($contentActions, '0.url'));
    }

    public function test_reference_data_domain_uses_snapshot_metrics_and_canonical_queue(): void
    {
        $attention = Mockery::mock(FounderAttentionService::class);
        $queue = Mockery::mock(FounderExecutiveWorkQueueService::class);
        $approvals = Mockery::mock(FounderApprovalInboxService::class);
        $snapshots = Mockery::mock(FounderOperationsSnapshotService::class);

        $snapshots->shouldReceive('snapshot')->once()->with(24)->andReturn([
            'approvals' => ['total'=>7, 'references'=>['total'=>3], 'locations'=>['total'=>4]],
        ]);
        $workQueue = [
            'needs_founder_decision'=>1,'prepared_by_najm_hoda'=>0,'attention_only'=>1,
            'items'=>[
                ['kind'=>'approval','domain'=>'reference_data','priority'=>'P1','approval_request_id'=>'ref-1','entity_type'=>'specialty','entity_id'=>22],
                ['kind'=>'attention','domain'=>'stock','priority'=>'P0'],
            ],
        ];
        $queue->shouldReceive('snapshot')->once()->with(24,100)->andReturn($workQueue);
        $attention->shouldReceive('brief')->once()->with(24)->andReturn(['summary'=>['P0'=>0,'P1'=>1]]);

        $service = $this->makeService($attention, $queue, $approvals, $snapshots);
        $result = $service->respond('reference_data', 24);

        $this->assertSame(7, data_get($result, 'management.summary_cards.pending.value'));
        $this->assertSame(3, data_get($result, 'management.summary_cards.reference_pending.value'));
        $this->assertSame(4, data_get($result, 'management.summary_cards.location_pending.value'));
        $this->assertCount(1, data_get($result, 'management.items'));
        $this->assertStringContainsString('#reference-data', data_get($result, 'management.items.0.ui.workbench_url'));
    }

    public function test_typed_management_questions_map_to_read_only_general_and_domain_intents(): void
    {
        $service = $this->service();

        $this->assertSame('morning_brief', $service->inferIntent('از دیشب تا الان چه خبر مهمی داریم؟'));
        $this->assertSame('pending_approvals', $service->inferIntent('چه چیزهایی منتظر تأیید من است؟'));
        $this->assertSame('communications', $service->inferIntent('وضعیت ایمیل‌ها و اطلاعیه‌ها چیست؟'));
        $this->assertSame('system_health', $service->inferIntent('سلامت سامانه چطور است؟'));
        $this->assertSame('end_of_day', $service->inferIntent('پایان روز چه باقی مانده؟'));
        $this->assertSame('urgent_items', $service->inferIntent('کارهای فوری من چیست؟'));
        $this->assertSame('users_registration', $service->inferIntent('کاربران جدید و ثبت‌نام چه وضعی دارند؟'));
        $this->assertSame('reference_data', $service->inferIntent('مکان و صنف و تخصص منتظر چیست؟'));
        $this->assertSame('support_moderation', $service->inferIntent('تیکت و شکایت‌های مهم را نشان بده'));
        $this->assertSame('governance', $service->inferIntent('وضع انتخابات چیست؟'));
        $this->assertSame('najm_bahar', $service->inferIntent('نجم بهار چه هشدارهایی دارد؟'));
        $this->assertSame('stock', $service->inferIntent('وضع مزایده سهام و تسویه چیست؟'));
        $this->assertSame('secretariat', $service->inferIntent('پیگیری‌های دبیرخانه چه وضعی دارند؟'));
        $this->assertSame('authority', $service->inferIntent('چه اختیارهایی به نجم واگذار شده؟'));
    }

    public function test_executable_or_unknown_typed_request_is_not_inferred_as_an_action(): void
    {
        $service = $this->service();

        $this->assertNull($service->inferIntent('همه کاربران را حذف کن'));
        $this->assertNull($service->inferIntent('این ایمیل را همین الان ارسال کن'));
        $this->assertNull($service->inferIntent('اطلاعیه شماره 12 را منتشر کن'));
        $this->assertNull($service->inferIntent('همه تصمیم‌ها را تأیید کن'));
    }

    public function test_unknown_management_intent_fails_closed_without_calling_any_read_model(): void
    {
        $attention = Mockery::mock(FounderAttentionService::class);
        $queue = Mockery::mock(FounderExecutiveWorkQueueService::class);
        $approvals = Mockery::mock(FounderApprovalInboxService::class);
        $snapshots = Mockery::mock(FounderOperationsSnapshotService::class);
        $authority = Mockery::mock(FounderAuthoritySnapshotService::class);

        $attention->shouldNotReceive('brief');
        $queue->shouldNotReceive('snapshot');
        $approvals->shouldNotReceive('snapshot');
        $snapshots->shouldNotReceive('snapshot');
        $authority->shouldNotReceive('snapshot');

        $service = $this->makeService($attention, $queue, $approvals, $snapshots, $authority);
        $result = $service->respond('delete_everything', 24);

        $this->assertFalse($result['success']);
        $this->assertSame('unknown_management_intent', data_get($result, 'management.meta.reason'));
    }

    public function test_ministry_routes_are_registered_under_founder_ops_boundary(): void
    {
        $this->assertSame(url('/admin/najm-hoda/founder-ops/ministry/chat'), route('admin.najm-hoda.founder-ops.ministry.chat'));
        $this->assertSame(url('/admin/najm-hoda/founder-ops/ministry/readiness'), route('admin.najm-hoda.founder-ops.ministry.readiness'));
    }

    public function test_readiness_contract_identifies_deployed_ministry_and_preserves_execution_boundary(): void
    {
        $response = app(FounderMinistryChatController::class)->readiness();
        $payload = $response->getData(true);

        $this->assertTrue($payload['success']);
        $this->assertSame('founder_ministry', $payload['feature']);
        $this->assertSame(FounderMinistryChatController::UAT_VERSION, $payload['version']);
        $this->assertSame('read_only_decision_support', $payload['mode']);
        $this->assertSame(FounderMinistryChatService::INTENTS, $payload['read_only_intents']);
        $this->assertFalse($payload['typed_execution_inference']);
        $this->assertFalse($payload['approval_bypass']);
        $this->assertTrue($payload['action_cards']);
        $this->assertSame('existing_founder_ops_approval_authority_lifecycle', $payload['execution_boundary']);
    }
}
