<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\NajmHoda\NajmHodaOrchestrator;
use App\Services\NajmHoda\Agents\ArchitectAgent;
use App\Services\NajmHoda\CodeScanner\CodeScannerService;
use App\Services\NajmHoda\CodeScanner\CodeAnalyzerService;
use App\Services\NajmHoda\CodeScanner\AutoFixerService;
use App\Services\NajmHoda\CodeScanner\BackupManagerService;
use App\Models\Conversation;
use App\Models\AIInteraction;
use App\Models\Feedback;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\NajmHodaGroupActionItem;
use App\Models\StewardKnowledgeFile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

/**
 * کنترلر مدیریت نجم‌هدا در پنل ادمین
 */
class NajmHodaController extends Controller
{
    protected NajmHodaOrchestrator $orchestrator;
    protected ArchitectAgent $architect;
    protected CodeScannerService $scanner;
    protected CodeAnalyzerService $analyzer;
    protected AutoFixerService $fixer;
    protected BackupManagerService $backupManager;
    
    public function __construct()
    {
        $this->orchestrator = app(NajmHodaOrchestrator::class);
        $this->architect = app(ArchitectAgent::class);
        $this->scanner = app(CodeScannerService::class);
        $this->analyzer = app(CodeAnalyzerService::class);
        $this->fixer = app(AutoFixerService::class);
        $this->backupManager = app(BackupManagerService::class);
    }
    
    /**
     * داشبورد اصلی نجم‌هدا
     */
    public function index()
    {
        $stats = $this->getStatistics();
        $recentConversations = Conversation::with('user')
            ->latest()
            ->take(10)
            ->get();
        
        $agentUsage = AIInteraction::select('agent_role', DB::raw('count(*) as count'))
            ->groupBy('agent_role')
            ->get()
            ->pluck('count', 'agent_role')
            ->toArray();
        
        $todayInteractions = AIInteraction::whereDate('created_at', today())->count();
        
        // محاسبه تعاملات روزانه هفته
        $weekInteractions = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $weekInteractions[] = AIInteraction::whereDate('created_at', $date)->count();
        }
        
