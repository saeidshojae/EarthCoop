<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NajmHodaIntegrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * کنترلر API برای تبدیل مکالمات نجم‌هدا به تیکت
 *
 * این endpoint توسط سرویس خارجی نجم‌هدا فراخوانی می‌شود.
 */
class NajmHodaEscalationController extends Controller
{
    protected NajmHodaIntegrationService $integrationService;

    public function __construct(NajmHodaIntegrationService $integrationService)
    {
        $this->integrationService = $integrationService;
    }

    public function escalate(Request $request)
    {
        $token = (string) $request->header('X-NAJM-HODA-TOKEN', '');
        $expectedToken = (string) config('najm-hoda.integration.escalation_token', env('NAJM_HODA_TOKEN', ''));

        // Fail closed. Use constant-time comparison and never log any part of a secret.
        if ($expectedToken === '' || $token === '' || !hash_equals($expectedToken, $token)) {
            Log::warning('Najm Hoda escalation authentication failed', [
                'ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 200),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'conversation_id' => 'required|string|max:255',
            'transcript' => 'required|string|min:10|max:50000',
            'user_email' => 'nullable|email|max:255',
            'user_id' => 'nullable|integer|exists:users,id',
            'reason' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
        ], [
            'conversation_id.required' => 'شناسه مکالمه الزامی است',
            'transcript.required' => 'متن مکالمه الزامی است',
            'transcript.min' => 'متن مکالمه باید حداقل 10 کاراکتر باشد',
            'user_email.email' => 'ایمیل معتبر نیست',
            'user_id.exists' => 'کاربر یافت نشد',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $ticket = $this->integrationService->handleEscalation($validator->validated());

            Log::info('Najm Hoda conversation escalated to ticket', [
                'conversation_id' => $request->conversation_id,
                'ticket_id' => $ticket->id,
                'tracking_code' => $ticket->tracking_code,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'مکالمه با موفقیت به تیکت تبدیل شد',
                'ticket' => [
                    'id' => $ticket->id,
                    'tracking_code' => $ticket->tracking_code,
                    'subject' => $ticket->subject,
                    'status' => $ticket->status,
                    'priority' => $ticket->priority,
                    'created_at' => $ticket->created_at->toIso8601String(),
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('خطا در تبدیل مکالمه نجم‌هدا به تیکت', [
                'conversation_id' => $request->conversation_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'خطا در تبدیل مکالمه به تیکت',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
