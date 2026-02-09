<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\EmailTicketIntegrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * کنترلر Webhook برای دریافت ایمیل‌ها (Mailgun)
 * 
 * این endpoint توسط Mailgun برای ارسال ایمیل‌های دریافتی فراخوانی می‌شود
 */
class EmailWebhookController extends Controller
{
    protected EmailTicketIntegrationService $emailService;

    public function __construct(EmailTicketIntegrationService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * دریافت ایمیل از Mailgun
     * 
     * POST /api/email/webhook
     */
    public function webhook(Request $request)
    {
        // بررسی signature (برای امنیت)
        if (!$this->verifySignature($request)) {
            Log::warning('Email webhook signature verification failed', [
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            // پردازش داده‌های ایمیل
            $emailData = [
                'from' => [
                    'email' => $request->input('sender'),
                    'name' => $request->input('From'),
                ],
                'to' => $request->input('recipient'),
                'subject' => $request->input('subject'),
                'body' => $request->input('body-plain') ?? $request->input('body-html'),
                'text_plain' => $request->input('body-plain'),
                'text_html' => $request->input('body-html'),
                'message_id' => $request->input('Message-Id'),
                'in_reply_to' => $request->input('In-Reply-To'),
                'references' => $request->input('References'),
                'attachments' => $this->processAttachments($request),
            ];

            // تبدیل به تیکت یا کامنت
            $ticket = $this->emailService->processIncomingEmail($emailData);

            if ($ticket) {
                Log::info('Email converted to ticket', [
                    'ticket_id' => $ticket->id,
                    'tracking_code' => $ticket->tracking_code,
                    'from_email' => $emailData['from']['email'],
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'ایمیل با موفقیت پردازش شد',
                    'ticket_id' => $ticket->id,
                    'tracking_code' => $ticket->tracking_code,
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'نتوانستیم ایمیل را پردازش کنیم',
            ], 400);

        } catch (\Exception $e) {
            Log::error('خطا در پردازش webhook ایمیل: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'خطا در پردازش ایمیل',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * بررسی signature Mailgun برای امنیت
     */
    protected function verifySignature(Request $request): bool
    {
        $signature = $request->input('signature');
        $timestamp = $request->input('timestamp');
        $token = $request->input('token');
        $mailgunKey = env('MAILGUN_SECRET');

        if (!$mailgunKey || !$signature || !$timestamp || !$token) {
            return false;
        }

        $computedSignature = hash_hmac('sha256', $timestamp . $token, $mailgunKey);

        return hash_equals($signature, $computedSignature);
    }

    /**
     * پردازش فایل‌های ضمیمه
     */
    protected function processAttachments(Request $request): array
    {
        $attachments = [];

        // Mailgun attachments info
        $attachmentCount = (int) ($request->input('attachment-count') ?? 0);

        for ($i = 1; $i <= $attachmentCount; $i++) {
            $attachmentUrl = $request->input("attachment-{$i}");
            
            if ($attachmentUrl) {
                // دانلود و ذخیره attachment (می‌توانید پیاده‌سازی کنید)
                $attachments[] = [
                    'url' => $attachmentUrl,
                    'name' => $request->input("attachment-name-{$i}"),
                ];
            }
        }

        return $attachments;
    }
}
