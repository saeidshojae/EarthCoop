<?php

namespace Tests\Feature\NajmHoda;

use App\Models\ModerationCaseSummary;
use App\Models\Report;
use App\Models\User;
use App\Services\NajmHoda\FounderOps\FounderModerationDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FounderModerationDecisionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_founder_resolution_persists_and_is_outcome_verified(): void
    {
        $founder = User::factory()->create();
        $reporter = User::factory()->create();
        config()->set('najm-hoda-founder-action-policy.founder_approval.user_ids', [$founder->id]);

        $report = Report::create([
            'type' => 'user',
            'reported_by' => $reporter->id,
            'reported_item_id' => $founder->id,
            'reason' => 'test',
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        ModerationCaseSummary::create([
            'source_type' => 'report',
            'source_id' => $report->id,
            'summary' => 'خلاصه اولیه نجم هدا برای بررسی گزارش',
            'status' => 'draft',
        ]);

        $service = app(FounderModerationDecisionService::class);
        $prepared = $service->requestResolve('report', $report->id, $founder->id);
        $requestId = (string) data_get($prepared, 'approval_request.id');

        $result = $service->decideAndExecute($requestId, 'approve', $founder->id, 'بررسی و حل شد');

        $this->assertTrue($result['success']);
        $this->assertSame('resolved', $report->fresh()->status);
        $this->assertSame($founder->id, (int) $report->fresh()->reviewed_by);
        $this->assertTrue((bool) data_get($result, 'verification.verified'));
        $this->assertSame('verified', (string) data_get($result, 'verification.status'));
        $this->assertDatabaseHas('moderation_case_summaries', [
            'source_type' => 'report',
            'source_id' => $report->id,
            'status' => 'resolved',
        ]);
    }

    public function test_rejected_resolution_request_does_not_mutate_report(): void
    {
        $founder = User::factory()->create();
        $reporter = User::factory()->create();
        config()->set('najm-hoda-founder-action-policy.founder_approval.user_ids', [$founder->id]);

        $report = Report::create([
            'type' => 'user',
            'reported_by' => $reporter->id,
            'reported_item_id' => $founder->id,
            'reason' => 'test',
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        $service = app(FounderModerationDecisionService::class);
        $prepared = $service->requestResolve('report', $report->id, $founder->id);
        $result = $service->decideAndExecute(
            (string) data_get($prepared, 'approval_request.id'),
            'reject',
            $founder->id,
            'نیازمند بررسی بیشتر'
        );

        $this->assertTrue($result['success']);
        $this->assertSame('rejected_request_only', $result['status']);
        $this->assertSame('pending', $report->fresh()->status);
    }
}
