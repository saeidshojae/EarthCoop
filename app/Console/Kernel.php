<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\BackfillProfileReputation::class,
        \App\Console\Commands\CreateContactPage::class,
        \App\Console\Commands\CreateFaqPage::class,
        \App\Console\Commands\CleanupNajmBaharOrphans::class,
        \App\Console\Commands\FundWalletsCommand::class,
        \App\Console\Commands\NajmBaharDistributeMembershipBalance::class,
        \App\Console\Commands\NajmBaharProcessScheduled::class,
        \App\Console\Commands\NajmBaharRecalculateBalances::class,
        \App\Console\Commands\NajmBaharRunSalaries::class,
        \App\Console\Commands\NajmBaharSyncSubAccountBalances::class,
        \App\Console\Commands\NajmHodaBootstrapGroups::class,
        \App\Console\Commands\NajmHodaCoverageHeartbeat::class,
        \App\Console\Commands\NajmHodaCodeOpsCanary::class,
        \App\Console\Commands\NajmHodaContinuousEvaluation::class,
        \App\Console\Commands\NajmHodaCoverageKpi::class,
        \App\Console\Commands\NajmHodaCoverageProbe::class,
        \App\Console\Commands\NajmHodaDelegationAudit::class,
        \App\Console\Commands\NajmHodaDelegationGrant::class,
        \App\Console\Commands\NajmHodaFounderOpsTick::class,
        \App\Console\Commands\NajmHodaGameDay::class,
        \App\Console\Commands\NajmHodaGraphQuery::class,
        \App\Console\Commands\NajmHodaGoalLoop::class,
        \App\Console\Commands\NajmHodaMultiHorizonGoals::class,
        \App\Console\Commands\NajmHodaMultiHorizonGoalsReview::class,
        \App\Console\Commands\NajmHodaOpsActivation::class,
        \App\Console\Commands\NajmHodaOpsMonitor::class,
        \App\Console\Commands\NajmHodaOversightConsole::class,
        \App\Console\Commands\NajmHodaOrchestrate::class,
        \App\Console\Commands\NajmHodaOnboardingAudit::class,
        \App\Console\Commands\NajmHodaPhaseSixSignoff::class,
        \App\Console\Commands\NajmHodaPolicyLearningLoop::class,
        \App\Console\Commands\NajmHodaShadowRollout::class,
        \App\Console\Commands\NajmHodaModerationSweep::class,
        \App\Console\Commands\NajmHodaGroupAttentionSweep::class,
        \App\Console\Commands\ProcessElectionLifecycle::class,
        \App\Console\Commands\SendElectionReminders::class,
        \App\Console\Commands\SendAuctionReminders::class,
        \App\Console\Commands\ActivateScheduledGroupSessions::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        $schedule->command('auctions:close')->everyMinute();
        $schedule->command('group-chat:dispatch-outbox --limit=500')->everyMinute()->withoutOverlapping();
        $schedule->command('group-chat:activate-sessions')->everyMinute()->withoutOverlapping();
        $schedule->command('elections:process-lifecycle --limit=500 --fail-on-error')
            ->everyMinute()
            ->withoutOverlapping();
        $schedule->command('elections:send-reminders')->twiceDaily(0, 12);
        $schedule->command('auctions:send-reminders')->hourly();
        $schedule->command('najm-hoda:moderation-sweep --max-groups=200')->hourly();
        $schedule->command('najm-hoda:group-attention-sweep --max-groups=200')
            ->everyFiveMinutes()
            ->withoutOverlapping();
        $schedule->command('najm-hoda:ops-monitor')->everyFiveMinutes();

        // Founder Operations unified autonomous management loop. The command is
        // fail-closed: it executes only delegated-ready actions with explicit
        // canonical low-risk handlers; all other work remains plan/approval only.
        $schedule->command('najm-hoda:founder-ops-tick --hours=24 --limit=12')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        $schedule->command('najm-hoda:goal-loop')->everyTenMinutes();
        $schedule->command('najm-hoda:multi-goals --scope=global --window=24 --limit=2000')->everyThirtyMinutes();
        $schedule->command('najm-hoda:multi-goals-review --scope=global --window=24 --limit=2000')->hourly();
        $schedule->command('najm-hoda:orchestrate --from-multi-goals --goal=stabilize_operations')->hourly();
        $schedule->command('najm-hoda:oversight-console --limit=80')->hourly();
        $schedule->command('najm-hoda:policy-learning-loop --window=24 --apply')->everyTwoHours();
        $schedule->command('najm-hoda:codeops-canary --evaluate --auto-rollback')->everyThirtyMinutes();
        $schedule->command('najm-hoda:continuous-evaluation --window=24 --fail-on-breach')->dailyAt('03:30');
        $schedule->command('najm-hoda:ops-activation --tick')->everyTenMinutes();
        $schedule->command('najm-hoda:shadow-rollout --evaluate --window=24')->hourly();
        $schedule->command('najm-hoda:phase6-signoff --report --window=24')->dailyAt('04:00');
        $schedule->command('najm-hoda:coverage-heartbeat')->hourly();
        $schedule->command('najm-hoda:coverage-probe')->hourly();
        $schedule->command('najm-hoda:coverage-kpi --window=24 --limit=5000')->hourly();
        $schedule->command('najm-hoda:coverage-kpi --window=24 --limit=5000 --heartbeat --require-sustained')->hourly();
        $schedule->command('najm-hoda:coverage-kpi --window=24 --limit=5000 --require-sustained')->dailyAt('02:00');
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
