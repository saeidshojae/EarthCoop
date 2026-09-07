<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\FounderNajmBaharProjectReviewDraft;
use App\Models\User;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\NajmBahar\Services\ProjectService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;

class FounderNajmBaharProjectDecisionService
{
    public function __construct(
        protected FounderActionRequestService $requests,
        protected FounderActionExecutionService $execution,
        protected NajmHodaAutonomyApprovalService $approvals,
        protected ProjectService $projects
    ) {}

    /** @return array<string,mixed> */
    public function requestApprove(Project $project, int $requestedBy, ?FounderNajmBaharProjectReviewDraft $draft = null, ?string $reasonCode = null): array
    {
        if (! in_array((string) $project->status, ['pending', 'under_review'], true)) {
            return ['success' => false, 'status' => 'blocked', 'reason' => 'project_not_approvable'];
        }
        if ($draft && ((int) $draft->project_id !== (int) $project->id || (string) $draft->status !== 'draft')) {
            return ['success' => false, 'status' => 'invalid_review_draft', 'reason' => 'project_review_draft_mismatch'];
        }

        return $this->requests->prepare('najm_bahar', 'approve_project', [
            'entity_type' => 'najm_bahar_project',
            'entity_id' => (int) $project->id,
            'requested_by' => $requestedBy,
            'reason_code' => $reasonCode ?: 'najm-bahar-approve-project-' . (int) $project->id,
            'source_event' => 'founder_ops_najm_bahar_project',
            'review_draft_id' => $draft?->id,
        ]);
    }

    /** @return array<string,mixed> */
    public function decideAndExecute(string $requestId, string $decision, int $founderId, ?string $reason = null): array
    {
        if (! in_array($founderId, $this->founderIds(), true)) {
            return ['success' => false, 'status' => 'forbidden', 'reason' => 'founder_not_authorized'];
        }

        $pending = collect($this->approvals->pending(200))
            ->first(fn (array $item): bool => (string) ($item['id'] ?? '') === $requestId);
        if (! is_array($pending)) {
            return ['success' => false, 'status' => 'not_found', 'reason' => 'approval_request_not_pending'];
        }

        if ((string) data_get($pending, 'plan_item.domain') !== 'najm_bahar'
            || (string) data_get($pending, 'plan_item.domain_action') !== 'approve_project'
            || (string) data_get($pending, 'context.entity_type') !== 'najm_bahar_project') {
            return ['success' => false, 'status' => 'invalid_request', 'reason' => 'approval_contract_mismatch'];
        }

        $projectId = (int) data_get($pending, 'context.entity_id', 0);
        $project = $projectId > 0 ? Project::query()->find($projectId) : null;
        if (! $project) {
            return ['success' => false, 'status' => 'not_found', 'reason' => 'najm_bahar_project_not_found'];
        }
        if (! in_array((string) $project->status, ['pending', 'under_review'], true)) {
            return ['success' => false, 'status' => 'blocked', 'reason' => 'project_not_approvable'];
        }

        $founder = User::query()->find($founderId);
        if (! $founder) {
            return ['success' => false, 'status' => 'not_found', 'reason' => 'founder_user_not_found'];
        }

        $draftId = (int) data_get($pending, 'context.review_draft_id', 0);
        $draft = $draftId > 0 ? FounderNajmBaharProjectReviewDraft::query()->find($draftId) : null;
        if ($draftId > 0 && (! $draft || (int) $draft->project_id !== $projectId || (string) $draft->status !== 'draft')) {
            return ['success' => false, 'status' => 'invalid_review_draft', 'reason' => 'project_review_draft_mismatch'];
        }

        $decisionResult = $this->approvals->decide($requestId, $decision, $founderId, $reason);
        if (! (bool) ($decisionResult['success'] ?? false)) {
            return $decisionResult;
        }

        if ($decision === 'reject') {
            if ($draft) $draft->update(['status' => 'rejected']);
            return ['success' => true, 'status' => 'rejected', 'project_id' => $projectId, 'review_draft_id' => $draft?->id];
        }

        return $this->execution->execute(
            'najm_bahar',
            'approve_project',
            function () use ($project, $founder, $reason, $draft): array {
                $approved = $this->projects->approveProject($project, $founder, $reason);
                if ($draft) $draft->update(['status' => 'approved']);
                return [
                    'project_id' => (int) $approved->id,
                    'project_status' => (string) $approved->status,
                    'approved_at' => $approved->approved_at?->toIso8601String(),
                    'review_draft_id' => $draft?->id,
                ];
            },
            $requestId,
            ['entity_type' => 'najm_bahar_project', 'entity_id' => $projectId, 'requested_by' => $founderId]
        );
    }

    /** @return array<int,int> */
    protected function founderIds(): array
    {
        return array_values(array_filter(array_map(
            'intval',
            (array) config('najm-hoda-founder-action-policy.founder_approval.user_ids', [])
        )));
    }
}
