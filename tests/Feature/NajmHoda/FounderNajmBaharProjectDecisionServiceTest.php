<?php

namespace Tests\Feature\NajmHoda;

use App\Models\FounderNajmBaharProjectReviewDraft;
use App\Models\User;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\NajmBahar\Models\ProjectReview;
use App\Services\NajmHoda\FounderOps\FounderExecutiveConnectivityService;
use App\Services\NajmHoda\FounderOps\FounderNajmBaharProjectDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FounderNajmBaharProjectDecisionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.autonomy.human_escalation.enabled' => true,
            'najm-hoda.runtime.autonomy.human_escalation.notify_admins' => false,
        ]);
        Cache::flush();
        Notification::fake();
    }

    public function test_project_approval_requires_founder_and_uses_canonical_project_service(): void
    {
        $founder = User::factory()->create(['is_system' => false]);
        $owner = User::factory()->create(['is_system' => false]);
        config(['najm-hoda-founder-action-policy.founder_approval.user_ids' => [$founder->id]]);

        $project = $this->project($owner, 'pending');
        $draft = FounderNajmBaharProjectReviewDraft::query()->create([
            'project_id' => $project->id,
            'requested_by_user_id' => $founder->id,
            'status' => 'draft',
            'summary' => 'Founder review',
            'findings' => [],
            'reason_code' => 'approval-test',
        ]);

        $service = app(FounderNajmBaharProjectDecisionService::class);
        $request = $service->requestApprove($project, $founder->id, $draft, 'approve-project-'.$project->id);

        $this->assertSame('awaiting_approval', $request['status']);
        $this->assertSame('pending', $project->fresh()->status);
        $requestId = (string) data_get($request, 'approval_request.id', '');
        $this->assertNotSame('', $requestId);

        $result = $service->decideAndExecute($requestId, 'approve', $founder->id, 'Founder approved project');

        $this->assertTrue($result['success']);
        $this->assertSame('executed', $result['status']);
        $this->assertSame('approved', $project->fresh()->status);
        $this->assertNotNull($project->fresh()->approved_at);
        $this->assertSame('approved', $draft->fresh()->status);
        $this->assertDatabaseHas('najm_bahar_project_reviews', [
            'project_id' => $project->id,
            'reviewer_id' => $founder->id,
            'action' => 'approved',
        ]);
        $this->assertSame(
            'connected',
            data_get(app(FounderExecutiveConnectivityService::class)->report(), 'domains.najm_bahar.actions.approve_project.state')
        );
    }

    public function test_rejecting_founder_action_does_not_reject_or_approve_project(): void
    {
        $founder = User::factory()->create(['is_system' => false]);
        $owner = User::factory()->create(['is_system' => false]);
        config(['najm-hoda-founder-action-policy.founder_approval.user_ids' => [$founder->id]]);

        $project = $this->project($owner, 'under_review');
        $draft = FounderNajmBaharProjectReviewDraft::query()->create([
            'project_id' => $project->id,
            'requested_by_user_id' => $founder->id,
            'status' => 'draft',
            'summary' => 'Review pending founder decision',
            'findings' => [],
        ]);

        $service = app(FounderNajmBaharProjectDecisionService::class);
        $request = $service->requestApprove($project, $founder->id, $draft);
        $requestId = (string) data_get($request, 'approval_request.id', '');

        $result = $service->decideAndExecute($requestId, 'reject', $founder->id, 'Do not approve yet');

        $this->assertTrue($result['success']);
        $this->assertSame('rejected', $result['status']);
        $this->assertSame('under_review', $project->fresh()->status);
        $this->assertNull($project->fresh()->approved_at);
        $this->assertSame('rejected', $draft->fresh()->status);
        $this->assertSame(0, ProjectReview::query()->where('project_id', $project->id)->where('action', 'approved')->count());
    }

    public function test_unauthorized_user_cannot_approve_project(): void
    {
        $founder = User::factory()->create(['is_system' => false]);
        $other = User::factory()->create(['is_system' => false]);
        $owner = User::factory()->create(['is_system' => false]);
        config(['najm-hoda-founder-action-policy.founder_approval.user_ids' => [$founder->id]]);

        $project = $this->project($owner, 'pending');
        $service = app(FounderNajmBaharProjectDecisionService::class);
        $request = $service->requestApprove($project, $founder->id);
        $requestId = (string) data_get($request, 'approval_request.id', '');

        $result = $service->decideAndExecute($requestId, 'approve', $other->id);

        $this->assertFalse($result['success']);
        $this->assertSame('forbidden', $result['status']);
        $this->assertSame('pending', $project->fresh()->status);
    }

    private function project(User $owner, string $status): Project
    {
        return Project::query()->create([
            'owner_type' => User::class,
            'owner_id' => $owner->id,
            'title' => 'Founder approval project',
            'summary' => 'Project summary',
            'status' => $status,
        ]);
    }
}
