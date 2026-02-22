<?php

namespace App\Observers\NajmHoda;

use App\Models\Ticket;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;

class TicketObserver
{
    public function created(Ticket $ticket): void
    {
        $this->bus()->emit('najm_hoda.input.support.ticket.created', [
            'ticket_id' => (int) $ticket->id,
            'user_id' => $ticket->user_id !== null ? (int) $ticket->user_id : null,
            'assignee_id' => $ticket->assignee_id !== null ? (int) $ticket->assignee_id : null,
            'tracking_code' => (string) ($ticket->tracking_code ?? ''),
            'status' => (string) ($ticket->status ?? 'open'),
            'priority' => (string) ($ticket->priority ?? 'normal'),
            'scope' => 'support',
            'risk' => 'low',
        ]);
    }

    public function updated(Ticket $ticket): void
    {
        $changes = $ticket->getChanges();

        if (array_key_exists('status', $changes)) {
            $this->bus()->emit('najm_hoda.input.support.ticket.status_changed', [
                'ticket_id' => (int) $ticket->id,
                'user_id' => $ticket->user_id !== null ? (int) $ticket->user_id : null,
                'from_status' => (string) $ticket->getOriginal('status'),
                'to_status' => (string) $ticket->status,
                'scope' => 'support',
                'risk' => 'low',
            ]);
        }

        if (array_key_exists('assignee_id', $changes)) {
            $this->bus()->emit('najm_hoda.input.support.ticket.assigned', [
                'ticket_id' => (int) $ticket->id,
                'user_id' => $ticket->user_id !== null ? (int) $ticket->user_id : null,
                'from_assignee_id' => $ticket->getOriginal('assignee_id') !== null ? (int) $ticket->getOriginal('assignee_id') : null,
                'to_assignee_id' => $ticket->assignee_id !== null ? (int) $ticket->assignee_id : null,
                'scope' => 'support',
                'risk' => 'low',
            ]);
        }
    }

    protected function bus(): RuntimeEventBus
    {
        /** @var RuntimeEventBus $bus */
        $bus = app(RuntimeEventBus::class);
        return $bus;
    }
}

