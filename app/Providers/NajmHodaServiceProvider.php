<?php

namespace App\Providers;

use App\Services\NajmHoda\NajmHodaOrchestrator;
use App\Services\NajmHoda\Agents\EngineerAgent;
use App\Services\NajmHoda\Agents\PilotAgent;
use App\Services\NajmHoda\Agents\StewardAgent;
use App\Services\NajmHoda\Agents\GuideAgent;
use App\Services\NajmHoda\Agents\ArchitectAgent;
use App\Services\NajmHoda\MockModeService;
use App\Services\NajmHoda\Runtime\DatabaseRuntimeEventBus;
use App\Services\NajmHoda\Runtime\GroupActionExecutor;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomousGoalLoopService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyAuditService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyControlService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyCostLedgerService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomySafetyGate;
use App\Services\NajmHoda\Runtime\NajmHodaCapabilityRegistry;
use App\Services\NajmHoda\Runtime\NajmHodaEntryPolicy;
use App\Services\NajmHoda\Runtime\NajmHodaExecutionService;
use App\Services\NajmHoda\Runtime\NajmHodaObservabilityGraphService;
use App\Services\NajmHoda\Runtime\NajmHodaOperatorActionExecutorV2;
use App\Services\NajmHoda\Runtime\NajmHodaProactiveRecommendationService;
use App\Services\NajmHoda\Runtime\NajmHodaOpsEscalationService;
use App\Services\NajmHoda\Runtime\NajmHodaOpsHealthMonitor;
use App\Services\NajmHoda\Runtime\NajmHodaOpsRetentionService;
use App\Services\NajmHoda\Runtime\NajmHodaOpsTriageService;
use App\Services\NajmHoda\Runtime\NajmHodaGovernanceKpiCatalogService;
use App\Services\NajmHoda\Runtime\NajmHodaGovernanceMetricsAggregatorService;
use App\Services\NajmHoda\Runtime\NajmHodaDecisionPolicyDriftService;
use App\Services\NajmHoda\Runtime\NajmHodaRunbookRegistryService;
use App\Services\NajmHoda\Runtime\NajmHodaGovernanceAlertingService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyGameDayService;
use App\Services\NajmHoda\Runtime\NajmHodaComplianceEvidenceService;
use App\Services\NajmHoda\Runtime\NajmHodaProductionReadinessService;
use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaPolicyGate;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use App\Services\NajmHoda\CodeScanner\CodeScannerService;
use App\Services\NajmHoda\CodeScanner\CodeAnalyzerService;
use App\Services\NotificationService;
use App\Services\TicketTriageService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;

/**
 * سرویس پرووایدر نجم‌هدا
 * 
 * مسئول ثبت و راه‌اندازی تمام سرویس‌های نجم‌هدا
 */
