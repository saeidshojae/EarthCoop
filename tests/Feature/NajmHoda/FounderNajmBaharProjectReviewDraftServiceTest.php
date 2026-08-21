<?php

namespace Tests\Feature\NajmHoda;

use App\Models\FounderNajmBaharProjectReviewDraft;
use App\Models\User;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\NajmBahar\Models\ProjectReview;
use App\Services\NajmHoda\FounderOps\FounderExecutiveConnectivityService;
use App\Services\NajmHoda\FounderOps\FounderLowRiskDomainActionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FounderNajmBaharProjectReviewDraftServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_review_draft_is_isolated_from_official_project_state_and_review_history(): void
    {
        $owner = User::factory()->create(['is_system' => false]);
        $project = Project::query()->create([
            'owner_type' => User::class,
            'owner_id' => $owner->id,
            'title' => 'Incomplete founder review project',
            'summary' => '',
            'status' => 'pending',
        ]);

        $before = [
            'status' => (string) $project->status,
            'approved_value_min' => $project->approved_value_min,
            'approved_value_max' => $project->approved_value_max,
            'current_base_value' => $project->current_base_value,
            'official_reviews' => ProjectReview::query()->where('project_id', $project->id)->count(),
        ];

        $service = app(FounderLowRiskDomainActionService::class);
        $result = $service->execute('najm_bahar', 'draft_project_review', [
            'entity_id' => $project->id,
            'requested_by' => $owner->id,
            'reason_code' => 'najm-bahar-project-review-test-'.$project->id,
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('draft_ready', $result['status']);
        $this->assertGreaterThan(0, (int) $result['finding_count']);

        $draft = FounderNajmBaharProjectReviewDraft::query()->findOrFail((int) $result['draft_id']);
        $this->assertSame($project->id, $draft->project_id);
        $this->assertSame('draft', $draft->status);
        $this->assertNotEmpty((array) $draft->findings);

        $fresh = $project->fresh();
        $this->assertSame($before['status'], (string) $fresh->status);
        $this->assertSame($before['approved_value_min'], $fresh->approved_value_min);
        $this->assertSame($before['approved_value_max'], $fresh->approved_value_max);
        $this->assertSame($before['current_base_value'], $fresh->current_base_value);
        $this->assertSame(
            $before['official_reviews'],
            ProjectReview::query()->where('project_id', $project->id)->count()
        );

        $this->assertSame(
            'connected',
            data_get(app(FounderExecutiveConnectivityService::class)->report(), 'domains.najm_bahar.actions.draft_project_review.state')
        );
    }

    public function test_project_review_draft_is_idempotent_while_an_open_draft_exists(): void
    {
        $owner = User::factory()->create(['is_system' => false]);
        $project = Project::query()->create([
            'owner_type' => User::class,
            'owner_id' => $owner->id,
            'title' => 'Idempotent review project',
            'summary' => 'Minimal summary',
            'status' => 'pending',
        ]);

        $service = app(FounderLowRiskDomainActionService::class);
        $first = $service->execute('najm_bahar', 'draft_project_review', [
            'entity_id' => $project->id,
            'requested_by' => $owner->id,
            'reason_code' => 'nb-review-idempotency-'.$project->id,
        ]);
        $second = $service->execute('najm_bahar', 'draft_project_review', [
            'entity_id' => $project->id,
            'requested_by' => $owner->id,
            'reason_code' => 'nb-review-idempotency-'.$project->id,
        ]);

        $this->assertSame($first['draft_id'], $second['draft_id']);
        $this->assertSame('existing', $second['mode']);
        $this->assertSame(
            1,
            FounderNajmBaharProjectReviewDraft::query()->where('project_id', $project->id)->count()
        );
    }
}
