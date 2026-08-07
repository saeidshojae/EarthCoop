<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\NajmHoda\NajmHodaOrchestrator;
use App\Services\NajmHoda\Agents\EngineerAgent;
use App\Services\NajmHoda\Agents\PilotAgent;
use App\Services\NajmHoda\Agents\StewardAgent;
use App\Services\NajmHoda\Agents\GuideAgent;
use App\Services\NajmHoda\Agents\ArchitectAgent;
use App\Services\NajmHoda\Runtime\DatabaseRuntimeEventBus;
use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaAdaptivePolicyService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyAuditService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyControlService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomySafetyGate;
use App\Services\NajmHoda\Runtime\NajmHodaCapabilityRegistry;
use App\Services\NajmHoda\Runtime\NajmHodaCompensatingTransactionService;
use App\Services\NajmHoda\Runtime\NajmHodaContinuousEvaluationService;
use App\Services\NajmHoda\Runtime\NajmHodaCostLedgerService;
use App\Services\NajmHoda\Runtime\NajmHodaCrossModuleCapabilityOrchestratorService;
use App\Services\NajmHoda\Runtime\NajmHodaDelegatedPermissionService;
use App\Services\NajmHoda\Runtime\NajmHodaEntryPolicy;
use App\Services\NajmHoda\Runtime\NajmHodaExecutionService;
use App\Services\NajmHoda\Runtime\NajmHodaMultiHorizonGoalEngineService;
use App\Services\NajmHoda\Runtime\NajmHodaMultiHorizonGoalReviewService;
use App\Services\NajmHoda\Runtime\NajmHodaOperatorActionExecutorV2;
use App\Services\NajmHoda\Runtime\NajmHodaOversightConsoleService;
use App\Services\NajmHoda\Runtime\NajmHodaPolicyGate;
use App\Services\NajmHoda\Runtime\NajmHodaProductionReadinessService;
use App\Services\NajmHoda\Runtime\NajmHodaShadowRolloutService;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use App\Services\NajmHoda\Runtime\GroupActionExecutor;
use App\Services\NajmHoda\NajmHodaInteractionBoundaryService;

class NajmHodaServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(EngineerAgent::class, fn ($app) => new EngineerAgent());
        $this->app->singleton(PilotAgent::class, fn ($app) => new PilotAgent());
        $this->app->singleton(StewardAgent::class, fn ($app) => new StewardAgent());
        $this->app->singleton(GuideAgent::class, fn ($app) => new GuideAgent());
        $this->app->singleton(ArchitectAgent::class, fn ($app) => new ArchitectAgent());

        $this->app->singleton(NajmHodaOrchestrator::class, function ($app) {
            return new NajmHodaOrchestrator(
                $app->make(EngineerAgent::class),
                $app->make(PilotAgent::class),
                $app->make(StewardAgent::class),
                $app->make(GuideAgent::class),
                $app->make(ArchitectAgent::class)
            );
        });

        $this->app->singleton(RuntimeEventBus::class, function ($app) {
            $driver = (string) config('najm-hoda.runtime.observability.event_bus', 'database');
            if ($driver === 'memory') {
                return new InMemoryRuntimeEventBus();
            }

            $maxEvents = (int) config('najm-hoda.runtime.observability.database.max_events', 5000);
            $retentionDays = (int) config('najm-hoda.runtime.observability.database.retention_days', 30);
            $pruneInterval = (int) config('najm-hoda.runtime.observability.database.prune_interval', 100);

            return new DatabaseRuntimeEventBus($maxEvents, $retentionDays, $pruneInterval);
        });

        $this->app->singleton(NajmHodaPolicyGate::class, fn ($app) => new NajmHodaPolicyGate());
        $this->app->singleton(GroupActionExecutor::class, fn ($app) => new GroupActionExecutor());
        $this->app->singleton(NajmHodaEntryPolicy::class, fn ($app) => new NajmHodaEntryPolicy());

        $this->app->singleton(NajmHodaExecutionService::class, function ($app) {
            return new NajmHodaExecutionService(
                $app->make(NajmHodaInteractionBoundaryService::class),
                $app->make(NajmHodaCrossModuleCapabilityOrchestratorService::class)
            );
        });

        $this->app->singleton(NajmHodaCapabilityRegistry::class, function ($app) {
            return new NajmHodaCapabilityRegistry($app->make(RuntimeEventBus::class));
        });

        $this->app->singleton(NajmHodaAutonomySafetyGate::class, function ($app) {
            return new NajmHodaAutonomySafetyGate($app->make(RuntimeEventBus::class));
        });

        $this->app->singleton(NajmHodaAutonomyControlService::class, function ($app) {
            return new NajmHodaAutonomyControlService($app->make(RuntimeEventBus::class));
        });

        $this->app->singleton(NajmHodaAutonomyApprovalService::class, function ($app) {
            return new NajmHodaAutonomyApprovalService($app->make(RuntimeEventBus::class));
        });

        $this->app->singleton(NajmHodaDelegatedPermissionService::class, function ($app) {
            return new NajmHodaDelegatedPermissionService($app->make(RuntimeEventBus::class));
        });

        $this->app->singleton(NajmHodaCostLedgerService::class, function ($app) {
            return new NajmHodaCostLedgerService($app->make(RuntimeEventBus::class));
        });

        $this->app->singleton(NajmHodaOperatorActionExecutorV2::class, function ($app) {
            return new NajmHodaOperatorActionExecutorV2(
                $app->make(RuntimeEventBus::class),
                $app->make(NajmHodaAutonomyControlService::class),
                $app->make(NajmHodaCostLedgerService::class)
            );
        });

        $this->app->singleton(NajmHodaCompensatingTransactionService::class, function ($app) {
            return new NajmHodaCompensatingTransactionService(
                $app->make(RuntimeEventBus::class),
                $app->make(NajmHodaCapabilityRegistry::class),
                $app->make(NajmHodaOperatorActionExecutorV2::class)
            );
        });

        $this->app->singleton(NajmHodaCrossModuleCapabilityOrchestratorService::class, function ($app) {
            return new NajmHodaCrossModuleCapabilityOrchestratorService(
                $app->make(RuntimeEventBus::class),
                $app->make(NajmHodaCapabilityRegistry::class),
                $app->make(NajmHodaAutonomySafetyGate::class),
                $app->make(NajmHodaDelegatedPermissionService::class),
                $app->make(NajmHodaAutonomyApprovalService::class),
                $app->make(NajmHodaOperatorActionExecutorV2::class),
                $app->make(NajmHodaCompensatingTransactionService::class)
            );
        });

        $this->app->singleton(NajmHodaAdaptivePolicyService::class, function ($app) {
            return new NajmHodaAdaptivePolicyService($app->make(RuntimeEventBus::class));
        });

        $this->app->singleton(NajmHodaAutonomyAuditService::class, function ($app) {
            return new NajmHodaAutonomyAuditService($app->make(RuntimeEventBus::class));
        });

        $this->app->singleton(NajmHodaContinuousEvaluationService::class, function ($app) {
            return new NajmHodaContinuousEvaluationService($app->make(RuntimeEventBus::class));
        });

        $this->app->singleton(NajmHodaOversightConsoleService::class, function ($app) {
            return new NajmHodaOversightConsoleService($app->make(RuntimeEventBus::class));
        });

        $this->app->singleton(NajmHodaShadowRolloutService::class, function ($app) {
            return new NajmHodaShadowRolloutService($app->make(RuntimeEventBus::class));
        });

        $this->app->singleton(NajmHodaMultiHorizonGoalReviewService::class, function ($app) {
            return new NajmHodaMultiHorizonGoalReviewService($app->make(RuntimeEventBus::class));
        });

        $this->app->singleton(NajmHodaMultiHorizonGoalEngineService::class, function ($app) {
            return new NajmHodaMultiHorizonGoalEngineService(
                $app->make(RuntimeEventBus::class),
                $app->make(NajmHodaMultiHorizonGoalReviewService::class)
            );
        });

        $this->app->singleton(NajmHodaProductionReadinessService::class, function ($app) {
            return $app->make(NajmHodaProductionReadinessService::class);
        });
    }
}
