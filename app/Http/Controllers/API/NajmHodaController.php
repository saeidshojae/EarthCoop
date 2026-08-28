<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\NajmHoda\NajmHodaOrchestrator;
use App\Services\NajmHoda\NajmHodaPrivateGroupActionItemCommandService;
use App\Services\NajmHoda\NajmHodaChatEscalationService;
use App\Services\NajmHoda\Context\NajmHodaPageContextResolver;
use App\Services\NajmHoda\Context\NajmHodaSecretariatDraftAssistant;
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
    protected NajmHodaPageContextResolver $pageContextResolver;
    protected NajmHodaPrivateGroupActionItemCommandService $privateGroupActionItemCommandService;
    protected NajmHodaSecretariatDraftAssistant $secretariatDraftAssistant;
    protected NajmHodaChatEscalationService $chatEscalationService;

    public function __construct(
        NajmHodaOrchestrator $najmHoda,
        NajmHodaEntryPolicy $entryPolicy,
        NajmHodaExecutionService $executionService,
        NajmHodaPageContextResolver $pageContextResolver,
        NajmHodaPrivateGroupActionItemCommandService $privateGroupActionItemCommandService,
        NajmHodaSecretariatDraftAssistant $secretariatDraftAssistant,
        NajmHodaChatEscalationService $chatEscalationService
    ) {
        $this->najmHoda = $najmHoda;
        $this->entryPolicy = $entryPolicy;
        $this->executionService = $executionService;
        $this->pageContextResolver = $pageContextResolver;
        $this->privateGroupActionItemCommandService = $privateGroupActionItemCommandService;
        $this->secretariatDraftAssistant = $secretariatDraftAssistant;
        $this->chatEscalationService = $chatEscalationService;
    }

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

        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:2000',
            'agent' => 'nullable|in:auto,engineer,pilot,steward,guide',
            // Existence is deliberately checked inside the owner-scoped query below.
            // This also avoids leaking whether another user's conversation ID exists.
            'conversation_id' => 'nullable|integer|min:1',
            'context' => 'nullable|array',
        ], [
            'message.required' => 'لطفاً پیام خود را وارد کنید',
            'message.max' => 'پیام نباید بیشتر از 2000 کاراکتر باشد',
            'agent.in' => 'عامل انتخاب شده معتبر نیست',
            'conversation_id.integer' => 'شناسه مکالمه معتبر نیست',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $conversation = $this->getOrCreateConversation($request);
            $this->saveUserMessage($conversation, $request->message);

            // Browser context is informational only. ExecutionService strips all
            // action authority fields unless trusted server code supplies authority.
            $context = array_merge($request->context ?? [], [
                'conversation' => $conversation,
                'user_id' => auth()->id(),
                'user_is_admin' => $isAdmin,
            ]);

            if ($request->agent && $request->agent !== 'auto') {
                $context['force_agent'] = $request->agent;
            }

            // Private page-aware mutation helpers run only after the browser page
            // hint has been resolved again on the server. Secretariat drafting gets
            // first chance on Secretariat pages; it only previews or saves Drafts,
            // never submits/registers/dispatches/publishes. Group action-item flow
            // remains unchanged for group pages.
            $response = null;
            if ($user) {
                $browserPage = is_array(data_get($request->context ?? [], 'page'))
                    ? (array) data_get($request->context ?? [], 'page')
                    : [];
                $pageContext = $this->pageContextResolver->resolve($user, ['page' => $browserPage]);

                $response = $this->secretariatDraftAssistant->intercept(
                    $user,
                    $pageContext,
                    (string) $request->message,
                    (int) $conversation->id
                );

                if (! is_array($response)) {
                    $response = $this->privateGroupActionItemCommandService->intercept(
                        $user,
                        $pageContext,
                        (string) $request->message,
                        (int) $conversation->id
                    );
                }
            }

            if (! is_array($response)) {
                $response = $this->executionService->executeChat(
                    $this->najmHoda,
                    (string) $request->message,
                    $context
                );
            }

            if ((bool) ($response['success'] ?? false)) {
                $this->saveAssistantMessage(
                    $conversation,
                    (string) ($response['message'] ?? ''),
                    (string) ($response['agent'] ?? 'unknown')
                );
            }

            $supportTicket = null;
            if ($this->chatEscalationService->shouldEscalate((string) $request->message, $response)) {
                try {
                    $supportTicket = $this->chatEscalationService->escalate(
                        $conversation,
                        $user,
                        (string) $request->message,
                        $response
                    );
                } catch (\Throwable $escalationError) {
                    Log::error('خطا در ارجاع خودکار مکالمه نجم هدا به پشتیبانی', [
                        'conversation_id' => $conversation->id,
                        'user_id' => auth()->id(),
                        'error' => $escalationError->getMessage(),
                    ]);
                }
            }

            if (!(bool) ($response['success'] ?? false)) {
                if ($supportTicket) {
                    return response()->json([
                        'success' => true,
                        'resolution' => 'escalated_to_support',
                        'message' => 'نتوانستم این موضوع را با اطمینان حل کنم؛ مکالمه به پشتیبانی ارجاع شد.',
                        'agent' => (string) ($response['agent'] ?? 'system'),
                        'conversation_id' => $conversation->id,
                        'request_id' => (string) ($response['request_id'] ?? ''),
                        'response_time_ms' => (int) ($response['response_time_ms'] ?? 0),
                        'escalated_to_support' => true,
                        'ticket' => [
                            'id' => $supportTicket->id,
                            'tracking_code' => $supportTicket->tracking_code,
                            'status' => $supportTicket->status,
                            'priority' => $supportTicket->priority,
                            'category' => $supportTicket->category,
                        ],
                    ], 202);
                }

                return response()->json([
                    'success' => false,
                    'message' => (string) ($response['message'] ?? 'عملیات با خطا مواجه شد. لطفاً مجدداً تلاش کنید.'),
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
                'agent_icon' => (string) ($response['agent_icon'] ?? '🤖'),
                'conversation_id' => $conversation->id,
                'suggestions' => (array) ($response['suggestions'] ?? []),
                'response_time_ms' => (int) ($response['response_time_ms'] ?? 0),
                'request_id' => (string) ($response['request_id'] ?? ''),
                'escalated_to_support' => (bool) $supportTicket,
                'ticket' => $supportTicket ? [
                    'id' => $supportTicket->id,
                    'tracking_code' => $supportTicket->tracking_code,
                    'status' => $supportTicket->status,
                    'priority' => $supportTicket->priority,
                    'category' => $supportTicket->category,
                ] : null,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'مکالمه یافت نشد',
            ], 404);
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

    public function getConversation($id)
    {
        if ($policyResponse = $this->denyByEntryPolicy('api.conversation.show')) {
            return $policyResponse;
        }

        try {
            $conversation = $this->ownedConversationQuery()
                ->with(['messages' => function ($query) {
                    $query->orderBy('created_at', 'asc');
                }])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'conversation' => [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                    'agent_type' => $conversation->agent_type,
                    'created_at' => $conversation->created_at,
                    'messages' => $conversation->messages->map(function ($msg) {
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

    public function listConversations(Request $request)
    {
        if ($policyResponse = $this->denyByEntryPolicy('api.conversation.list')) {
            return $policyResponse;
        }

        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:active,archived,deleted',
            'agent' => 'nullable|in:auto,engineer,pilot,steward,guide',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = $this->ownedConversationQuery()
            ->with('lastMessage')
            ->latest();

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->agent) {
            $query->where('agent_type', $request->agent);
        }

        $conversations = $query->paginate((int) ($request->per_page ?? 20));

        return response()->json([
            'success' => true,
            'conversations' => $conversations->map(function ($conv) {
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

    public function deleteConversation($id)
    {
        if ($policyResponse = $this->denyByEntryPolicy('api.conversation.delete')) {
            return $policyResponse;
        }

        try {
            $conversation = $this->ownedConversationQuery()->findOrFail($id);
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

    public function archiveConversation($id)
    {
        if ($policyResponse = $this->denyByEntryPolicy('api.conversation.archive')) {
            return $policyResponse;
        }

        try {
            $conversation = $this->ownedConversationQuery()->findOrFail($id);
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
     * Owner scoping is applied in SQL, before the conversation is materialized.
     * Foreign and nonexistent IDs therefore have the same externally visible result.
     */
    protected function ownedConversationQuery()
    {
        return Conversation::query()->where('user_id', auth()->id());
    }

    protected function getOrCreateConversation(Request $request): Conversation
    {
        if ($request->conversation_id) {
            return $this->ownedConversationQuery()->findOrFail((int) $request->conversation_id);
        }

        return Conversation::create([
            'user_id' => auth()->id(),
            'title' => $this->generateTitle($request->message),
            'agent_type' => $request->agent ?? 'auto',
            'status' => 'active',
        ]);
    }

    protected function saveUserMessage(Conversation $conversation, string $message): ConversationMessage
    {
        return $conversation->messages()->create([
            'role' => 'user',
            'content' => $message,
        ]);
    }

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

    protected function generateTitle(string $message): string
    {
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
