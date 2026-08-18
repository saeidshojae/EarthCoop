<?php

namespace App\Services\NajmHoda\Runtime;

use App\Models\Ticket;
use App\Models\User;

/**
 * Authorizes a capability against the concrete resource it targets.
 *
 * Delegation answers "may this actor use this capability?". This service
 * answers the separate question "may this actor use it on this resource?".
 */
class NajmHodaResourceAuthorizationService
{
    /**
     * @param array<string, mixed> $input
     * @return array{allowed: bool, reason: string}
     */
    public function authorize(?int $actorId, string $action, array $input = []): array
    {
        return match ($action) {
            'set_ticket_needs_review' => $this->authorizeTicketMutation($actorId, $input),
            default => ['allowed' => true, 'reason' => 'no_resource_rule_required'],
        };
    }

    /**
     * @param array<string, mixed> $input
     * @return array{allowed: bool, reason: string}
     */
    protected function authorizeTicketMutation(?int $actorId, array $input): array
    {
        if ($actorId === null || $actorId <= 0) {
            return ['allowed' => false, 'reason' => 'actor_required'];
        }

        $ticketId = (int) ($input['ticket_id'] ?? 0);
        if ($ticketId <= 0) {
            return ['allowed' => false, 'reason' => 'invalid_ticket_id'];
        }

        $user = User::query()->find($actorId);
        if ($user === null) {
            return ['allowed' => false, 'reason' => 'actor_not_found'];
        }

        $ticket = Ticket::query()->find($ticketId);
        if ($ticket === null) {
            // Do not disclose whether the object exists to an unauthorized caller.
            return ['allowed' => false, 'reason' => 'resource_not_accessible'];
        }

        if ((bool) ($user->is_admin ?? false) || $user->hasRole('super-admin')) {
            return ['allowed' => true, 'reason' => 'admin_access'];
        }

        if ((int) ($ticket->user_id ?? 0) === $actorId) {
            return ['allowed' => true, 'reason' => 'ticket_owner'];
        }

        if ((int) ($ticket->assignee_id ?? 0) === $actorId) {
            return ['allowed' => true, 'reason' => 'ticket_assignee'];
        }

        $userEmail = mb_strtolower(trim((string) ($user->email ?? '')));
        $ticketEmail = mb_strtolower(trim((string) ($ticket->email ?? '')));
        if ($userEmail !== '' && $ticketEmail !== '' && $userEmail === $ticketEmail) {
            return ['allowed' => true, 'reason' => 'ticket_email_owner'];
        }

        return ['allowed' => false, 'reason' => 'resource_not_accessible'];
    }
}
