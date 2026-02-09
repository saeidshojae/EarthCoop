<?php

namespace App\Traits;

use App\Models\Ticket;
use App\Models\TicketActivity;

trait LogsTicketActivity
{
    /**
     * Log ticket activity
     */
    protected function logActivity(
        Ticket $ticket,
        string $type,
        ?string $field = null,
        $oldValue = null,
        $newValue = null,
        ?string $description = null
    ): void {
        TicketActivity::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'type' => $type,
            'field' => $field,
            'old_value' => $oldValue ? (is_string($oldValue) ? $oldValue : json_encode($oldValue)) : null,
            'new_value' => $newValue ? (is_string($newValue) ? $newValue : json_encode($newValue)) : null,
            'description' => $description,
        ]);
    }

    /**
     * Log status change
     */
    protected function logStatusChange(Ticket $ticket, string $oldStatus, string $newStatus): void
    {
        $this->logActivity($ticket, 'status_changed', 'status', $oldStatus, $newStatus);
    }

    /**
     * Log priority change
     */
    protected function logPriorityChange(Ticket $ticket, ?string $oldPriority, ?string $newPriority): void
    {
        $this->logActivity($ticket, 'priority_changed', 'priority', $oldPriority, $newPriority);
    }

    /**
     * Log assignee change
     */
    protected function logAssigneeChange(Ticket $ticket, ?int $oldAssigneeId, ?int $newAssigneeId): void
    {
        $this->logActivity($ticket, 'assignee_changed', 'assignee_id', $oldAssigneeId, $newAssigneeId);
    }

    /**
     * Log comment added
     */
    protected function logCommentAdded(Ticket $ticket): void
    {
        $this->logActivity($ticket, 'comment_added');
    }

    /**
     * Log ticket created
     */
    protected function logTicketCreated(Ticket $ticket): void
    {
        $this->logActivity($ticket, 'ticket_created', null, null, null, 'تیکت ایجاد شد');
    }
}


