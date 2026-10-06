<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\Support\TicketManagementService;
use App\Services\Communication\CommunicationDispatcher;
use Illuminate\Support\Str;

class NajmHodaIntegrationService
{
    public function __construct(
        protected TicketTriageService $triage,
        protected TicketManagementService $tickets,
        protected CommunicationDispatcher $communications,
    ) {}

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
                        'message' => '[Najm Hoda escalation] ' . $transcript,
                    ]);
                }
                return $existing;
            }
        }

        $subject = $reason ?: Str::limit($transcript, 80);
        $legacyTriage = $this->triage->triage($subject, $transcript);

        $ticket = Ticket::create([
            'user_id' => $userId,
            'name' => $userEmail ? explode('@', $userEmail)[0] : 'NajmHodaUser',
            'email' => $userEmail,
            'subject' => $subject,
            'message' => $transcript,
            'status' => 'open',
            'priority' => $legacyTriage['priority'] ?? 'normal',
            'assignee_id' => $legacyTriage['assignee_id'] ?? null,
            'tracking_code' => strtoupper(Str::random(8)),
            'najm_conversation_id' => $conversationId,
        ]);

        // Canonical support management owns deterministic category/priority rules.
        $this->tickets->classify($ticket);
        $this->tickets->assignPriority($ticket);
        $ticket->refresh();

        if ($transcript) {
            TicketComment::create([
                'ticket_id' => $ticket->id,
                'user_id' => $userId,
                'message' => '[Najm Hoda escalation] ' . $transcript,
            ]);
        }

        try {
            $subject = 'تیکت پشتیبانی جدید از نجم هدا - '.$ticket->tracking_code;
            $body = '<p>یک گفتگوی نجم هدا به تیکت پشتیبانی تبدیل شد.</p>'
                .'<p><strong>کد پیگیری:</strong> '.e($ticket->tracking_code).'</p>'
                .'<p><strong>موضوع:</strong> '.e($ticket->subject).'</p>';

            $supportEmail = trim((string) env('SUPPORT_EMAIL'));
            if ($supportEmail !== '') {
                $this->communications->dispatchExternal(
                    'support.ticket_internal_alert',
                    ['type' => 'najm_hoda.ticket_escalation', 'id' => (string) $ticket->id],
                    [['email' => $supportEmail, 'locale' => 'fa']],
                    ['subject' => $subject, 'rendered_html' => $body],
                    [
                        'priority' => 1,
                        'deduplication_key' => 'support.ticket_internal_alert:'.$ticket->id.':support',
                    ],
                );
            }

            if (! empty($ticket->assignee_id)) {
                $assignee = User::find($ticket->assignee_id);
                if ($assignee) {
                    $this->communications->dispatch(
                        'support.ticket_internal_alert',
                        ['type' => 'najm_hoda.ticket_escalation', 'id' => (string) $ticket->id],
                        [$assignee],
                        ['subject' => $subject, 'rendered_html' => $body],
                        [
                            'priority' => 1,
                            'deduplication_key' => 'support.ticket_internal_alert:'.$ticket->id.':assignee:'.$assignee->id,
                        ],
                    );
                }
            }
        } catch (\Throwable $e) {
            // Ticket creation must not fail because communication infrastructure is unavailable.
        }

        return $ticket;
    }
}
