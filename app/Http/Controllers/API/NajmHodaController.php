<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\NajmHoda\NajmHodaOrchestrator;
use App\Services\NajmHoda\Runtime\NajmHodaEntryPolicy;
use App\Services\NajmHoda\Runtime\NajmHodaExecutionService;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * کنترلر API برای نجم‌هدا
 * 
 * این کنترلر تمام درخواست‌های مرتبط با چت و تعامل با نجم‌هدا را مدیریت می‌کند
 */
class NajmHodaController extends Controller
{
    protected NajmHodaOrchestrator $najmHoda;
    protected NajmHodaEntryPolicy $entryPolicy;
    protected NajmHodaExecutionService $executionService;
    
    public function __construct(
        NajmHodaOrchestrator $najmHoda,
        NajmHodaEntryPolicy $entryPolicy,
        NajmHodaExecutionService $executionService
    )
    {
        $this->najmHoda = $najmHoda;
        $this->entryPolicy = $entryPolicy;
        $this->executionService = $executionService;
    }
    
    /**
     * پیام خوش‌آمدگویی
     * 
     * GET /api/najm-hoda/welcome
     */
    public function welcome()
    {
        if ($policyResponse = $this->denyByEntryPolicy('api.welcome', false)) {
            return $policyResponse;
        }

        return response()->json([
            'success' => true,
            'message' => $this->najmHoda->getWelcomeMessage(),
            'stats' => $this->najmHoda->getSystemStats(),
        ]);
    }
    
    /**
     * چت با نجم‌هدا
     * 
     * POST /api/najm-hoda/chat
     */
    public function chat(Request $request)
    {
        if ($policyResponse = $this->denyByEntryPolicy('api.chat')) {
            return $policyResponse;
        }

        $user = auth()->user();
        $isAdmin = $user ? ($user->is_admin || $user->hasRole('super-admin')) : false;

        if (!$isAdmin) {
            $request->merge(['agent' => 'steward']);
        }

        // اعتبارسنجی
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:2000',
            'agent' => 'nullable|in:auto,engineer,pilot,steward,guide',
            'conversation_id' => 'nullable|exists:conversations,id',
            'context' => 'nullable|array',
        ], [
            'message.required' => 'لطفاً پیام خود را وارد کنید',
            'message.max' => 'پیام نباید بیشتر از 2000 کاراکتر باشد',
            'agent.in' => 'عامل انتخاب شده معتبر نیست',
            'conversation_id.exists' => 'مکالمه یافت نشد',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }
        
