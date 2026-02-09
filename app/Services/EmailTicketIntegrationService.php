<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\TicketTriageService;
use App\Services\TicketSlaService;
use App\Traits\LogsTicketActivity;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

                    return $ticket;
                }
            }

            // ایجاد تیکت جدید
            return $this->createTicketFromEmail($fromEmail, $fromName, $subject, $body, $user, $messageId);

        } catch (\Exception $e) {
            Log::error('خطا در پردازش ایمیل دریافتی: ' . $e->getMessage(), [
                'email_data' => $emailData,
                'trace' => $e->getTraceAsString(),
            ]);

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
        try {
            if (!$ticket->email) {
                Log::warning('تیکت بدون ایمیل کاربر: ' . $ticket->id);
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

            return true;

        } catch (\Exception $e) {
            Log::error('خطا در ارسال پاسخ تیکت به ایمیل: ' . $e->getMessage(), [
                'ticket_id' => $ticket->id,
                'comment_id' => $comment->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * ارسال اعلان ایجاد تیکت به ایمیل کاربر
     */
    public function sendTicketCreatedEmail(Ticket $ticket): bool
    {
        try {
            if (!$ticket->email) {
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

            return true;

        } catch (\Exception $e) {
            Log::error('خطا در ارسال ایمیل ایجاد تیکت: ' . $e->getMessage(), [
                'ticket_id' => $ticket->id,
            ]);

            return false;
        }
    }
}



