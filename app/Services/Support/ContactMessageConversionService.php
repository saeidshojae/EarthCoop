<?php

namespace App\Services\Support;

use App\Models\ContactMessage;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketSlaService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContactMessageConversionService
{
    public function __construct(
        private readonly TicketSlaService $sla,
    ) {
    }

    public function convert(ContactMessage $message, ?int $actorId = null): Ticket
    {
        if ($message->status === 'spam') {
            throw new DomainException('contact_message_conversion_not_allowed');
        }

        if ($message->converted_ticket_id) {
            $existing = Ticket::query()->find($message->converted_ticket_id);
            if ($existing) {
                return $existing;
            }
        }

        $user = $message->user_id
            ? User::query()->find($message->user_id)
            : null;

        if (! $user) {
            throw new DomainException('contact_message_requires_authenticated_user');
        }

        return DB::transaction(function () use ($message, $user, $actorId): Ticket {
            $priority = 'normal';
            $slaDeadline = $this->sla->calculateDeadline(new Ticket(['priority' => $priority]));

            do {
                $trackingCode = 'TK-'.Str::upper(Str::random(8));
            } while (Ticket::query()->where('tracking_code', $trackingCode)->exists());

            $ticket = Ticket::query()->create([
                'user_id' => $user->id,
                'tracking_code' => $trackingCode,
                'subject' => $message->subject,
                'message' => $message->message,
                'status' => 'open',
                'priority' => $priority,
                'category' => 'general',
                'sla_deadline' => $slaDeadline,
                'name' => $message->name ?: $user->fullName(),
                'email' => $user->email,
                'phone' => $message->phone ?: ($user->phone ?? null),
            ]);

            $message->update([
                'status' => 'converted',
                'converted_ticket_id' => $ticket->id,
                'handled_by' => $actorId,
                'handled_at' => now(),
            ]);

            return $ticket;
        });
    }
}