        try {
            // پیدا کردن یا ایجاد مکالمه
            $conversation = $this->getOrCreateConversation($request);
            
            // ذخیره پیام کاربر
            $this->saveUserMessage($conversation, $request->message);
            
            // ارسال به نجم‌هدا
            $context = array_merge($request->context ?? [], [
                'conversation' => $conversation,
                'user_id' => auth()->id(),
                'user_is_admin' => $isAdmin,
            ]);
            
            if ($request->agent && $request->agent !== 'auto') {
                $context['force_agent'] = $request->agent;
            }
            $response = $this->executionService->executeChat(
                $this->najmHoda,
                (string) $request->message,
                $context
            );

            if ((bool) ($response['success'] ?? false)) {
                $this->saveAssistantMessage(
                    $conversation,
                    (string) ($response['message'] ?? ''),
                    (string) ($response['agent'] ?? 'unknown')
                );
            }

            if (!(bool) ($response['success'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'message' => (string) ($response['message'] ??  'عملیات با خطا مواجه شد. لطفاً مجدداً تلاش کنید.'),
                    'agent' => (string) ($response['agent'] ?? 'system'),
                    'request_id' => (string) ($response['request_id'] ?? ''),
                    'response_time_ms' => (int) ($response['response_time_ms'] ?? 0),
                    'error' => $response['error'] ?? null,
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => (string) ($response['message'] ?? ''),
                'agent' => (string) ($response['agent'] ?? 'unknown'),
                'agent_name' => (string) ($response['agent_name'] ?? 'نجم هدا'),
                'agent_icon' => (string) ($response['agent_icon'] ?? '??'),
                'conversation_id' => $conversation->id,
                'suggestions' => (array) ($response['suggestions'] ?? []),
                'response_time_ms' => (int) ($response['response_time_ms'] ?? 0),
                'request_id' => (string) ($response['request_id'] ?? ''),
            ]);
            
        } catch (\Exception $e) {
            Log::error('خطا در چت با نجم‌هدا: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'message' => $request->message,
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'متأسفانه مشکلی پیش آمد. لطفاً دوباره تلاش کنید.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
    
    /**
     * دریافت تاریخچه مکالمه
     * 
     * GET /api/najm-hoda/conversations/{id}
     */
    public function getConversation($id)
    {
        if ($policyResponse = $this->denyByEntryPolicy('api.conversation.show')) {
            return $policyResponse;
        }

        try {
            $conversation = Conversation::with(['messages' => function($query) {
                $query->orderBy('created_at', 'asc');
            }])->findOrFail($id);
            
            // بررسی دسترسی
            if ($conversation->user_id && $conversation->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'شما دسترسی به این مکالمه ندارید',
                ], 403);
            }
            
            return response()->json([
                'success' => true,
                'conversation' => [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                    'agent_type' => $conversation->agent_type,
                    'created_at' => $conversation->created_at,
                    'messages' => $conversation->messages->map(function($msg) {
                        return [
                            'role' => $msg->role,
                            'content' => $msg->content,
                            'created_at' => $msg->created_at,
                        ];
                    }),
                ],
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'مکالمه یافت نشد',
            ], 404);
        }
    }
    
    /**
     * لیست مکالمات کاربر
     * 
     * GET /api/najm-hoda/conversations
     */
    public function listConversations(Request $request)
    {
        if ($policyResponse = $this->denyByEntryPolicy('api.conversation.list')) {
            return $policyResponse;
        }

        $query = Conversation::where('user_id', auth()->id())
            ->with('lastMessage')
            ->latest();
        
        if ($request->status) {
            $query->where('status', $request->status);
        }
        
        if ($request->agent) {
            $query->where('agent_type', $request->agent);
        }
        
        $conversations = $query->paginate($request->per_page ?? 20);
        
        return response()->json([
            'success' => true,
            'conversations' => $conversations->map(function($conv) {
                return [
                    'id' => $conv->id,
                    'title' => $conv->title ?? 'بدون عنوان',
                    'agent_type' => $conv->agent_type,
                    'status' => $conv->status,
                    'last_message' => $conv->lastMessage?->content,
                    'updated_at' => $conv->updated_at,
                    'created_at' => $conv->created_at,
                ];
            }),
            'pagination' => [
                'current_page' => $conversations->currentPage(),
                'total' => $conversations->total(),
                'per_page' => $conversations->perPage(),
                'last_page' => $conversations->lastPage(),
            ],
        ]);
    }
    
    /**
     * حذف مکالمه
     * 
     * DELETE /api/najm-hoda/conversations/{id}
     */
    public function deleteConversation($id)
    {
        if ($policyResponse = $this->denyByEntryPolicy('api.conversation.delete')) {
            return $policyResponse;
        }

        try {
            $conversation = Conversation::findOrFail($id);
            
            // بررسی دسترسی
            if ($conversation->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'شما دسترسی به این مکالمه ندارید',
                ], 403);
            }
            
            $conversation->update(['status' => 'deleted']);
            
            return response()->json([
                'success' => true,
                'message' => 'مکالمه با موفقیت حذف شد',
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'مکالمه یافت نشد',
            ], 404);
        }
    }
    
    /**
     * آرشیو مکالمه
     * 
     * PUT /api/najm-hoda/conversations/{id}/archive
     */
    public function archiveConversation($id)
    {
        if ($policyResponse = $this->denyByEntryPolicy('api.conversation.archive')) {
            return $policyResponse;
        }

        try {
            $conversation = Conversation::findOrFail($id);
            
            if ($conversation->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'شما دسترسی به این مکالمه ندارید',
                ], 403);
            }
            
            $conversation->update(['status' => 'archived']);
            
            return response()->json([
                'success' => true,
                'message' => 'مکالمه آرشیو شد',
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'مکالمه یافت نشد',
            ], 404);
        }
    }
    
    /**
     * ارسال بازخورد
     * 
     * POST /api/najm-hoda/feedback
     */
    public function submitFeedback(Request $request)
    {
        if ($policyResponse = $this->denyByEntryPolicy('api.feedback.submit')) {
            return $policyResponse;
        }

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:bug,feature_request,improvement,complaint,praise,other',
            'subject' => 'required|string|max:200',
            'content' => 'required|string|max:2000',
            'rating' => 'nullable|integer|min:1|max:5',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }
        
        try {
            $feedback = \App\Models\Feedback::create([
                'user_id' => auth()->id(),
                'type' => $request->type,
                'subject' => $request->subject,
                'content' => $request->content,
                'rating' => $request->rating,
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'بازخورد شما ثبت شد. متشکریم!',
                'feedback_id' => $feedback->id,
            ]);
            
        } catch (\Exception $e) {
            Log::error('خطا در ثبت بازخورد: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'خطا در ثبت بازخورد',
            ], 500);
        }
    }
    
    /**
     * دریافت آمار سیستم (فقط ادمین)
     * 
     * GET /api/najm-hoda/stats
     */
    public function getStats()
    {
        if ($policyResponse = $this->denyByEntryPolicy('api.stats', false)) {
            return $policyResponse;
        }

        $this->authorize('admin');
        
        try {
            $stats = [
                'total_interactions' => \App\Models\AIInteraction::count(),
                'today_interactions' => \App\Models\AIInteraction::today()->count(),
                'month_interactions' => \App\Models\AIInteraction::thisMonth()->count(),
                'total_conversations' => Conversation::count(),
                'active_conversations' => Conversation::active()->count(),
                'total_cost' => \App\Models\AIInteraction::thisMonth()->sum('cost'),
                'agents_usage' => [
                    'engineer' => \App\Models\AIInteraction::byAgent('engineer')->thisMonth()->count(),
                    'pilot' => \App\Models\AIInteraction::byAgent('pilot')->thisMonth()->count(),
                    'steward' => \App\Models\AIInteraction::byAgent('steward')->thisMonth()->count(),
                    'guide' => \App\Models\AIInteraction::byAgent('guide')->thisMonth()->count(),
                ],
            ];
            
            return response()->json([
                'success' => true,
                'stats' => $stats,
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت آمار',
            ], 500);
        }
    }
    
    /**
     * پیدا کردن یا ایجاد مکالمه
     */
    protected function getOrCreateConversation(Request $request): Conversation
    {
        if ($request->conversation_id) {
            return Conversation::findOrFail($request->conversation_id);
        }
        
        return Conversation::create([
            'user_id' => auth()->id(),
            'title' => $this->generateTitle($request->message),
            'agent_type' => $request->agent ?? 'auto',
            'status' => 'active',
        ]);
    }
    
    /**
     * ذخیره پیام کاربر
     */
    protected function saveUserMessage(Conversation $conversation, string $message): ConversationMessage
    {
        return $conversation->messages()->create([
            'role' => 'user',
            'content' => $message,
        ]);
    }
    
    /**
     * ذخیره پاسخ دستیار
     */
    protected function saveAssistantMessage(Conversation $conversation, string $message, string $agent): ConversationMessage
    {
        return $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $message,
            'metadata' => [
                'agent' => $agent,
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
    
    /**
     * تولید عنوان برای مکالمه
     */
    protected function generateTitle(string $message): string
    {
        // عنوان را از 50 کاراکتر اول پیام بسازیم
        $title = mb_substr($message, 0, 50);
        
        if (mb_strlen($message) > 50) {
            $title .= '...';
        }
        
        return $title;
    }

    protected function denyByEntryPolicy(string $entrypoint, bool $enforceRateLimit = true)
    {
        $entryPolicy = $this->entryPolicy->check(
            $entrypoint,
            auth()->id(),
            request()->ip(),
            $enforceRateLimit
        );

        if ((bool) ($entryPolicy['allowed'] ?? false)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => (string) ($entryPolicy['message'] ?? 'Request denied by policy.'),
            'code' => (string) ($entryPolicy['code'] ?? 'NAJM_HODA_POLICY_DENIED'),
        ], (int) ($entryPolicy['status'] ?? 403));
    }
}
