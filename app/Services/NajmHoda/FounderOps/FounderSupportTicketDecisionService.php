<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\Ticket;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use App\Services\Support\TicketManagementService;

class FounderSupportTicketDecisionService
{
    public function __construct(
        protected FounderActionRequestService $requests,
        protected FounderActionExecutionService $execution,
        protected NajmHodaAutonomyApprovalService $approvals,
        protected TicketManagementService $tickets
    ) {}

    /** @return array<string,mixed> */
    public function requestClose(Ticket $ticket, int $requestedBy, ?string $reasonCode = null): array
    {
        if ((string) $ticket->status === 'closed') {
            return ['success' => false, 'status' => 'skipped', 'reason' => 'ticket_already_closed'];
        }

        return $this->requests->prepare('support', 'close_ticket', [
            'entity_type' => 'ticket',
            'entity_id' => (int) $ticket->id,
            'requested_by' => $requestedBy,
            'reason_code' => $reasonCode ?: 'support-close-ticket-' . (int) $ticket->id,
            'source_event' => 'founder_ops_support_ticket',
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
            || (string) data_get($pending, 'plan_item.domain_action') !== 'close_ticket'
            || (string) data_get($pending, 'context.entity_type') !== 'ticket') {
            return ['success' => false, 'status' => 'invalid_request', 'reason' => 'approval_contract_mismatch'];
        }

        $ticketId = (int) data_get($pending, 'context.entity_id', 0);
        $ticket = $ticketId > 0 ? Ticket::query()->find($ticketId) : null;
        if (! $ticket) {
            return ['success' => false, 'status' => 'not_found', 'reason' => 'ticket_not_found'];
        }
        if ((string) $ticket->status === 'closed') {
            return ['success' => false, 'status' => 'skipped', 'reason' => 'ticket_already_closed'];
        }

        $decisionResult = $this->approvals->decide($requestId, $decision, $founderId, $reason);
        if (! (bool) ($decisionResult['success'] ?? false)) {
            return $decisionResult;
        }

        if ($decision === 'reject') {
            return ['success' => true, 'status' => 'rejected', 'ticket_id' => $ticketId];
        }

        return $this->execution->execute(
            'support',
            'close_ticket',
            fn (): array => $this->tickets->close($ticket),
            $requestId,
            [
                'entity_type' => 'ticket',
                'entity_id' => $ticketId,
                'requested_by' => $founderId,
                'reason_code' => (string) data_get($pending, 'context.reason_code', ''),
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