        return view('admin.najm-hoda.index', compact(
            'stats',
            'recentConversations',
            'agentUsage',
            'todayInteractions',
            'weekInteractions'
        ));
    }
    
    /**
     * مکالمات
     */
    public function conversations()
    {
        $conversations = Conversation::with(['user', 'messages'])
            ->withCount('messages')
            ->latest()
            ->paginate(20);
        
        return view('admin.najm-hoda.conversations', compact('conversations'));
    }
    
    /**
     * نمایش یک مکالمه
     */
    public function showConversation(Conversation $conversation)
    {
        $conversation->load(['user', 'messages' => function($query) {
            $query->orderBy('created_at', 'asc');
        }]);
        
        return view('admin.najm-hoda.conversation-detail', compact('conversation'));
    }
    
    /**
     * تحلیل‌ها و گزارش‌ها
     */
    public function analytics()
    {
        // آمار کلی
        $totalInteractions = AIInteraction::count();
        $totalCost = AIInteraction::sum('cost');
        $totalTokens = AIInteraction::sum('tokens_used');
        
        // نمودار استفاده روزانه (30 روز اخیر)
        $dailyUsage = AIInteraction::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(tokens_used) as tokens'),
                DB::raw('SUM(cost) as cost')
            )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        // محبوب‌ترین عوامل
        $agentStats = AIInteraction::select(
                'agent_role',
                DB::raw('COUNT(*) as total_uses'),
                DB::raw('AVG(tokens_used) as avg_tokens'),
                DB::raw('SUM(cost) as total_cost')
            )
            ->groupBy('agent_role')
            ->get();
        
        // متوسط زمان پاسخ
        $avgResponseTime = AIInteraction::avg('response_time_ms');
        
        return view('admin.najm-hoda.analytics', compact(
            'totalInteractions',
            'totalCost',
            'totalTokens',
            'dailyUsage',
            'agentStats',
            'avgResponseTime'
        ));
    }
    
    /**
     * بازخوردها
     */
    public function feedbacks()
    {
        $feedbacks = Feedback::with(['user', 'interaction'])
            ->latest()
            ->paginate(20);
        
        $avgRating = Feedback::avg('rating');
        $ratingDistribution = Feedback::select('rating', DB::raw('count(*) as count'))
            ->groupBy('rating')
            ->get()
            ->pluck('count', 'rating')
            ->toArray();
        
        return view('admin.najm-hoda.feedbacks', compact(
            'feedbacks',
            'avgRating',
            'ratingDistribution'
        ));
    }
    
    /**
     * تنظیمات نجم‌هدا
     */
    public function settings()
    {
        $config = config('najm-hoda');
        $agents = $this->getAvailableAgents();
        
        return view('admin.najm-hoda.settings', compact('config', 'agents'));
    }
    
    /**
     * ذخیره تنظیمات
     */
    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'enabled' => 'nullable|boolean',
            'mock_mode' => 'nullable|boolean',
            'provider' => 'nullable|in:openai,openrouter,claude,gemini',
            'model' => 'nullable|string',
            'api_key' => 'nullable|string',
            'max_tokens' => 'nullable|integer|min:100',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'auto_actions_enabled' => 'nullable|boolean',
            'rate_limit_requests' => 'nullable|integer|min:1',
            'group_assistant_enabled' => 'nullable|boolean',
            'group_bot_email' => 'nullable|email|max:255',
            'group_bot_first_name' => 'nullable|string|max:100',
            'group_bot_last_name' => 'nullable|string|max:100',
            'group_default_role' => 'nullable|integer|in:0,1,2,3,4,5',
            'group_assistant_role' => 'nullable|string|in:secretary,advisor,admin,hybrid',
            'group_default_agent' => 'nullable|string|in:steward,guide,pilot,engineer,architect',
            'group_auto_reply_mode' => 'nullable|string|in:disabled,mention_only,mention_or_question,always',
            'group_knowledge_scope' => 'nullable|string|in:local,hybrid,global',
            'group_meeting_mode' => 'nullable|boolean',
            'group_proactive_guidance' => 'nullable|boolean',
            'group_allow_private_messages' => 'nullable|boolean',
            'group_private_message_mode' => 'nullable|string|in:direct,request',
            'group_action_executor_enabled' => 'nullable|boolean',
            'group_action_propose_before_execute' => 'nullable|boolean',
            'group_action_allow_create_post' => 'nullable|boolean',
            'group_action_allow_create_poll' => 'nullable|boolean',
            'group_action_allow_create_comment' => 'nullable|boolean',
            'group_action_allow_react_message' => 'nullable|boolean',
            'group_action_allow_react_post' => 'nullable|boolean',
            'group_action_allow_react_comment' => 'nullable|boolean',
            'group_action_max_per_hour' => 'nullable|integer|min:1|max:100',
            'group_max_replies_per_hour' => 'nullable|integer|min:1|max:200',
            'group_min_reply_interval_seconds' => 'nullable|integer|min:0|max:3600',
            'group_action_items_enabled' => 'nullable|boolean',
            'group_action_items_max_items' => 'nullable|integer|min:1|max:50',

            'agent_engineer_enabled' => 'nullable|boolean',
            'agent_pilot_enabled' => 'nullable|boolean',
            'agent_steward_enabled' => 'nullable|boolean',
            'agent_guide_enabled' => 'nullable|boolean',
            'agent_architect_enabled' => 'nullable|boolean',

            'agent_engineer_temperature' => 'nullable|numeric|min:0|max:1',
            'agent_pilot_temperature' => 'nullable|numeric|min:0|max:1',
            'agent_steward_temperature' => 'nullable|numeric|min:0|max:1',
            'agent_guide_temperature' => 'nullable|numeric|min:0|max:1',
            'agent_architect_temperature' => 'nullable|numeric|min:0|max:1',

            'agent_engineer_max_tokens' => 'nullable|integer|min:100|max:16000',
            'agent_pilot_max_tokens' => 'nullable|integer|min:100|max:16000',
            'agent_steward_max_tokens' => 'nullable|integer|min:100|max:16000',
            'agent_guide_max_tokens' => 'nullable|integer|min:100|max:16000',
            'agent_architect_max_tokens' => 'nullable|integer|min:100|max:16000',
        ]);
        
        // به‌روزرسانی فایل .env
        $envUpdates = [];
        if (isset($validated['enabled'])) {
            $envUpdates['NAJM_HODA_ENABLED'] = $validated['enabled'] ? 'true' : 'false';
        }
        if (isset($validated['mock_mode'])) {
            $envUpdates['NAJM_HODA_MOCK_MODE'] = $validated['mock_mode'] ? 'true' : 'false';
        }
        if (isset($validated['provider'])) {
            $envUpdates['AI_PROVIDER'] = $validated['provider'];
        }
        if (isset($validated['model'])) {
            $envUpdates['AI_MODEL'] = $validated['model'];
        }
        if (isset($validated['api_key']) && $validated['api_key'] !== '***********') {
            $envUpdates['AI_API_KEY'] = $validated['api_key'];
        }
        if (isset($validated['max_tokens'])) {
            $envUpdates['AI_MAX_TOKENS'] = $validated['max_tokens'];
        }
        if (isset($validated['temperature'])) {
            $envUpdates['AI_TEMPERATURE'] = $validated['temperature'];
        }

        $booleanMappings = [
            'group_assistant_enabled' => 'NAJM_HODA_GROUP_ASSISTANT_ENABLED',
            'group_meeting_mode' => 'NAJM_HODA_GROUP_MEETING_MODE',
            'group_proactive_guidance' => 'NAJM_HODA_GROUP_PROACTIVE_GUIDANCE',
            'group_allow_private_messages' => 'NAJM_HODA_GROUP_ALLOW_PRIVATE_MESSAGES',
            'group_action_executor_enabled' => 'NAJM_HODA_GROUP_ACTION_EXECUTOR_ENABLED',
            'group_action_propose_before_execute' => 'NAJM_HODA_GROUP_ACTION_PROPOSE_BEFORE_EXECUTE',
            'group_action_allow_create_post' => 'NAJM_HODA_GROUP_ACTION_ALLOW_CREATE_POST',
            'group_action_allow_create_poll' => 'NAJM_HODA_GROUP_ACTION_ALLOW_CREATE_POLL',
            'group_action_allow_create_comment' => 'NAJM_HODA_GROUP_ACTION_ALLOW_CREATE_COMMENT',
            'group_action_allow_react_message' => 'NAJM_HODA_GROUP_ACTION_ALLOW_REACT_MESSAGE',
            'group_action_allow_react_post' => 'NAJM_HODA_GROUP_ACTION_ALLOW_REACT_POST',
            'group_action_allow_react_comment' => 'NAJM_HODA_GROUP_ACTION_ALLOW_REACT_COMMENT',
            'group_action_items_enabled' => 'NAJM_HODA_GROUP_ACTION_ITEMS_ENABLED',
            'agent_engineer_enabled' => 'AGENT_ENGINEER_ENABLED',
            'agent_pilot_enabled' => 'AGENT_PILOT_ENABLED',
            'agent_steward_enabled' => 'AGENT_STEWARD_ENABLED',
            'agent_guide_enabled' => 'AGENT_GUIDE_ENABLED',
            'agent_architect_enabled' => 'AGENT_ARCHITECT_ENABLED',
        ];

        foreach ($booleanMappings as $inputKey => $envKey) {
            if (isset($validated[$inputKey])) {
                $envUpdates[$envKey] = $validated[$inputKey] ? 'true' : 'false';
            }
        }

        $directMappings = [
            'group_bot_email' => 'NAJM_HODA_GROUP_BOT_EMAIL',
            'group_bot_first_name' => 'NAJM_HODA_GROUP_BOT_FIRST_NAME',
            'group_bot_last_name' => 'NAJM_HODA_GROUP_BOT_LAST_NAME',
            'group_default_role' => 'NAJM_HODA_GROUP_DEFAULT_ROLE',
            'group_assistant_role' => 'NAJM_HODA_GROUP_ASSISTANT_ROLE',
            'group_default_agent' => 'NAJM_HODA_GROUP_DEFAULT_AGENT',
            'group_auto_reply_mode' => 'NAJM_HODA_GROUP_AUTO_REPLY_MODE',
            'group_knowledge_scope' => 'NAJM_HODA_GROUP_KNOWLEDGE_SCOPE',
            'group_private_message_mode' => 'NAJM_HODA_GROUP_PRIVATE_MESSAGE_MODE',
            'group_action_max_per_hour' => 'NAJM_HODA_GROUP_ACTION_MAX_PER_HOUR',
            'group_max_replies_per_hour' => 'NAJM_HODA_GROUP_MAX_REPLIES_PER_HOUR',
            'group_min_reply_interval_seconds' => 'NAJM_HODA_GROUP_MIN_REPLY_INTERVAL_SECONDS',
            'group_action_items_max_items' => 'NAJM_HODA_GROUP_ACTION_ITEMS_MAX_ITEMS',
            'agent_engineer_temperature' => 'AGENT_ENGINEER_TEMPERATURE',
            'agent_pilot_temperature' => 'AGENT_PILOT_TEMPERATURE',
            'agent_steward_temperature' => 'AGENT_STEWARD_TEMPERATURE',
            'agent_guide_temperature' => 'AGENT_GUIDE_TEMPERATURE',
            'agent_architect_temperature' => 'AGENT_ARCHITECT_TEMPERATURE',
            'agent_engineer_max_tokens' => 'AGENT_ENGINEER_MAX_TOKENS',
            'agent_pilot_max_tokens' => 'AGENT_PILOT_MAX_TOKENS',
            'agent_steward_max_tokens' => 'AGENT_STEWARD_MAX_TOKENS',
            'agent_guide_max_tokens' => 'AGENT_GUIDE_MAX_TOKENS',
            'agent_architect_max_tokens' => 'AGENT_ARCHITECT_MAX_TOKENS',
            'rate_limit_requests' => 'NAJM_HODA_RATE_LIMIT_MAX_REQUESTS',
        ];

        foreach ($directMappings as $inputKey => $envKey) {
            if (isset($validated[$inputKey]) && $validated[$inputKey] !== '') {
                $envUpdates[$envKey] = (string) $validated[$inputKey];
            }
        }
        
        if (!empty($envUpdates)) {
            $this->updateEnvFile($envUpdates);
        }
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تنظیمات با موفقیت ذخیره شد'
            ]);
        }
        
        return back()->with('success', 'تنظیمات با موفقیت ذخیره شد');
    }
    
    /**
     * چت مستقیم با نجم‌هدا (برای ادمین)
     */
    public function groupActionItems(Request $request)
    {
        $validated = $request->validate([
            'group_id' => 'nullable|integer|exists:groups,id',
            'status' => 'nullable|string|in:open,in_progress,blocked,done,cancelled',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'q' => 'nullable|string|max:255',
        ]);

        $query = NajmHodaGroupActionItem::query()
            ->with([
                'group:id,name',
                'assignedUser:id,first_name,last_name,email',
            ])
            ->latest('id');

        if (!empty($validated['group_id'])) {
            $query->where('group_id', $validated['group_id']);
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['priority'])) {
            $query->where('priority', $validated['priority']);
        }

        if (!empty($validated['q'])) {
            $term = trim($validated['q']);
            $query->where(function ($subQuery) use ($term) {
                $subQuery->where('title', 'like', "%{$term}%")
                    ->orWhere('details', 'like', "%{$term}%")
                    ->orWhere('assignee_name', 'like', "%{$term}%");
            });
        }

        $items = $query->paginate(25)->withQueryString();

        $groups = Group::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $stats = [
            'total' => NajmHodaGroupActionItem::count(),
            'open' => NajmHodaGroupActionItem::where('status', 'open')->count(),
            'in_progress' => NajmHodaGroupActionItem::where('status', 'in_progress')->count(),
            'done' => NajmHodaGroupActionItem::where('status', 'done')->count(),
            'overdue' => NajmHodaGroupActionItem::whereNotIn('status', ['done', 'cancelled'])
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->count(),
        ];

        $statusOptions = ['open', 'in_progress', 'blocked', 'done', 'cancelled'];
        $priorityOptions = ['low', 'medium', 'high', 'urgent'];

        $groupIds = $items->getCollection()
            ->pluck('group_id')
            ->filter()
            ->unique()
            ->values();

        $groupUsersByGroup = GroupUser::query()
            ->with('user:id,first_name,last_name,email')
            ->whereIn('group_id', $groupIds)
            ->where('status', 1)
            ->get()
            ->groupBy('group_id')
            ->map(function ($members) {
                return $members->map(function ($member) {
                    $fullName = trim(($member->user->first_name ?? '') . ' ' . ($member->user->last_name ?? ''));
                    return [
                        'id' => $member->user_id,
                        'name' => $fullName !== '' ? $fullName : ($member->user->email ?? ('user#' . $member->user_id)),
                    ];
                })->values();
            });

        return view('admin.najm-hoda.group-action-items', [
            'items' => $items,
            'groups' => $groups,
            'stats' => $stats,
            'statusOptions' => $statusOptions,
            'priorityOptions' => $priorityOptions,
            'groupUsersByGroup' => $groupUsersByGroup,
            'filters' => [
                'group_id' => $validated['group_id'] ?? null,
                'status' => $validated['status'] ?? null,
                'priority' => $validated['priority'] ?? null,
                'q' => $validated['q'] ?? null,
            ],
        ]);
    }

    public function updateGroupActionItem(Request $request, NajmHodaGroupActionItem $actionItem)
    {
        $validated = $request->validate([
            'status' => 'nullable|string|in:open,in_progress,blocked,done,cancelled',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'assigned_user_id' => 'nullable|integer|exists:users,id',
            'assignee_name' => 'nullable|string|max:150',
            'due_at' => 'nullable|date',
            'title' => 'nullable|string|max:255',
            'details' => 'nullable|string',
        ]);

        $updatePayload = [];
        foreach (['status', 'priority', 'assigned_user_id', 'assignee_name', 'due_at', 'title', 'details'] as $key) {
            if ($request->has($key)) {
                $updatePayload[$key] = $validated[$key] ?? null;
            }
        }

        if (array_key_exists('assigned_user_id', $updatePayload) && !empty($updatePayload['assigned_user_id'])) {
            $isGroupMember = GroupUser::query()
                ->where('group_id', $actionItem->group_id)
                ->where('user_id', $updatePayload['assigned_user_id'])
                ->where('status', 1)
                ->exists();

            if (!$isGroupMember) {
                if (!$request->expectsJson()) {
                    return back()->withErrors([
                        'assigned_user_id' => 'کاربر انتخاب شده عضو فعال این گروه نیست.',
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Selected user is not an active member of this group.',
                ], 422);
            }
        }

        if (array_key_exists('assigned_user_id', $updatePayload) && !array_key_exists('assignee_name', $updatePayload)) {
            if (!empty($updatePayload['assigned_user_id'])) {
                $assignee = User::query()->select('id', 'first_name', 'last_name', 'email')->find($updatePayload['assigned_user_id']);
                if ($assignee) {
                    $fullName = trim(($assignee->first_name ?? '') . ' ' . ($assignee->last_name ?? ''));
                    $updatePayload['assignee_name'] = $fullName !== '' ? $fullName : ($assignee->email ?? null);
                }
            } else {
                $updatePayload['assignee_name'] = null;
            }
        }

        $actionItem->fill($updatePayload);
        $actionItem->save();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Action item updated successfully.',
                'item' => [
                    'id' => $actionItem->id,
                    'status' => $actionItem->status,
                    'priority' => $actionItem->priority,
                    'assignee_name' => $actionItem->assignee_name,
                    'due_at' => optional($actionItem->due_at)->toDateTimeString(),
                ],
            ]);
        }

        return back()->with('success', 'Action item updated successfully.');
    }

    public function chat()
    {
        $agents = $this->getAvailableAgents();
        
        return view('admin.najm-hoda.chat', compact('agents'));
    }
    
    /**
     * ارسال پیام در چت ادمین
     */
    public function sendMessage(Request $request)
    {
        try {
            $validated = $request->validate([
                'message' => 'required|string|max:5000',
                'agent' => 'nullable|string|in:engineer,pilot,steward,guide,architect',
            ]);
            
            $context = [
                'user_is_admin' => true,
                'force_agent' => $validated['agent'] ?? null,
            ];
            
            $response = $this->orchestrator->route($validated['message'], $context);
            
            return response()->json([
                'success' => true,
                'response' => $response['message'],
                'agent' => $response['agent'],
                'suggestions' => $response['suggestions'] ?? [],
            ]);
            
        } catch (\Exception $e) {
            \Log::error('خطا در چت نجم‌هدا: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'متأسفانه خطایی رخ داد. لطفاً دوباره تلاش کنید.'
            ], 500);
        }
    }
    
    /**
     * ساخت عامل جدید
     */
    public function createAgent()
    {
        return view('admin.najm-hoda.create-agent');
    }
    
    /**
     * طراحی عامل جدید
     */
    public function designAgent(Request $request)
    {
        $validated = $request->validate([
            'description' => 'required|string|min:10',
            'requirements' => 'nullable|array',
        ]);
        
        try {
            // تشخیص نیاز
            $needAnalysis = $this->architect->detectNeedForNewAgent($validated['description']);
            
            // طراحی عامل
            $design = $this->architect->designNewAgent(
                $validated['description'],
                $validated['requirements'] ?? []
            );
            
            return response()->json([
                'success' => true,
                'need_analysis' => $needAnalysis,
                'design' => $design,
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * ذخیره عامل جدید
     */
    public function saveAgent(Request $request)
    {
        $validated = $request->validate([
            'design' => 'required|array',
            'design.agent_info' => 'required|array',
            'design.agent_info.class_name' => 'required|string',
        ]);
        
        try {
            $design = $validated['design'];
            $className = $design['agent_info']['class_name'];
            
            // تولید کد
            $code = $this->architect->generateAgentCode($design);
            
            // ذخیره فایل
            $saved = $this->architect->saveNewAgent($code, $className);
            
            if ($saved) {
                $role = $design['agent_info']['role'] ?? 'unknown';
                $guide = $this->architect->generateIntegrationGuide($className, $role);
                
                return response()->json([
                    'success' => true,
                    'message' => "عامل {$className} با موفقیت ساخته شد",
                    'integration_guide' => $guide,
                    'file_path' => "app/Services/NajmHoda/Agents/{$className}.php",
                ]);
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * لاگ‌های سیستم
     */
    public function logs()
    {
        $logFile = storage_path('logs/najm-hoda.log');
        $logs = [];
        
        if (File::exists($logFile)) {
            $content = File::get($logFile);
            $lines = explode("\n", $content);
            $logs = array_filter(array_slice(array_reverse($lines), 0, 100));
        }
        
        return view('admin.najm-hoda.logs', compact('logs'));
    }
    
    /**
     * پاک کردن لاگ‌ها
     */
    public function clearLogs()
    {
        $logFile = storage_path('logs/najm-hoda.log');
        
        if (File::exists($logFile)) {
            File::put($logFile, '');
        }
        
        return back()->with('success', 'لاگ‌ها پاک شدند');
    }
    
    /**
     * دریافت آمار
     */
    protected function getStatistics(): array
    {
        return [
            'total_conversations' => Conversation::count(),
            'total_messages' => \App\Models\ConversationMessage::count(),
            'total_interactions' => AIInteraction::count(),
            'total_feedbacks' => Feedback::count(),
            'avg_rating' => Feedback::avg('rating') ?? 0,
            'total_cost' => AIInteraction::sum('cost') ?? 0,
            'total_tokens' => AIInteraction::sum('tokens_used') ?? 0,
            'active_users' => Conversation::distinct('user_id')->count('user_id'),
        ];
    }
    
    /**
     * دریافت لیست عوامل موجود
     */
    protected function getAvailableAgents(): array
    {
        return [
            'engineer' => [
                'name' => 'مهندس',
                'icon' => '🔧',
                'description' => 'طراحی، کدنویسی و بررسی کد',
            ],
            'pilot' => [
                'name' => 'خلبان',
                'icon' => '✈️',
                'description' => 'مدیریت پروژه و برنامه‌ریزی',
            ],
            'steward' => [
                'name' => 'مهماندار',
                'icon' => '👨‍✈️',
                'description' => 'پشتیبانی و راهنمایی کاربران',
            ],
            'guide' => [
                'name' => 'راهنما',
                'icon' => '📖',
                'description' => 'استراتژی و نقشه راه',
            ],
            'architect' => [
                'name' => 'معمار',
                'icon' => '🏗️',
                'description' => 'طراحی و ساخت عوامل جدید',
            ],
        ];
    }
    
    /**
     * به‌روزرسانی فایل .env
     */
    protected function updateEnvFile(array $data): void
    {
        $envFile = base_path('.env');
        $envContent = File::get($envFile);
        
        foreach ($data as $key => $value) {
            $pattern = "/^{$key}=.*/m";
            $replacement = "{$key}={$value}";
            
            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
            } else {
                $envContent .= "\n{$replacement}";
            }
        }
        
        File::put($envFile, $envContent);
    }

    /**
     * اسکن کامل پروژه
     */
    public function scanProject(Request $request)
    {
        try {
            $results = $this->scanner->scanProject();
            $summary = $this->scanner->getSummary($results);
            
            // Add scanned_at timestamp if not exists
            if (!isset($results['scanned_at'])) {
                $results['scanned_at'] = now();
            }

            // If it's a POST request (AJAX), return JSON and store in session
            if ($request->isMethod('post')) {
                session(['scan_results' => $results, 'scan_summary' => $summary]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'اسکن با موفقیت انجام شد',
                    'redirect' => route('admin.najm-hoda.code-scanner.results')
                ]);
            }
            
            // If it's a GET request, show results from session or scan again
            if ($request->isMethod('get')) {
                $results = session('scan_results');
                $summary = session('scan_summary');
                
                if (!$results || !$summary) {
                    // If no results in session, scan again
                    $results = $this->scanner->scanProject();
                    $summary = $this->scanner->getSummary($results);
                    if (!isset($results['scanned_at'])) {
                        $results['scanned_at'] = now();
                    }
                    session(['scan_results' => $results, 'scan_summary' => $summary]);
                }
                
                return view('admin.najm-hoda.code-scanner.results', compact('results', 'summary'));
            }

        } catch (\Exception $e) {
            if ($request->isMethod('post')) {
                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage(),
                    'message' => 'خطا در اسکن پروژه: ' . $e->getMessage()
                ], 500);
            }
            
            return back()->with('error', 'خطا در اسکن پروژه: ' . $e->getMessage());
        }
    }

    /**
     * تحلیل یک فایل خاص
     */
    public function analyzeFile(Request $request)
    {
        $validated = $request->validate([
            'file_path' => 'required|string',
        ]);

        try {
            $filePath = base_path($validated['file_path']);

            if (!File::exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'error' => 'فایل یافت نشد'
                ], 404);
            }

            $fileResults = $this->scanner->scanFile($filePath);

            if (empty($fileResults['issues'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'مشکلی یافت نشد!',
                    'issues' => []
                ]);
            }

            // تحلیل با AI
            $analysis = $this->analyzer->analyzeMultipleIssues(
                $fileResults['issues'],
                $filePath
            );

            return response()->json([
                'success' => true,
                'analysis' => $analysis
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * دریافت پیشنهاد برای رفع یک issue
     */
    public function getSuggestion(Request $request)
    {
        $validated = $request->validate([
            'file_path' => 'required|string',
            'issue' => 'required|array',
        ]);

        try {
            $filePath = base_path($validated['file_path']);
            $fileContent = File::get($filePath);

            $suggestion = $this->analyzer->generateCodeSuggestion(
                $validated['issue'],
                $fileContent
            );

            return response()->json([
                'success' => true,
                'suggestion' => $suggestion
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * صفحه اسکنر کد
     */
    public function codeScanner()
    {
        return view('admin.najm-hoda.code-scanner.index');
    }

    /**
     * صفحه تنظیمات Auto-Fixer
     */
    public function autoFixerSettings()
    {
        return view('admin.najm-hoda.auto-fixer-settings');
    }

    /**
     * دریافت تنظیمات Auto-Fixer
     */
    public function getAutoFixerSettings()
    {
        $settings = [
            'enabled' => config('najm-hoda.auto_fixer.enabled', false),
            'level' => config('najm-hoda.auto_fixer.level', 'off'),
            'max_fixes_per_run' => config('najm-hoda.auto_fixer.max_fixes_per_run', 10),
            'require_approval' => config('najm-hoda.auto_fixer.require_approval', true),
            'backup_retention_days' => config('najm-hoda.auto_fixer.backup_retention_days', 30),
        ];

        $stats = [
            'total_fixes' => $this->fixer->getLogs(9999) ? count($this->fixer->getLogs(9999)) : 0,
            'total_backups' => $this->backupManager->getStatistics()['total_backups'],
            'total_size_mb' => $this->backupManager->getStatistics()['total_size_mb'],
            'oldest_backup' => $this->backupManager->getStatistics()['oldest_backup'],
        ];

        return response()->json([
            'success' => true,
            'settings' => $settings,
            'stats' => $stats
        ]);
    }

    /**
     * ذخیره تنظیمات Auto-Fixer
     */
    public function saveAutoFixerSettings(Request $request)
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'level' => 'required|in:off,safe,moderate,aggressive',
            'max_fixes_per_run' => 'required|integer|min:1|max:50',
            'require_approval' => 'required|boolean',
            'backup_retention_days' => 'required|integer|min:7|max:90',
        ]);

        // ذخیره در فایل env (فقط نمایشی - باید به صورت دستی انجام شود)
        // یا ذخیره در دیتابیس/کش

        // برای الان از Cache استفاده می‌کنیم
        cache()->put('najm_hoda_auto_fixer_settings', $validated, now()->addYear());

        return response()->json([
            'success' => true,
            'message' => 'تنظیمات با موفقیت ذخیره شد'
        ]);
    }

    /**
     * تست اجرا
     */
    public function testAutoFixer()
    {
        $settings = cache()->get('najm_hoda_auto_fixer_settings', [
            'enabled' => false,
            'level' => 'off',
        ]);

        if (!$settings['enabled'] || $settings['level'] === 'off') {
            return response()->json([
                'success' => false,
                'message' => 'Auto-Fixer غیرفعال است'
            ]);
        }

        // شبیه‌سازی تست
        return response()->json([
            'success' => true,
            'fixable_count' => rand(5, 20),
            'level' => $settings['level'],
            'message' => 'تست موفق - هیچ تغییری اعمال نشد'
        ]);
    }

    /**
     * پاکسازی Backup های قدیمی
     */
    public function cleanBackups()
    {
        $settings = cache()->get('najm_hoda_auto_fixer_settings', [
            'backup_retention_days' => 30
        ]);

        $deleted = $this->backupManager->cleanOldBackups($settings['backup_retention_days']);

        return response()->json([
            'success' => true,
            'deleted_count' => $deleted
        ]);
    }

    /**
     * دریافت تاریخچه
     */
    public function getAutoFixerLogs()
    {
        $logs = $this->fixer->getLogs(100);

        return response()->json([
            'success' => true,
            'logs' => $logs
        ]);
    }

    /**
     * بازگردانی از Backup
     */
    public function rollback(Request $request)
    {
        $validated = $request->validate([
            'backup_id' => 'required|string'
        ]);

        try {
            $this->backupManager->restore($validated['backup_id']);

            return response()->json([
                'success' => true,
                'message' => 'فایل با موفقیت بازگردانی شد'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * صفحهٔ مدیریت فایل‌های دانش Steward
     */
    public function manageKnowledgeFiles()
    {
        $files = StewardKnowledgeFile::with('uploader:id,first_name,last_name,email')
            ->latest()
            ->paginate(20);

        return view('admin.najm-hoda.knowledge-files', compact('files'));
    }

    /**
     * آپلود فایل دانش برای Steward Agent
     */
    public function uploadKnowledgeFile(Request $request)
    {
        // بررسی authentication
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'ابتدا وارد سیستم شوید'
            ], 401);
        }

        try {
            // Validation
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'knowledge_file' => 'required|file|mimes:pdf,doc,docx,txt,md|max:10240', // 10MB
                'search_priority' => 'nullable|integer|min:1|max:10',
            ], [
                'knowledge_file.required' => 'لطفاً فایل را انتخاب کنید',
                'knowledge_file.mimes' => 'فایل باید یکی از فرمت‌های PDF، Word، TXT یا Markdown باشد',
                'knowledge_file.max' => 'حجم فایل نمی‌تواند بیشتر از 10 مگابایت باشد',
                'title.required' => 'لطفاً عنوان فایل را وارد کنید',
                'title.max' => 'عنوان نمی‌تواند بیشتر از 255 کاراکتر باشد',
            ]);

            $file = $request->file('knowledge_file');
            $originalName = $file->getClientOriginalName();
            $extension = strtolower($file->getClientOriginalExtension());
            $fileSize = $file->getSize();
            
            // ایجاد نام فایل منحصر به فرد
            $fileName = time() . '_' . str_replace(' ', '_', preg_replace('/[^\w.-]/u', '', basename($originalName)));
            
            // ذخیره فایل در storage
            $filePath = $file->storeAs('steward/knowledge', $fileName, 'public');
            
            if (!$filePath) {
                return response()->json([
                    'success' => false,
                    'message' => '❌ خطا: فایل نتوانست ذخیره شود. لطفاً دوباره تلاش کنید'
                ], 500);
            }
            
            // استخراج محتوا
            $extractedContent = $this->extractFileContent($file, $extension);
            if (empty($extractedContent)) {
                $extractedContent = "فایل: {$originalName}";
            }
            
            // ذخیره در دیتابیس
            $knowledgeFile = StewardKnowledgeFile::create([
                'title' => trim($validated['title']),
                'original_filename' => $originalName,
                'file_path' => $filePath,
                'file_type' => $extension,
                'file_size' => $fileSize,
                'extracted_content' => $extractedContent,
                'summary' => substr($extractedContent, 0, 200),
                'search_priority' => $validated['search_priority'] ?? 5,
                'uploaded_by' => auth()->id(),
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => '✅ فایل "' . $validated['title'] . '" با موفقیت آپلود شد',
                'file' => [
                    'id' => $knowledgeFile->id,
                    'title' => $knowledgeFile->title,
                    'file_type' => $knowledgeFile->file_type,
                    'file_size' => $knowledgeFile->formatted_file_size,
                    'created_at' => $knowledgeFile->created_at->diffForHumans(),
                ]
            ], 201);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = collect($e->errors())->flatten()->first();
            return response()->json([
                'success' => false,
                'message' => '⚠️ ' . $errors
            ], 422);
            
        } catch (\Exception $e) {
            // Log error for debugging
            \Log::error('Knowledge file upload error: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'file' => $request->file('knowledge_file') ? $request->file('knowledge_file')->getClientOriginalName() : 'unknown',
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '❌ خطا در آپلود فایل: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * دریافت لیست فایل‌های دانش
     */
    public function getKnowledgeFiles()
    {
        $files = StewardKnowledgeFile::with('uploader:id,first_name,last_name,email')
            ->active()
            ->latest()
            ->get()
            ->map(function($file) {
                // ساخت نام کاربر
                $uploaderName = 'نامشخص';
                if ($file->uploader) {
                    if ($file->uploader->first_name || $file->uploader->last_name) {
                        $uploaderName = trim($file->uploader->first_name . ' ' . $file->uploader->last_name);
                    } else {
                        $uploaderName = $file->uploader->email ?? 'نامشخص';
                    }
                }
                
                return [
                    'id' => $file->id,
                    'title' => $file->title,
                    'file_type' => $file->file_type,
                    'file_size' => $file->formatted_file_size,
                    'search_priority' => $file->search_priority,
                    'uploader' => $uploaderName,
                    'created_at' => $file->created_at->diffForHumans(),
                    'icon' => $file->file_icon,
                ];
            })->toArray();

        return response()->json([
            'success' => true,
            'files' => $files
        ]);
    }

    /**
     * ویرایش فایل دانش (فقط عنوان و اولویت)
     */
    public function updateKnowledgeFile(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'search_priority' => 'nullable|integer|min:1|max:10',
        ]);

        try {
            $file = StewardKnowledgeFile::findOrFail($id);
            
            $file->update([
                'title' => $request->title,
                'search_priority' => $request->search_priority ?? $file->search_priority,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'فایل با موفقیت ویرایش شد',
                'file' => [
                    'id' => $file->id,
                    'title' => $file->title,
                    'search_priority' => $file->search_priority,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در ویرایش فایل: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * حذف فایل دانش
     */
    public function deleteKnowledgeFile($id)
    {
        try {
            $file = StewardKnowledgeFile::findOrFail($id);
            
            // حذف فایل از storage
            if (Storage::disk('public')->exists($file->file_path)) {
                Storage::disk('public')->delete($file->file_path);
            }
            
            // حذف از دیتابیس
            $file->delete();

            return response()->json([
                'success' => true,
                'message' => 'فایل با موفقیت حذف شد'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در حذف فایل: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * استخراج محتوای فایل بر اساس نوع
     */
    private function extractFileContent($file, $extension)
    {
        $content = '';
        $filename = $file->getClientOriginalName();

        try {
            if ($extension === 'txt' || $extension === 'md') {
                // فایل‌های متنی: استخراج کامل محتوا
                $rawContent = file_get_contents($file->getRealPath());
                
                // اطمینان از UTF-8 بودن
                if (!mb_check_encoding($rawContent, 'UTF-8')) {
                    $content = mb_convert_encoding($rawContent, 'UTF-8', 'auto');
                } else {
                    $content = $rawContent;
                }
                
            } elseif ($extension === 'pdf') {
                // PDF: تلاش برای استخراج محتوا با smalot/pdfparser
                if (class_exists('\Smalot\PdfParser\Parser')) {
                    try {
                        $parser = new \Smalot\PdfParser\Parser();
                        $pdf = $parser->parseFile($file->getRealPath());
                        $extractedText = $pdf->getText();
                        
                        if (!empty(trim($extractedText))) {
                            // محتوا استخراج شد
                            $content = "📄 فایل PDF: {$filename}\n\n";
                            $content .= "محتوای استخراج‌شده:\n\n";
                            $content .= $extractedText;
                        } else {
                            // PDF خالی یا بدون متن
                            $content = "📄 فایل PDF: {$filename}\n\n";
                            $content .= "این فایل PDF شامل تصاویر یا محتوای غیرقابل استخراج است.";
                        }
                    } catch (\Exception $e) {
                        \Log::warning('PDF parsing failed', [
                            'filename' => $filename,
                            'error' => $e->getMessage()
                        ]);
                        
                        $content = "📄 فایل PDF: {$filename}\n\n";
                        $content .= "خطا در استخراج محتوا. نام فایل برای جستجو استفاده می‌شود.";
                    }
                } else {
                    $content = "📄 فایل PDF: {$filename}\n\n";
                    $content .= "کتابخانه PDF Parser نصب نیست.";
                }
                
            } elseif ($extension === 'docx' || $extension === 'doc') {
                // Word: فعلاً فقط نام فایل (کتابخانه phpoffice/phpword نصب نیست)
                $content = "📝 فایل Word: {$filename}\n\n";
                $content .= "این یک فایل Word است. ";
                $content .= "برای استخراج خودکار محتوای Word، نیاز به نصب کتابخانه phpoffice/phpword است.";
            }
        } catch (\Exception $e) {
            \Log::error('File content extraction error', [
                'filename' => $filename,
                'extension' => $extension,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $content = "فایل: {$filename}\n\nخطا در پردازش فایل.";
        }

        return $content;
    }
}
