<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\SupportReplyDraft;
use App\Models\Ticket;
use App\Models\User;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use App\Services\Support\TicketManagementService;
use App\Services\Support\TicketReplyDraftService;
use App\Services\SystemIdentityService;

class FounderUserSupportResponseService
{
    public function __construct(
        protected FounderActionRequestService $requests,
        protected FounderActionExecutionService $execution,
        protected NajmHodaAutonomyApprovalService $approvals,
        protected TicketReplyDraftService $drafts,
        protected TicketManagementService $tickets,
        protected SystemIdentityService $systemIdentities
    ) {}

    /** @return array<string,mixed> */
    public function draft(User $user, Ticket $ticket, ?string $reasonCode = null): array
    {
        if ((int) $ticket->user_id !== (int) $user->id) {
            return ['success' => false, 'status' => 'blocked', 'reason' => 'ticket_user_mismatch'];
        }

        if (! in_array((string) $ticket->status, ['open', 'in-progress'], true)) {
            return ['success' => false, 'status' => 'skipped', 'reason' => 'ticket_not_active'];
        }

        return $this->drafts->generate(
            $ticket,
            $reasonCode ?: 'users-support-draft-' . (int) $ticket->id
        );
    }

    /** @return array<string,mixed> */
    public function requestSend(SupportReplyDraft $draft, int $requestedBy): array
    {
        $draft->loadMissing('ticket');
        if ((string) $draft->status !== 'draft') {
            return ['success' => false, 'status' => 'invalid_draft_state'];
        }
        if (! $draft->ticket || ! $draft->ticket->user_id) {
            return ['success' => false, 'status' => 'invalid_user_support_draft'];
        }

        return $this->requests->prepare('users', 'send_support_response', [
            'entity_type' => 'support_reply_draft',
            'entity_id' => (int) $draft->id,
            'requested_by' => $requestedBy,
            'reason_code' => 'users-support-draft-' . (int) $draft->id,
            'source_event' => 'founder_ops_user_support_draft',
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

        if ((string) data_get($pending, 'plan_item.domain') !== 'users'
            || (string) data_get($pending, 'plan_item.domain_action') !== 'send_support_response'
            || (string) data_get($pending, 'context.entity_type') !== 'support_reply_draft') {
            return ['success' => false, 'status' => 'invalid_request', 'reason' => 'approval_contract_mismatch'];
        }

        $draftId = (int) data_get($pending, 'context.entity_id', 0);
        $draft = $draftId > 0 ? SupportReplyDraft::query()->with('ticket')->find($draftId) : null;
        if (! $draft || ! $draft->ticket || ! $draft->ticket->user_id) {
            return ['success' => false, 'status' => 'not_found', 'reason' => 'user_support_draft_not_found'];
        }
        if ((string) $draft->status !== 'draft') {
            return ['success' => false, 'status' => 'invalid_draft_state'];
        }

        $decisionResult = $this->approvals->decide($requestId, $decision, $founderId, $reason);
        if (! (bool) ($decisionResult['success'] ?? false)) {
            return $decisionResult;
        }

        if ($decision === 'reject') {
            $draft->update(['status' => 'rejected', 'rejected_at' => now()]);
            return ['success' => true, 'status' => 'rejected', 'draft_id' => $draftId];
        }

        $supportIdentity = $this->systemIdentities->support();
        $draft->update(['status' => 'approved', 'approved_at' => now()]);

        return $this->execution->execute(
            'users',
            'send_support_response',
            function () use ($draft, $supportIdentity): array {
                $reply = $this->tickets->reply($draft->ticket, (int) $supportIdentity->id, (string) $draft->body, true);
                $draft->update(['status' => 'sent', 'sent_at' => now()]);
                return [
                    'user_id' => (int) $draft->ticket->user_id,
                    'ticket_id' => (int) ($reply['ticket_id'] ?? $draft->ticket_id),
                    'draft_id' => (int) $draft->id,
                    'comment_id' => (int) ($reply['comment_id'] ?? 0),
                    'ticket_status' => (string) ($reply['status'] ?? $draft->ticket->fresh()->status),
                    'sender_identity_id' => (int) $supportIdentity->id,
                ];
            },
            $requestId,
            [
                'entity_type' => 'support_reply_draft',
                'entity_id' => $draftId,
                'requested_by' => $founderId,
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