class NajmHodaServiceProvider extends ServiceProvider
{
    /**
     * ثبت سرویس‌ها
     */
    public function register(): void
    {
        // ثبت Singleton برای هر عامل
        $this->app->singleton(EngineerAgent::class, function ($app) {
            return new EngineerAgent();
        });

        $this->app->singleton(PilotAgent::class, function ($app) {
            return new PilotAgent();
        });

        $this->app->singleton(StewardAgent::class, function ($app) {
            return new StewardAgent();
        });

        $this->app->singleton(GuideAgent::class, function ($app) {
            return new GuideAgent();
        });

        $this->app->singleton(ArchitectAgent::class, function ($app) {
            return new ArchitectAgent();
        });

        // ثبت Mock Mode Service
        $this->app->singleton(MockModeService::class, function ($app) {
            return new MockModeService();
        });

        $this->app->singleton(RuntimeEventBus::class, function ($app) {
            $driver = (string) config('najm-hoda.runtime.event_bus.driver', 'database');
            $maxEvents = (int) config('najm-hoda.runtime.event_bus.max_events', 500);
            $retentionDays = (int) config('najm-hoda.runtime.event_bus.retention_days', 14);
            $pruneInterval = (int) config('najm-hoda.runtime.event_bus.prune_interval_seconds', 300);

            if ($driver === 'in_memory') {
                return new InMemoryRuntimeEventBus($maxEvents);
            }

            return new DatabaseRuntimeEventBus($maxEvents, $retentionDays, $pruneInterval);
        });

        $this->app->singleton(NajmHodaPolicyGate::class, function ($app) {
            return new NajmHodaPolicyGate();
        });

        $this->app->singleton(GroupActionExecutor::class, function ($app) {
            return new GroupActionExecutor();
        });

        $this->app->singleton(NajmHodaEntryPolicy::class, function ($app) {
            return new NajmHodaEntryPolicy();
        });

        $this->app->singleton(NajmHodaExecutionService::class, function ($app) {
            return new NajmHodaExecutionService();
        });

        $this->app->singleton(NajmHodaCapabilityRegistry::class, function ($app) {
            return new NajmHodaCapabilityRegistry(
                $app->make(RuntimeEventBus::class)
            );
        });

        $this->app->singleton(NajmHodaAutonomySafetyGate::class, function ($app) {
            return new NajmHodaAutonomySafetyGate(
                $app->make(RuntimeEventBus::class)
            );
        });

        $this->app->singleton(NajmHodaAutonomyApprovalService::class, function ($app) {
            return new NajmHodaAutonomyApprovalService(
                $app->make(RuntimeEventBus::class),
                $app->make(NotificationService::class)
            );
        });

        $this->app->singleton(NajmHodaAutonomyControlService::class, function ($app) {
            return new NajmHodaAutonomyControlService(
                $app->make(RuntimeEventBus::class)
            );
        });

        $this->app->singleton(NajmHodaAutonomyAuditService::class, function ($app) {
            return new NajmHodaAutonomyAuditService(
                $app->make(RuntimeEventBus::class)
            );
        });

        $this->app->singleton(NajmHodaAutonomyCostLedgerService::class, function ($app) {
            return new NajmHodaAutonomyCostLedgerService(
                $app->make(RuntimeEventBus::class)
            );
        });

        $this->app->singleton(NajmHodaObservabilityGraphService::class, function ($app) {
            return new NajmHodaObservabilityGraphService(
                $app->make(RuntimeEventBus::class)
            );
        });

        $this->app->singleton(NajmHodaProactiveRecommendationService::class, function ($app) {
            return new NajmHodaProactiveRecommendationService(
                $app->make(RuntimeEventBus::class)
            );
        });

        $this->app->singleton(NajmHodaOperatorActionExecutorV2::class, function ($app) {
            return new NajmHodaOperatorActionExecutorV2(
                $app->make(RuntimeEventBus::class),
                $app->make(NajmHodaAutonomyCostLedgerService::class),
                $app->make(NajmHodaAutonomyControlService::class)
            );
        });

        $this->app->singleton(NajmHodaAutonomousGoalLoopService::class, function ($app) {
            return new NajmHodaAutonomousGoalLoopService(
                $app->make(RuntimeEventBus::class),
                $app->make(NajmHodaObservabilityGraphService::class),
                $app->make(NajmHodaProactiveRecommendationService::class),
                $app->make(NajmHodaOperatorActionExecutorV2::class),
                $app->make(NajmHodaAutonomyControlService::class),
                $app->make(NajmHodaAutonomyAuditService::class),
                $app->make(NajmHodaCapabilityRegistry::class),
                $app->make(NajmHodaAutonomySafetyGate::class),
                $app->make(NajmHodaAutonomyApprovalService::class)
            );
        });

        $this->app->singleton(NajmHodaOpsHealthMonitor::class, function ($app) {
            return new NajmHodaOpsHealthMonitor(
                $app->make(RuntimeEventBus::class)
            );
        });

        $this->app->singleton(NajmHodaOpsTriageService::class, function ($app) {
            return new NajmHodaOpsTriageService(
                $app->make(RuntimeEventBus::class)
            );
        });

        $this->app->singleton(NajmHodaOpsEscalationService::class, function ($app) {
            return new NajmHodaOpsEscalationService(
                $app->make(RuntimeEventBus::class),
                $app->make(TicketTriageService::class),
                $app->make(NotificationService::class)
            );
        });

        $this->app->singleton(NajmHodaOpsRetentionService::class, function ($app) {
            return new NajmHodaOpsRetentionService(
                $app->make(RuntimeEventBus::class)
            );
        });

        $this->app->singleton(NajmHodaGovernanceKpiCatalogService::class, function ($app) {
            return new NajmHodaGovernanceKpiCatalogService();
        });

        $this->app->singleton(NajmHodaGovernanceMetricsAggregatorService::class, function ($app) {
            return new NajmHodaGovernanceMetricsAggregatorService(
                $app->make(RuntimeEventBus::class),
                $app->make(NajmHodaGovernanceKpiCatalogService::class)
            );
        });

        $this->app->singleton(NajmHodaDecisionPolicyDriftService::class, function ($app) {
            return new NajmHodaDecisionPolicyDriftService(
                $app->make(RuntimeEventBus::class)
            );
        });

        $this->app->singleton(NajmHodaRunbookRegistryService::class, function ($app) {
            return new NajmHodaRunbookRegistryService(
                $app->make(RuntimeEventBus::class)
            );
        });

        $this->app->singleton(NajmHodaGovernanceAlertingService::class, function ($app) {
            return new NajmHodaGovernanceAlertingService(
                $app->make(RuntimeEventBus::class),
                $app->make(NajmHodaGovernanceMetricsAggregatorService::class),
                $app->make(NajmHodaAutonomyApprovalService::class),
                $app->make(NotificationService::class)
            );
        });

        $this->app->singleton(NajmHodaAutonomyGameDayService::class, function ($app) {
            return new NajmHodaAutonomyGameDayService(
                $app->make(RuntimeEventBus::class),
                $app->make(NajmHodaAutonomyControlService::class),
                $app->make(NajmHodaAutonomousGoalLoopService::class),
                $app->make(NajmHodaAutonomyAuditService::class),
                $app->make(NajmHodaGovernanceAlertingService::class)
            );
        });

        $this->app->singleton(NajmHodaComplianceEvidenceService::class, function ($app) {
            return new NajmHodaComplianceEvidenceService(
                $app->make(NajmHodaAutonomyAuditService::class),
                $app->make(NajmHodaAutonomyApprovalService::class),
                $app->make(NajmHodaGovernanceAlertingService::class),
                $app->make(NajmHodaAutonomyGameDayService::class)
            );
        });

        $this->app->singleton(NajmHodaProductionReadinessService::class, function ($app) {
            return new NajmHodaProductionReadinessService(
                $app->make(RuntimeEventBus::class),
                $app->make(NajmHodaGovernanceMetricsAggregatorService::class),
                $app->make(NajmHodaDecisionPolicyDriftService::class),
                $app->make(NajmHodaRunbookRegistryService::class),
                $app->make(NajmHodaAutonomyApprovalService::class),
                $app->make(NajmHodaAutonomyGameDayService::class),
                $app->make(NajmHodaComplianceEvidenceService::class)
            );
        });

        // ثبت Code Scanner
        $this->app->singleton(CodeScannerService::class, function ($app) {
            return new CodeScannerService();
        });

        // ثبت Code Analyzer
        $this->app->singleton(CodeAnalyzerService::class, function ($app) {
            return new CodeAnalyzerService();
        });

        // ثبت Orchestrator
        $this->app->singleton(NajmHodaOrchestrator::class, function ($app) {
            return new NajmHodaOrchestrator();
        });
    }

