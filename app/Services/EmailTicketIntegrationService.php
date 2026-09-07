<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\NajmHoda\Runtime\NajmHodaDomainEventPolicyLinkService;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use App\Services\TicketTriageService;
use App\Services\TicketSlaService;
use App\Traits\LogsTicketActivity;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * سرویس یکپارچه‌سازی ایمیل با تیکت‌ها
 *
 * این سرویس امکان تبدیل ایمیل‌های دریافتی به تیکت و ارسال پاسخ تیکت‌ها به ایمیل را فراهم می‌کند.
 * تمام ایمیل‌های خروجی پشتیبانی با هویت سازمانی «تیم پشتیبانی EarthCoop» ارسال می‌شوند،
 * نه با هویت حساب کاربری مدیر یا پاسخ‌دهنده.
 */
class EmailTicketIntegrationService
{
    use LogsTicketActivity;

    protected TicketTriageService $triage;
    protected TicketSlaService $sla;
    protected SystemIdentityService $systemIdentities;

    public function __construct(
        TicketTriageService $triage,
        TicketSlaService $sla,
        SystemIdentityService $systemIdentities
    ) {
        $this->triage = $triage;
        $this->sla = $sla;
        $this->systemIdentities = $systemIdentities;
    }

    /**
     * تبدیل ایمیل دریافتی به تیکت یا کامنت
     */
    public function processIncomingEmail(array $emailData): ?Ticket
    {
        $context = [
            'scope' => 'support:email',
            'risk' => 'low',
            'has_message_id' => !empty($emailData['message_id']),
            'has_reply_headers' => !empty($emailData['in_reply_to']) || !empty($emailData['references']),
        ];
        $this->emitRuntime('najm_hoda.input.support.service.email_integration.process.requested', $context);

        try {
            $fromEmail = $emailData['from']['email'] ?? null;
            $fromName = $emailData['from']['name'] ?? null;
            $subject = $emailData['subject'] ?? 'بدون موضوع';
            $body = $this->extractEmailBody($emailData);
            $messageId = $emailData['message_id'] ?? null;
            $inReplyTo = $emailData['in_reply_to'] ?? null;
            $references = $emailData['references'] ?? null;

            $user = User::where('email', $fromEmail)->first();

            if ($inReplyTo || $references) {
                $ticket = $this->findTicketByMessageId($inReplyTo, $references);

                if ($ticket) {
                    TicketComment::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $user?->id,
                        'message' => "**پیام از ایمیل**\n\n" . $body,
                        'metadata' => [
                            'from_email' => $fromEmail,
                            'from_name' => $fromName,
                            'message_id' => $messageId,
                        ],
                    ]);

                    if ($ticket->status === 'closed') {
                        $ticket->update(['status' => 'open']);
                    }

                    $ticket->update(['last_activity_at' => now()]);

                    $this->emitRuntime('najm_hoda.input.support.service.email_integration.process.succeeded', array_merge($context, [
                        'ticket_id' => (int) $ticket->id,
                        'mode' => 'append_comment',
                    ]));
                    return $ticket;
                }
            }

            $ticket = $this->createTicketFromEmail($fromEmail, $fromName, $subject, $body, $user, $messageId);
            $this->emitRuntime('najm_hoda.input.support.service.email_integration.process.succeeded', array_merge($context, [
                'ticket_id' => (int) $ticket->id,
                'mode' => 'create_ticket',
            ]));

