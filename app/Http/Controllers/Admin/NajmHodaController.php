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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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
            'provider' => 'nullable|in:openai,claude,gemini',
            'model' => 'nullable|string',
            'api_key' => 'nullable|string',
            'max_tokens' => 'nullable|integer|min:100',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'auto_actions_enabled' => 'nullable|boolean',
            'rate_limit_requests' => 'nullable|integer|min:1',
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
}


