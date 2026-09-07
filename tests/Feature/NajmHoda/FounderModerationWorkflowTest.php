<?php

namespace Tests\Feature\NajmHoda;

use App\Models\ModerationCaseSummary;
use App\Models\Report;
use App\Models\User;
use App\Services\Moderation\ModerationCaseSummaryService;
use App\Services\NajmHoda\FounderOps\FounderModerationDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FounderModerationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_case_summary_is_deduplicated_and_classifies_high_risk_report(): void
    {
        $user = User::factory()->create();
        $report = Report::create([
            'type'=>'message','reported_by'=>$user->id,'reason'=>'تهدید و خشونت',
            'description'=>'کاربر دیگری را تهدید کرده است','status'=>'pending','priority'=>'high',
        ]);

        $first = app(ModerationCaseSummaryService::class)->prepare('report',$report->id,'case-1');
        $second = app(ModerationCaseSummaryService::class)->prepare('report',$report->id,'case-1');

        $this->assertSame('created',$first['mode']);
        $this->assertSame('existing',$second['mode']);
        $this->assertSame('threat_or_violence',$first['classification']);
        $this->assertSame('high',$first['severity']);
        $this->assertSame(1, ModerationCaseSummary::where('source_type','report')->where('source_id',$report->id)->count());
    }

    public function test_non_founder_cannot_resolve_report(): void
    {
        config()->set('najm-hoda-founder-action-policy.founder_approval.user_ids',[99]);
        $user = User::factory()->create();
        $report = Report::create([
            'type'=>'message','reported_by'=>$user->id,'reason'=>'spam','status'=>'pending','priority'=>'medium',
        ]);

        $prepared = app(FounderModerationDecisionService::class)->requestResolve('report',$report->id,10);
        $result = app(FounderModerationDecisionService::class)->decideAndExecute((string)data_get($prepared,'approval_request.id'),'approve',10);

        $this->assertFalse($result['success']);
        $this->assertSame('founder_not_authorized',$result['reason']);
        $this->assertSame('pending',$report->fresh()->status);
    }
}
