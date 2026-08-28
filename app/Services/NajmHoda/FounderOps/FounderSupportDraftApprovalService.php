<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\SupportReplyDraft;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use App\Services\Support\TicketManagementService;
use App\Services\SystemIdentityService;

class FounderSupportDraftApprovalService
{
    public function __construct(
        protected FounderActionRequestService $requests,
        protected FounderActionExecutionService $execution,
        protected NajmHodaAutonomyApprovalService $approvals,
        protected TicketManagementService $tickets,
        protected SystemIdentityService $systemIdentities
    ) {}

    /** @return array<string,mixed> */
    public function requestSend(SupportReplyDraft $draft, int $requestedBy): array
    {
        if ((string) $draft->status !== 'draft') {
            return ['success' => false, 'status' => 'invalid_draft_state'];
        }

        return $this->requests->prepare('support', 'send_reply', [
            'entity_type' => 'support_reply_draft',
            'entity_id' => (int) $draft->id,
            'requested_by' => $requestedBy,
            'reason_code' => 'support-draft-' . (int) $draft->id,
            'source_event' => 'founder_ops_support_draft',
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

        if ((string) data_get($pending, 'plan_item.domain') !== 'support'
            || (string) data_get($pending, 'plan_item.domain_action') !== 'send_reply'
            || (string) data_get($pending, 'context.entity_type') !== 'support_reply_draft') {
            return ['success' => false, 'status' => 'invalid_request', 'reason' => 'approval_contract_mismatch'];
        }

        $draftId = (int) data_get($pending, 'context.entity_id', 0);
        $draft = $draftId > 0 ? SupportReplyDraft::query()->with('ticket')->find($draftId) : null;
        if (! $draft || ! $draft->ticket) {
            return ['success' => false, 'status' => 'not_found', 'reason' => 'draft_or_ticket_not_found'];
        }

        $decisionResult = $this->approvals->decide($requestId, $decision, $founderId, $reason);
        if (! (bool) ($decisionResult['success'] ?? false)) return $decisionResult;

        if ($decision === 'reject') {
            $draft->update(['status' => 'rejected', 'rejected_at' => now()]);
            return ['success' => true, 'status' => 'rejected', 'draft_id' => $draftId];
        }

        $supportIdentity = $this->systemIdentities->support();
        $draft->update(['status' => 'approved', 'approved_at' => now()]);

        return $this->execution->execute(
            'support',
            'send_reply',
            function () use ($draft, $supportIdentity) {
                $reply = $this->tickets->reply($draft->ticket, (int) $supportIdentity->id, (string) $draft->body, true);
                $draft->update(['status' => 'sent', 'sent_at' => now()]);

                return [
                    'ticket_id' => (int) $draft->ticket_id,
                    'draft_id' => (int) $draft->id,
                    'comment_id' => (int) ($reply['comment_id'] ?? 0),
                    'sender_identity_id' => (int) $supportIdentity->id,
                ];
            },
            $requestId,
            ['entity_type' => 'support_reply_draft', 'entity_id' => $draftId, 'requested_by' => $founderId]
        );
    }

    /** @return array<int,int> */
    protected function founderIds(): array
    {
        return array_values(array_filter(array_map('intval', (array) config('najm-hoda-founder-action-policy.founder_approval.user_ids', []))));
    }
}
