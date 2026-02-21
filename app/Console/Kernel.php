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
        \App\Console\Commands\NajmHodaGameDay::class,
        \App\Console\Commands\NajmHodaGoalLoop::class,
        \App\Console\Commands\NajmHodaOpsMonitor::class,
        \App\Console\Commands\NajmHodaModerationSweep::class,
        \App\Console\Commands\SendElectionReminders::class,
        \App\Console\Commands\SendAuctionReminders::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        
        // Close expired auctions every minute
        $schedule->command('auctions:close')->everyMinute();
        
        // Send election reminders every 12 hours
        $schedule->command('elections:send-reminders')->everyTwelveHours();
        
        // Send auction reminders every hour
        $schedule->command('auctions:send-reminders')->hourly();

        // Najm Hoda scheduled group moderation cleanup (respects per-group interval)
        $schedule->command('najm-hoda:moderation-sweep --max-groups=200')->hourly();

        // Najm Hoda operational health monitor + auto triage
        $schedule->command('najm-hoda:ops-monitor')->everyFiveMinutes();

        // Najm Hoda autonomous goal loop (phase 4 skeleton)
        $schedule->command('najm-hoda:goal-loop')->everyTenMinutes();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
