<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Notifications\TicketCreatedNotification;
use App\Services\TicketTriageService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class NajmHodaIntegrationService
{
    protected TicketTriageService $triage;

    public function __construct(TicketTriageService $triage)
    {
        $this->triage = $triage;
    }

    public function handleEscalation(array $payload): Ticket
    {
        $conversationId = $payload['conversation_id'] ?? null;
        $transcript = $payload['transcript'] ?? '';
        $userEmail = $payload['user_email'] ?? null;
        $userId = $payload['user_id'] ?? null;
        $reason = $payload['reason'] ?? null;

        if ($conversationId) {
            $existing = Ticket::where('najm_conversation_id', $conversationId)->first();
            if ($existing) {
                if ($transcript) {
                    TicketComment::create([
                        'ticket_id' => $existing->id,
                        'user_id' => $userId,
                        'message' => "[Najm Hoda escalation] " . $transcript,
                    ]);
                }
                return $existing;
            }
        }

        $subject = $reason ?: Str::limit($transcript, 80);

        $triageResult = $this->triage->triage($subject, $transcript);

        $ticket = Ticket::create([
            'user_id' => $userId,
            'name' => $userEmail ? explode('@', $userEmail)[0] : 'NajmHodaUser',
            'email' => $userEmail,
            'subject' => $subject,
            'message' => $transcript,
            'status' => 'open',
            'priority' => $triageResult['priority'] ?? 'normal',
            'assignee_id' => $triageResult['assignee_id'] ?? null,
            'tracking_code' => strtoupper(Str::random(8)),
            'najm_conversation_id' => $conversationId,
        ]);

        if ($transcript) {
            TicketComment::create([
                'ticket_id' => $ticket->id,
                'user_id' => $userId,
                'message' => "[Najm Hoda escalation] " . $transcript,
            ]);
        }

        // Notify support email and assigned operator (queued)
        try {
            // Notify support inbox if configured
            $supportEmail = env('SUPPORT_EMAIL');
            if ($supportEmail) {
                Notification::route('mail', $supportEmail)->notify(new TicketCreatedNotification($ticket));
            }

            // Notify assignee user if exists
            if (! empty($ticket->assignee_id)) {
                $user = User::find($ticket->assignee_id);
                if ($user) {
                    $user->notify(new TicketCreatedNotification($ticket));
                }
            }
        } catch (\Throwable $e) {
            // don't break ticket creation for notification errors
            // log silently
            // Log::error('NajmHoda notification failed: '.$e->getMessage());
        }

        return $ticket;
    }
}