    /**
     * Bootstrap سرویس‌ها
     */
    public function boot(): void
    {
        // ایجاد دایرکتوری Knowledge Base
        $this->createKnowledgeBaseDirectory();
        
        // تنظیم لاگ کانال
        $this->setupLoggingChannel();
        
        // ثبت Event Listeners
        $this->registerEventListeners();
        
        Log::info('نجم‌هدا راه‌اندازی شد');
    }

    /**
     * ایجاد دایرکتوری Knowledge Base
     */
    protected function createKnowledgeBaseDirectory(): void
    {
        $knowledgePath = config('najm-hoda.knowledge_base_path');
        
        if (!file_exists($knowledgePath)) {
            mkdir($knowledgePath, 0755, true);
            $this->createDefaultKnowledgeFiles($knowledgePath);
        }
    }

    /**
     * ایجاد فایل‌های پیش‌فرض Knowledge Base
     */
    protected function createDefaultKnowledgeFiles(string $path): void
    {
        $files = [
            'project-info.md' => $this->getProjectInfo(),
            'vision.md' => $this->getVision(),
            'values.md' => $this->getValues(),
            'user-guide.md' => $this->getUserGuide(),
        ];

        foreach ($files as $filename => $content) {
            $filePath = "$path/$filename";
            if (!file_exists($filePath)) {
                file_put_contents($filePath, $content);
            }
        }
    }

