<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Services\Content\ContentManagementService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;

class FounderContentLifecycleDecisionService
{
    public function __construct(
        protected FounderActionRequestService $requests,
        protected FounderActionExecutionService $execution,
        protected NajmHodaAutonomyApprovalService $approvals,
        protected ContentManagementService $content
    ) {}

    /** @return array<string,mixed> */
    public function request(string $action, string $entityType, int $entityId, int $requestedBy, ?string $reasonCode = null): array
    {
        if (! in_array($action, ['publish_content','delete_content'], true)) {
            return ['success'=>false,'status'=>'invalid_action','reason'=>'unsupported_content_lifecycle_action'];
        }

        $this->content->find($entityType, $entityId);

        return $this->requests->prepare('content', $action, [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'requested_by' => $requestedBy,
            'reason_code' => $reasonCode ?: $action . '-' . $entityType . '-' . $entityId,
            'source_event' => 'founder_ops_content_lifecycle',
        ]);
    }

    /** @return array<string,mixed> */
    public function decideAndExecute(string $requestId, string $decision, int $founderId, ?string $reason = null): array
    {
        if (! in_array($founderId, $this->founderIds(), true)) {
            return ['success'=>false,'status'=>'forbidden','reason'=>'founder_not_authorized'];
        }

        $pending = collect($this->approvals->pending(200))
            ->first(fn (array $item): bool => (string) ($item['id'] ?? '') === $requestId);
        if (! is_array($pending)) {
            return ['success'=>false,'status'=>'not_found','reason'=>'approval_request_not_pending'];
        }

        $domain = (string) data_get($pending, 'plan_item.domain');
        $action = (string) data_get($pending, 'plan_item.domain_action');
        $entityType = (string) data_get($pending, 'context.entity_type');
        $entityId = (int) data_get($pending, 'context.entity_id', 0);

        if ($domain !== 'content' || ! in_array($action, ['publish_content','delete_content'], true)) {
            return ['success'=>false,'status'=>'invalid_request','reason'=>'approval_contract_mismatch'];
        }
        if (! in_array($entityType, ['page','faq_question'], true) || $entityId < 1) {
            return ['success'=>false,'status'=>'invalid_request','reason'=>'approval_entity_mismatch'];
        }

        $this->content->find($entityType, $entityId);

        $decisionResult = $this->approvals->decide($requestId, $decision, $founderId, $reason);
        if (! (bool) ($decisionResult['success'] ?? false)) {
            return $decisionResult;
        }
        if ($decision === 'reject') {
            return [
                'success'=>true,
                'status'=>'rejected',
                'entity_type'=>$entityType,
                'entity_id'=>$entityId,
                'action'=>$action,
            ];
        }

        return $this->execution->execute(
            'content',
            $action,
            fn (): array => $action === 'publish_content'
                ? $this->content->publish($entityType, $entityId)
                : $this->content->delete($entityType, $entityId),
            $requestId,
            [
                'entity_type'=>$entityType,
                'entity_id'=>$entityId,
                'requested_by'=>$founderId,
            ]
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
