<?php

namespace Tests\Feature\NajmHoda;

use App\Models\OccupationalField;
use App\Services\NajmHoda\FounderOps\FounderReferenceApprovalDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FounderReferenceApprovalDecisionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_founder_cannot_approve_reference_candidate(): void
    {
        config()->set('najm-hoda-founder-action-policy.founder_approval.user_ids',[99]);
        $item=OccupationalField::create(['name'=>'صنف آزمایشی','status'=>0]);
        $prepared=app(FounderReferenceApprovalDecisionService::class)->requestApprove('occupational',$item->id,10);
        $requestId=(string)data_get($prepared,'approval_request.id');

        $result=app(FounderReferenceApprovalDecisionService::class)->decideAndExecute($requestId,'approve',10);

        $this->assertFalse($result['success']);
        $this->assertSame(0,(int)$item->fresh()->status);
    }

    public function test_rejecting_request_keeps_candidate_pending(): void
    {
        config()->set('najm-hoda-founder-action-policy.founder_approval.user_ids',[99]);
        $item=OccupationalField::create(['name'=>'صنف نیازمند بررسی','status'=>0]);
        $prepared=app(FounderReferenceApprovalDecisionService::class)->requestApprove('occupational',$item->id,99);

        $result=app(FounderReferenceApprovalDecisionService::class)->decideAndExecute((string)data_get($prepared,'approval_request.id'),'reject',99);

        $this->assertTrue($result['success']);
        $this->assertSame('rejected_request_only',$result['status']);
        $this->assertSame(0,(int)$item->fresh()->status);
    }
}