    /**
     * تنظیم کانال لاگ
     */
    protected function setupLoggingChannel(): void
    {
        config([
            'logging.channels.najm_hoda' => [
                'driver' => 'daily',
                'path' => storage_path('logs/najm-hoda.log'),
                'level' => config('najm-hoda.logging.level', 'info'),
                'days' => 14,
            ],
        ]);
    }

    /**
     * ثبت Event Listeners
     */
    protected function registerEventListeners(): void
    {
        // مثال: وقتی کاربر جدید ثبت نام می‌کند
        \Event::listen(\Illuminate\Auth\Events\Registered::class, function ($event) {
            if (config('najm-hoda.auto_actions.user_responses')) {
                try {
                    $orchestrator = app(NajmHodaOrchestrator::class);
                    // می‌توانیم پیام خوش‌آمدگویی بفرستیم
                    Log::info('کاربر جدید ثبت نام کرد', ['user_id' => $event->user->id]);
                } catch (\Exception $e) {
                    Log::error('خطا در پردازش ثبت نام: ' . $e->getMessage());
                }
            }
        });
    }

    /**
     * محتوای پیش‌فرض اطلاعات پروژه
     */
    protected function getProjectInfo(): string
    {
        return <<<MD
# پروژه NewEarthCoop (ارثکوپ)

## خلاصه
ارثکوپ یک پلتفرم تعاونی اقتصادی مبتنی بر وب است که به کاربران امکان می‌دهد:
- در حراج‌های آنلاین شرکت کنند
- سرمایه‌گذاری عادلانه انجام دهند
- کیف پول دیجیتال داشته باشند
- در تصمیمات جمعی مشارکت کنند

## تکنولوژی
- **Backend**: Laravel 11
- **Frontend**: Vue.js 3, Bootstrap 5
- **Database**: MySQL 8.0
- **Cache**: Redis
- **Language**: فارسی (RTL)

## ویژگی‌های اصلی
1. سیستم احراز هویت امن
2. حراج‌های آنلاین با پیشنهاد قیمت
3. کیف پول دیجیتال
4. مدیریت دارایی‌ها
5. انجمن و گفتگوها
6. گزارش‌گیری مالی

## اهداف
- دموکراسی اقتصادی
- شفافیت کامل
- توزیع عادلانه ثروت
- توسعه پایدار

[این فایل را با اطلاعات دقیق‌تر پروژه تکمیل کنید]
MD;
    }

    /**
     * محتوای پیش‌فرض چشم‌انداز
     */
    protected function getVision(): string
    {
        return <<<MD
# چشم‌انداز ارثکوپ

دنیایی که در آن:
- همه افراد فرصت برابر برای سرمایه‌گذاری دارند
- ثروت به صورت عادلانه توزیع می‌شود
- تصمیمات به صورت جمعی و دموکراتیک گرفته می‌شوند
- شفافیت کامل در تمام تراکنش‌ها وجود دارد
- محیط زیست و توسعه پایدار محافظت می‌شود

[این فایل را تکمیل کنید]
MD;
    }

    /**
     * محتوای پیش‌فرض ارزش‌ها
     */
    protected function getValues(): string
    {
        return <<<MD
# ارزش‌های اصلی ارثکوپ

1. **شفافیت**: همه چیز قابل رؤیت و تأیید است
2. **عدالت**: فرصت‌های برابر برای همه
3. **مشارکت**: تصمیم‌گیری جمعی
4. **نوآوری**: پذیرش تغییر و بهبود مستمر
5. **پایداری**: فکر کردن به آینده

[این فایل را تکمیل کنید]
MD;
    }

    /**
     * محتوای پیش‌فرض راهنمای کاربر
     */
    protected function getUserGuide(): string
    {
        return <<<MD
# راهنمای کاربران ارثکوپ

## شروع کار
1. ثبت نام در سیستم
2. احراز هویت
3. شارژ کیف پول
4. شرکت در حراج‌ها

## سوالات متداول
[به‌روزرسانی شود]
MD;
    }
}
