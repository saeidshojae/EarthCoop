<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\EmailTemplate;
use App\Services\Email\EmailTemplateManagementService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;

class FounderEmailTemplateDecisionService
{
    public function __construct(
        protected FounderActionRequestService $requests,
        protected FounderActionExecutionService $execution,
        protected NajmHodaAutonomyApprovalService $approvals,
        protected EmailTemplateManagementService $templates,
    ) {}

    /** @param array<string,mixed> $changes */
    public function requestEdit(EmailTemplate $template, array $changes, int $requestedBy, ?string $reasonCode = null): array
    {
        return $this->requests->prepare('email', 'edit_template', [
            'entity_type' => 'email_template',
            'entity_id' => (int) $template->id,
            'requested_by' => $requestedBy,
            'reason_code' => $reasonCode ?: 'email-template-edit-' . (int) $template->id,
            'source_event' => 'founder_ops_email_template',
            'changes' => $changes,
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

        if ((string) data_get($pending, 'plan_item.domain') !== 'email'
            || (string) data_get($pending, 'plan_item.domain_action') !== 'edit_template'
            || (string) data_get($pending, 'context.entity_type') !== 'email_template') {
            return ['success' => false, 'status' => 'invalid_request', 'reason' => 'approval_contract_mismatch'];
        }

        $templateId = (int) data_get($pending, 'context.entity_id', 0);
        $template = $templateId > 0 ? EmailTemplate::query()->find($templateId) : null;
        if (! $template) {
            return ['success' => false, 'status' => 'not_found', 'reason' => 'email_template_not_found'];
        }

        $changes = data_get($pending, 'context.changes');
        if (! is_array($changes) || $changes === []) {
            return ['success' => false, 'status' => 'invalid_request', 'reason' => 'template_changes_missing'];
        }

        $decided = $this->approvals->decide($requestId, $decision, $founderId, $reason);
        if (! (bool) ($decided['success'] ?? false)) {
            return $decided;
        }
        if ($decision === 'reject') {
            return ['success' => true, 'status' => 'rejected', 'template_id' => $templateId];
        }

        return $this->execution->execute(
            'email',
            'edit_template',
            function () use ($template, $changes): array {
                $updated = $this->templates->update($template, $changes);
                return [
                    'template_id' => (int) $updated->id,
                    'template_name' => (string) $updated->name,
                    'is_active' => (bool) $updated->is_active,
                ];
            },
            $requestId,
            ['entity_type' => 'email_template', 'entity_id' => $templateId, 'requested_by' => $founderId]
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