            return $ticket;

        } catch (\Exception $e) {
            Log::error('خطا در پردازش ایمیل دریافتی: ' . $e->getMessage(), [
                'email_data' => $emailData,
                'trace' => $e->getTraceAsString(),
            ]);
            $this->emitRuntime('najm_hoda.input.support.service.email_integration.process.failed', array_merge($context, [
                'error' => $e->getMessage(),
                'risk' => 'medium',
            ]));

            return null;
        }
    }

    protected function extractEmailBody(array $emailData): string
    {
        if (isset($emailData['text_plain'])) {
            return strip_tags($emailData['text_plain']);
        }

        if (isset($emailData['text_html'])) {
            return strip_tags($emailData['text_html']);
        }

        if (isset($emailData['body'])) {
            return strip_tags($emailData['body']);
        }

        return '';
    }

    protected function findTicketByMessageId(?string $inReplyTo, ?string $references): ?Ticket
    {
        $comment = TicketComment::whereJsonContains('metadata->message_id', $inReplyTo)
            ->orWhereJsonContains('metadata->message_id', $references)
            ->first();

        if ($comment) {
            return $comment->ticket;
        }

        return Ticket::whereJsonContains('metadata->message_id', $inReplyTo)
            ->orWhereJsonContains('metadata->message_id', $references)
            ->first();
    }

    protected function createTicketFromEmail(
        string $fromEmail,
        ?string $fromName,
        string $subject,
        string $body,
        ?User $user,
        ?string $messageId
    ): Ticket {
        $cleanSubject = preg_replace('/^(Re:|Fwd:|FW:)\s*/i', '', $subject);
        $triageResult = $this->triage->triage($cleanSubject, $body);

        do {
            $trackingCode = 'TK-' . strtoupper(Str::random(8));
        } while (Ticket::where('tracking_code', $trackingCode)->exists());

        $priority = $triageResult['priority'] ?? 'normal';
        $slaDeadline = $this->sla->calculateDeadline(
            new Ticket(['priority' => $priority, 'created_at' => now()])
        );

        $ticket = Ticket::create([
            'user_id' => $user?->id,
            'tracking_code' => $trackingCode,
            'subject' => $cleanSubject,
            'message' => $body,
            'status' => 'open',
            'priority' => $priority,
            'assignee_id' => $triageResult['assignee_id'] ?? null,
            'name' => $fromName ?? explode('@', $fromEmail)[0],
            'email' => $fromEmail,
            'category' => 'general',
            'sla_deadline' => $slaDeadline,
            'metadata' => [
                'source' => 'email',
                'message_id' => $messageId,
            ],
        ]);

        $this->logTicketCreated($ticket);

        return $ticket;
    }

    public function sendTicketReplyToEmail(Ticket $ticket, TicketComment $comment): bool
    {
        $context = [
            'scope' => 'support:email',
            'risk' => 'low',
            'ticket_id' => (int) $ticket->id,
            'comment_id' => (int) $comment->id,
        ];
        $this->emitRuntime('najm_hoda.input.support.service.email_integration.send_reply.requested', $context);

        try {
            if (!$ticket->email) {
                Log::warning('تیکت بدون ایمیل کاربر: ' . $ticket->id);
                $this->emitRuntime('najm_hoda.input.support.service.email_integration.send_reply.rejected', array_merge($context, [
                    'reason' => 'missing_ticket_email',
                    'risk' => 'medium',
                ]));
                return false;
            }

            $user = $ticket->user;
            $commenter = $comment->user;
            $sender = $this->systemIdentities->mailSender('support');
            $subject = $ticket->tracking_code . ' - ' . $ticket->subject;

            $body = view('emails.ticket-reply', [
                'ticket' => $ticket,
                'comment' => $comment,
                'user' => $user,
                'commenter' => $commenter,
            ])->render();

            $messageId = null;
            Mail::html($body, function ($message) use ($ticket, $subject, $sender, &$messageId) {
                $message->from($sender['address'], $sender['name'])
                    ->to($ticket->email, $ticket->name)
                    ->subject($subject)
                    ->replyTo($sender['reply_to'] ?: $sender['address'], $sender['name']);

                $host = parse_url(config('app.url'), PHP_URL_HOST) ?? 'earthcoop.org';
                $messageId = '<ticket-' . $ticket->id . '-comment-' . time() . '@' . $host . '>';
                $message->getHeaders()->addTextHeader('Message-ID', $messageId);
                $message->getHeaders()->addTextHeader('In-Reply-To', '<ticket-' . $ticket->id . '@' . $host . '>');
                $message->getHeaders()->addTextHeader('References', '<ticket-' . $ticket->id . '@' . $host . '>');
            });

            if ($messageId) {
                $comment->update([
                    'metadata' => array_merge($comment->metadata ?? [], [
                        'email_sent' => true,
                        'email_sent_at' => now()->toIso8601String(),
                        'message_id' => $messageId,
                        'sender_identity' => 'support',
                        'sender_address' => $sender['address'],
                        'sender_name' => $sender['name'],
                    ]),
                ]);
            }

            $this->emitRuntime('najm_hoda.input.support.service.email_integration.send_reply.succeeded', array_merge($context, [
                'sender_identity' => 'support',
            ]));
            return true;

        } catch (\Exception $e) {
            Log::error('خطا در ارسال پاسخ تیکت به ایمیل: ' . $e->getMessage(), [
                'ticket_id' => $ticket->id,
                'comment_id' => $comment->id,
                'trace' => $e->getTraceAsString(),
            ]);
            $this->emitRuntime('najm_hoda.input.support.service.email_integration.send_reply.failed', array_merge($context, [
                'error' => $e->getMessage(),
                'risk' => 'medium',
            ]));

            return false;
        }
    }

    public function sendTicketCreatedEmail(Ticket $ticket): bool
    {
        $context = [
            'scope' => 'support:email',
            'risk' => 'low',
            'ticket_id' => (int) $ticket->id,
        ];
        $this->emitRuntime('najm_hoda.input.support.service.email_integration.send_created.requested', $context);

        try {
            if (!$ticket->email) {
                $this->emitRuntime('najm_hoda.input.support.service.email_integration.send_created.rejected', array_merge($context, [
                    'reason' => 'missing_ticket_email',
                    'risk' => 'medium',
                ]));
                return false;
            }

            $sender = $this->systemIdentities->mailSender('support');
            $subject = 'تیکت جدید شما: ' . $ticket->tracking_code . ' - ' . $ticket->subject;
            $body = view('emails.ticket-created', ['ticket' => $ticket])->render();

            Mail::html($body, function ($message) use ($ticket, $subject, $sender) {
                $message->from($sender['address'], $sender['name'])
                    ->to($ticket->email, $ticket->name)
                    ->subject($subject)
                    ->replyTo($sender['reply_to'] ?: $sender['address'], $sender['name']);
            });

            $this->emitRuntime('najm_hoda.input.support.service.email_integration.send_created.succeeded', array_merge($context, [
                'sender_identity' => 'support',
            ]));
            return true;

        } catch (\Exception $e) {
            Log::error('خطا در ارسال ایمیل ایجاد تیکت: ' . $e->getMessage(), [
                'ticket_id' => $ticket->id,
            ]);
            $this->emitRuntime('najm_hoda.input.support.service.email_integration.send_created.failed', array_merge($context, [
                'error' => $e->getMessage(),
                'risk' => 'medium',
            ]));

            return false;
        }
    }

    protected function emitRuntime(string $event, array $payload): void
    {
        try {
            /** @var RuntimeEventBus $bus */
            $bus = app(RuntimeEventBus::class);
            $bus->emit($event, $payload);

            /** @var NajmHodaDomainEventPolicyLinkService $link */
            $link = app(NajmHodaDomainEventPolicyLinkService::class);
            $link->ingest($event, $payload);
        } catch (Throwable) {
            // no-op
        }
    }
}
