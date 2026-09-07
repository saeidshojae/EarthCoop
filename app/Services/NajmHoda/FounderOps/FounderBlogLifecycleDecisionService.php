<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\Blog;
use App\Services\Blog\BlogLifecycleService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;

class FounderBlogLifecycleDecisionService
{
    public function __construct(
        protected FounderActionRequestService $requests,
        protected FounderActionExecutionService $execution,
        protected NajmHodaAutonomyApprovalService $approvals,
        protected BlogLifecycleService $blogs,
    ) {}

    /** @return array<string,mixed> */
    public function requestDelete(Blog $blog, int $requestedBy, ?string $reasonCode = null): array
    {
        return $this->requests->prepare('blog', 'delete_post', [
            'entity_type' => 'blog',
            'entity_id' => (int) $blog->id,
            'requested_by' => $requestedBy,
            'reason_code' => $reasonCode ?: 'blog-delete-' . (int) $blog->id,
            'source_event' => 'founder_ops_blog_post',
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

        if ((string) data_get($pending, 'plan_item.domain') !== 'blog'
            || (string) data_get($pending, 'plan_item.domain_action') !== 'delete_post'
            || (string) data_get($pending, 'context.entity_type') !== 'blog') {
            return ['success' => false, 'status' => 'invalid_request', 'reason' => 'approval_contract_mismatch'];
        }

        $blogId = (int) data_get($pending, 'context.entity_id', 0);
        $blog = $blogId > 0 ? Blog::query()->find($blogId) : null;
        if (! $blog) {
            return ['success' => false, 'status' => 'not_found', 'reason' => 'blog_post_not_found'];
        }

        $decisionResult = $this->approvals->decide($requestId, $decision, $founderId, $reason);
        if (! (bool) ($decisionResult['success'] ?? false)) {
            return $decisionResult;
        }
        if ($decision === 'reject') {
            return ['success' => true, 'status' => 'rejected', 'blog_id' => $blogId];
        }

        return $this->execution->execute(
            'blog',
            'delete_post',
            fn (): array => $this->blogs->delete($blog, $founderId),
            $requestId,
            ['entity_type' => 'blog', 'entity_id' => $blogId, 'requested_by' => $founderId]
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
