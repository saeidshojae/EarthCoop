<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\ContactMessage;
use App\Models\TicketComment;
use App\Services\Communication\CommunicationDispatcher;
use App\Services\NajmHoda\Runtime\NajmHodaDomainEventPolicyLinkService;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use App\Traits\LogsTicketActivity;
use Illuminate\Support\Facades\Log;
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

    public function __construct(
        protected CommunicationDispatcher $communications,
    ) {
    }

    /**
     * Reply معتبر به تیکت موجود را به همان thread متصل می‌کند؛
     * ایمیل مستقل جدید را به Contact Inbox می‌فرستد.
     */
    public function processIncomingEmail(array $emailData): Ticket|ContactMessage|null
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

            if ($inReplyTo || $references) {
                $ticket = $this->findTicketByMessageId($inReplyTo, $references);

                if ($ticket && strcasecmp(trim((string) $ticket->email), trim((string) $fromEmail)) === 0) {
                    TicketComment::create([
                        'ticket_id' => $ticket->id,
                        // Email possession alone is not authenticated EarthCoop identity.
                        'user_id' => null,
                        'message' => "**پیام از ایمیل**\n\n" . $body,
                        'metadata' => [
                            'from_email' => $fromEmail,
                            'from_name' => $fromName,
                            'message_id' => $messageId,
                            'source' => 'email_webhook',
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

            $contact = $this->createContactMessageFromEmail(
                $fromEmail,
                $fromName,
                $subject,
                $body,
            );

            $this->emitRuntime('najm_hoda.input.support.service.email_integration.process.succeeded', array_merge($context, [
                'contact_message_id' => (int) $contact->id,
                'mode' => 'create_contact_message',
            ]));

            return $contact;

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
        $messageIds = collect([$inReplyTo, $references])
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->flatMap(function (string $header): array {
                preg_match_all('/<[^>]+>/', $header, $matches);

                return $matches[0] !== [] ? $matches[0] : [trim($header)];
            })
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        if ($messageIds->isEmpty()) {
            return null;
        }

        $comment = TicketComment::query()
            ->where(function ($query) use ($messageIds): void {
                foreach ($messageIds as $messageId) {
                    $query->orWhereJsonContains('metadata->message_id', $messageId);
                }
            })
            ->first();

        if ($comment) {
            return $comment->ticket;
        }

        return Ticket::query()
            ->where(function ($query) use ($messageIds): void {
                foreach ($messageIds as $messageId) {
                    $query->orWhereJsonContains('metadata->message_id', $messageId);
                }
            })
            ->first();
    }

    protected function createContactMessageFromEmail(
        ?string $fromEmail,
        ?string $fromName,
        string $subject,
        string $body,
    ): ContactMessage {
        $cleanSubject = trim((string) preg_replace('/^(Re:|Fwd:|FW:)\s*/i', '', $subject));
        if ($cleanSubject === '') {
            $cleanSubject = 'پیام دریافتی از ایمیل';
        }

        return ContactMessage::query()->create([
            // Never infer authenticated membership from a claimed From address.
            'user_id' => null,
            'name' => trim((string) $fromName) ?: (trim((string) $fromEmail) ?: 'فرستنده ایمیل'),
            'email' => trim((string) $fromEmail) ?: null,
            'phone' => null,
            'subject' => $cleanSubject,
            'message' => $body !== '' ? $body : 'پیام بدون متن',
            'status' => 'new',
            'source' => 'email_webhook',
        ]);
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
            $subject = $ticket->tracking_code . ' - ' . $ticket->subject;
            $body = view('emails.ticket-reply', [
                'ticket' => $ticket,
                'comment' => $comment,
                'user' => $user,
                'commenter' => $commenter,
            ])->render();

            $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'earthcoop.org';
            $messageId = '<ticket-' . $ticket->id . '-comment-' . $comment->id . '@' . $host . '>';
            $threadId = '<ticket-' . $ticket->id . '@' . $host . '>';

            $communication = $this->communications->dispatchExternal(
                'support.ticket_reply',
                ['type' => 'support.ticket_reply', 'id' => (string) $comment->id],
                [['email' => $ticket->email, 'locale' => 'fa']],
                [
                    'subject' => $subject,
                    'rendered_html' => $body,
                    '_delivery_headers' => [
                        'Message-ID' => $messageId,
                        'In-Reply-To' => $threadId,
                        'References' => $threadId,
                    ],
                ],
                [
                    'priority' => 1,
                    'deduplication_key' => 'support.ticket_reply:'.$comment->id,
                ],
            );

            $communication->loadMissing('templateVersion.senderIdentity');
            $sender = $communication->templateVersion?->senderIdentity;
            if (! $sender) {
                throw new \RuntimeException('support_sender_identity_not_available');
            }

            // Preserve legacy metadata fields for inbound threading and old admin views.
            // Canonical recipient/attempt records remain the source of delivery truth.
            $comment->update([
                'metadata' => array_merge($comment->metadata ?? [], [
                    'email_sent' => true,
                    'email_sent_at' => now()->toIso8601String(),
                    'email_queued_at' => now()->toIso8601String(),
                    'message_id' => $messageId,
                    'sender_identity' => $sender->key,
                    'sender_address' => $sender->email,
                    'sender_name' => $sender->display_name,
                    'communication_id' => $communication->id,
                ]),
            ]);

            $this->emitRuntime('najm_hoda.input.support.service.email_integration.send_reply.succeeded', array_merge($context, [
                'sender_identity' => $sender->key,
                'communication_id' => (int) $communication->id,
            ]));
            return true;

        } catch (\Exception $e) {
            Log::error('خطا در صف‌بندی پاسخ تیکت برای ایمیل: ' . $e->getMessage(), [
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

            $subject = 'تیکت جدید شما: ' . $ticket->tracking_code . ' - ' . $ticket->subject;
            $body = view('emails.ticket-created', ['ticket' => $ticket])->render();

            $communication = $this->communications->dispatchExternal(
                'support.ticket_created',
                ['type' => 'support.ticket_created', 'id' => (string) $ticket->id],
                [['email' => $ticket->email, 'locale' => 'fa']],
                [
                    'subject' => $subject,
                    'rendered_html' => $body,
                ],
                [
                    'priority' => 1,
                    'deduplication_key' => 'support.ticket_created:'.$ticket->id,
                ],
            );

            $communication->loadMissing('templateVersion.senderIdentity');
            $sender = $communication->templateVersion?->senderIdentity;

            $this->emitRuntime('najm_hoda.input.support.service.email_integration.send_created.succeeded', array_merge($context, [
                'sender_identity' => $sender?->key ?? 'support',
                'communication_id' => (int) $communication->id,
            ]));
            return true;

        } catch (\Exception $e) {
            Log::error('خطا در صف‌بندی ایمیل ایجاد تیکت: ' . $e->getMessage(), [
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
