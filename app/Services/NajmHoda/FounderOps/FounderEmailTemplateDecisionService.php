<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\CommunicationTemplate;
use App\Models\EmailTemplate;
use App\Models\FounderEmailTemplateDraft;
use App\Services\Communication\CommunicationTemplateService;
use App\Services\Email\EmailTemplateManagementService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use RuntimeException;

class FounderEmailTemplateDecisionService
{
    public function __construct(
        protected FounderActionRequestService $requests,
        protected FounderActionExecutionService $execution,
        protected NajmHodaAutonomyApprovalService $approvals,
        protected EmailTemplateManagementService $legacyTemplates,
        protected CommunicationTemplateService $communicationTemplates,
    ) {}

    /** @param array<string,mixed> $changes */
    public function requestEdit(
        EmailTemplate|CommunicationTemplate $template,
        array $changes,
        int $requestedBy,
        ?string $reasonCode = null,
    ): array {
        $isCanonical = $template instanceof CommunicationTemplate;
        $reasonCode = $reasonCode ?: ($isCanonical
            ? 'communication-template-edit-'.(int) $template->id
            : 'email-template-edit-'.(int) $template->id);

        $query = FounderEmailTemplateDraft::query()
            ->where('reason_code', $reasonCode)
            ->where('status', 'draft');

        if ($isCanonical) {
            $query->where('communication_template_id', $template->id);
        } else {
            $query->where('template_id', $template->id);
        }

        $existing = $query->first();

        $draft = $existing ?: FounderEmailTemplateDraft::query()->create([
            'template_id' => $isCanonical ? null : (int) $template->id,
            'communication_template_id' => $isCanonical ? (int) $template->id : null,
            'changes' => $changes,
            'status' => 'draft',
            'reason_code' => $reasonCode,
            'created_by' => $requestedBy,
        ]);

        return $this->requests->prepare('email', 'edit_template', [
            'entity_type' => 'founder_email_template_draft',
            'entity_id' => (int) $draft->id,
            'requested_by' => $requestedBy,
            'reason_code' => $reasonCode,
            'source_event' => 'founder_ops_email_template',
            'review_draft_id' => (int) $draft->id,
            'canonical_template_id' => $isCanonical ? (int) $template->id : null,
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
            || (string) data_get($pending, 'context.entity_type') !== 'founder_email_template_draft') {
            return ['success' => false, 'status' => 'invalid_request', 'reason' => 'approval_contract_mismatch'];
        }

        $draftId = (int) data_get($pending, 'context.entity_id', 0);
        $draft = $draftId > 0
            ? FounderEmailTemplateDraft::query()
                ->with(['template', 'communicationTemplate'])
                ->whereKey($draftId)
                ->where('status', 'draft')
                ->first()
            : null;
        if (! $draft || (! $draft->communicationTemplate && ! $draft->template)) {
            return ['success' => false, 'status' => 'not_found', 'reason' => 'email_template_edit_draft_not_found'];
        }

        $changes = (array) $draft->changes;
        if ($changes === []) {
            return ['success' => false, 'status' => 'invalid_request', 'reason' => 'template_changes_missing'];
        }

        $decided = $this->approvals->decide($requestId, $decision, $founderId, $reason);
        if (! (bool) ($decided['success'] ?? false)) {
            return $decided;
        }
        if ($decision === 'reject') {
            $draft->update(['status' => 'rejected', 'rejected_at' => now()]);
            return ['success' => true, 'status' => 'rejected', 'draft_id' => $draft->id];
        }

        return $this->execution->execute(
            'email',
            'edit_template',
            function () use ($draft, $changes, $founderId): array {
                if ($draft->communicationTemplate) {
                    $result = $this->applyCanonicalRevision($draft->communicationTemplate, $changes, $draft, $founderId);
                } else {
                    // Compatibility path for historical drafts/callers. New work should
                    // target CommunicationTemplate directly so canonical state is primary.
                    $updated = $this->legacyTemplates->update($draft->template, $changes);
                    $result = [
                        'draft_id' => (int) $draft->id,
                        'template_id' => (int) $updated->id,
                        'template_name' => (string) $updated->name,
                        'is_active' => (bool) $updated->is_active,
                        'source' => 'legacy_compatibility',
                    ];
                }

                $draft->update([
                    'status' => 'applied',
                    'approved_by' => $founderId,
                    'approved_at' => now(),
                    'applied_at' => now(),
                ]);

                return $result;
            },
            $requestId,
            [
                'entity_type' => 'founder_email_template_draft',
                'entity_id' => $draft->id,
                'requested_by' => $founderId,
            ]
        );
    }

    /** @param array<string,mixed> $changes @return array<string,mixed> */
    private function applyCanonicalRevision(
        CommunicationTemplate $template,
        array $changes,
        FounderEmailTemplateDraft $draft,
        int $founderId,
    ): array {
        $locale = trim((string) ($changes['locale'] ?? 'fa')) ?: 'fa';
        $latest = $template->versions()
            ->where('locale', $locale)
            ->whereNotNull('published_at')
            ->orderByDesc('version')
            ->first();

        if (! $latest) {
            throw new RuntimeException('canonical_email_template_has_no_published_version');
        }

        $version = $this->communicationTemplates->publish(
            $template,
            $locale,
            (string) ($changes['subject'] ?? $latest->subject),
            (string) ($changes['body'] ?? $latest->body),
            (array) ($changes['variables_schema'] ?? $latest->variables_schema ?? []),
            $latest->senderIdentity,
            $draft->created_by ? (int) $draft->created_by : $founderId,
            $founderId,
        );

        $metadataChanges = array_intersect_key($changes, array_flip([
            'name',
            'category',
            'is_active',
        ]));
        if ($metadataChanges !== []) {
            $template->fill($metadataChanges)->save();
        }

        return [
            'draft_id' => (int) $draft->id,
            'communication_template_id' => (int) $template->id,
            'template_key' => (string) $template->key,
            'template_version_id' => (int) $version->id,
            'version' => (int) $version->version,
            'source' => 'canonical',
        ];
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
