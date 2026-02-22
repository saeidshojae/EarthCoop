<?php

namespace App\Observers\NajmHoda;

use App\Models\TicketComment;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;

class TicketCommentObserver
{
    public function created(TicketComment $comment): void
    {
        $this->bus()->emit('najm_hoda.input.support.ticket.comment_created', [
            'ticket_id' => (int) $comment->ticket_id,
            'comment_id' => (int) $comment->id,
            'user_id' => $comment->user_id !== null ? (int) $comment->user_id : null,
            'scope' => 'support',
            'risk' => 'low',
        ]);
    }

    protected function bus(): RuntimeEventBus
    {
        /** @var RuntimeEventBus $bus */
        $bus = app(RuntimeEventBus::class);
        return $bus;
    }
}

