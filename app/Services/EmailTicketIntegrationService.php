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
 * این سرویس امکان تبدیل ایمیل‌های دریافتی به تیکت و ارسال پاسخ تیکت‌ها به ایمیل را فراهم می‌کند
 */
class EmailTicketIntegrationService
{
    use LogsTicketActivity;

    protected TicketTriageService $triage;
    protected TicketSlaService $sla;

    public function __construct(TicketTriageService $triage, TicketSlaService $sla)
    {
        $this->triage = $triage;
        $this->sla = $sla;
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

            // پیدا کردن کاربر با ایمیل
            $user = User::where('email', $fromEmail)->first();

            // اگر این ایمیل پاسخ به یک تیکت است
            if ($inReplyTo || $references) {
                $ticket = $this->findTicketByMessageId($inReplyTo, $references);
                
                if ($ticket) {
                    // اضافه کردن کامنت به تیکت موجود
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

                    // به‌روزرسانی وضعیت تیکت
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

            // ایجاد تیکت جدید
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

    /**
     * استخراج متن اصلی ایمیل از body
     */
    protected function extractEmailBody(array $emailData): string
    {
        // اولویت: text/plain > text/html
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

    /**
     * پیدا کردن تیکت بر اساس Message-ID
     */
    protected function findTicketByMessageId(?string $inReplyTo, ?string $references): ?Ticket
    {
        // جستجو در metadata کامنت‌ها
        $comment = TicketComment::whereJsonContains('metadata->message_id', $inReplyTo)
            ->orWhereJsonContains('metadata->message_id', $references)
            ->first();

        if ($comment) {
            return $comment->ticket;
        }

        // جستجو در metadata تیکت‌ها
        return Ticket::whereJsonContains('metadata->message_id', $inReplyTo)
            ->orWhereJsonContains('metadata->message_id', $references)
            ->first();
    }

    /**
     * ایجاد تیکت جدید از ایمیل
     */
    protected function createTicketFromEmail(
        string $fromEmail,
        ?string $fromName,
        string $subject,
        string $body,
        ?User $user,
        ?string $messageId
    ): Ticket {
        // حذف پیشوند "Re:" یا "Fwd:" از subject
        $cleanSubject = preg_replace('/^(Re:|Fwd:|FW:)\s*/i', '', $subject);

        // تریاژ خودکار
        $triageResult = $this->triage->triage($cleanSubject, $body);

        // ایجاد کد پیگیری منحصر به فرد
        do {
            $trackingCode = 'TK-' . strtoupper(Str::random(8));
        } while (Ticket::where('tracking_code', $trackingCode)->exists());

        // محاسبه SLA
        $priority = $triageResult['priority'] ?? 'normal';
        $slaDeadline = $this->sla->calculateDeadline(
            new Ticket(['priority' => $priority, 'created_at' => now()])
        );

        // ایجاد تیکت
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

        // ثبت فعالیت
        $this->logTicketCreated($ticket);

        return $ticket;
    }

    /**
     * ارسال پاسخ تیکت به ایمیل کاربر
     */
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

            // ساخت subject ایمیل
            $subject = $ticket->tracking_code . ' - ' . $ticket->subject;

            // ساخت body ایمیل
            $body = view('emails.ticket-reply', [
                'ticket' => $ticket,
                'comment' => $comment,
                'user' => $user,
                'commenter' => $commenter,
            ])->render();

            // ارسال ایمیل
            $messageId = null;
            Mail::html($body, function ($message) use ($ticket, $subject, &$messageId) {
                $message->to($ticket->email, $ticket->name)
                    ->subject($subject)
                    ->replyTo(config('mail.support_email', 'support@earthcoop.org'), 'پشتیبانی EarthCoop');
                
                // تنظیم Message-ID برای ردیابی
                $host = parse_url(config('app.url'), PHP_URL_HOST) ?? 'earthcoop.org';
                $messageId = '<ticket-' . $ticket->id . '-comment-' . time() . '@' . $host . '>';
                $message->getHeaders()->addTextHeader('Message-ID', $messageId);
                $message->getHeaders()->addTextHeader('In-Reply-To', '<ticket-' . $ticket->id . '@' . $host . '>');
                $message->getHeaders()->addTextHeader('References', '<ticket-' . $ticket->id . '@' . $host . '>');
            });

            // ذخیره Message-ID برای ردیابی
            if ($messageId) {
                $comment->update([
                    'metadata' => array_merge($comment->metadata ?? [], [
                        'email_sent' => true,
                        'email_sent_at' => now()->toIso8601String(),
                        'message_id' => $messageId,
                    ]),
                ]);
            }

            $this->emitRuntime('najm_hoda.input.support.service.email_integration.send_reply.succeeded', $context);
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

    /**
     * ارسال اعلان ایجاد تیکت به ایمیل کاربر
     */
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

            $body = view('emails.ticket-created', [
                'ticket' => $ticket,
            ])->render();

            Mail::html($body, function ($message) use ($ticket, $subject) {
                $message->to($ticket->email, $ticket->name)
                    ->subject($subject);
            });

            $this->emitRuntime('najm_hoda.input.support.service.email_integration.send_created.succeeded', $context);
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

    /**
     * @param array<string, mixed> $payload
     */
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



