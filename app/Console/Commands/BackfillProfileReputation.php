<?php

namespace App\Console\Commands;

use App\Models\Address;
use App\Models\User;
use App\Models\UserExperience;
use App\Models\UserPoint;
use App\Models\UserPointTransaction;
use App\Services\ProfileCompletionService;
use App\Services\ReputationService;
use Illuminate\Console\Command;

class BackfillProfileReputation extends Command
{
    protected $signature = 'reputation:backfill-profile {--dry-run : Show counts without writing changes}';

    protected $description = 'Backfill profile completion points and reconcile user_points from the ledger.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $eligible = 0;
        $awarded = 0;
        $pointsCreated = 0;
        $pointsUpdated = 0;

        $profileCompletion = app(ProfileCompletionService::class);
        $reputation = app(ReputationService::class);

        User::query()->select(['id', 'first_name', 'last_name', 'gender', 'national_id', 'phone'])
            ->orderBy('id')
            ->chunkById(200, function ($users) use (
                $dryRun,
                $profileCompletion,
                $reputation,
                &$eligible,
                &$awarded,
                &$pointsCreated,
                &$pointsUpdated
            ) {
                foreach ($users as $user) {
                    $step1Complete = $user->first_name && $user->last_name && $user->gender && $user->national_id && $user->phone;
                    $hasExperience = UserExperience::where('user_id', $user->id)->exists();
                    $hasAddress = Address::where('user_id', $user->id)->exists();

                    if ($step1Complete && $hasExperience && $hasAddress) {
                        $eligible++;
                        if (! $dryRun) {
                            if ($profileCompletion->maybeAward($user)) {
                                $awarded++;
                            }
                        }
                    }

                    $latestBalance = (int) (UserPointTransaction::where('user_id', $user->id)
                        ->orderByDesc('created_at')
                        ->value('balance_after') ?? 0);

                    $userPoint = UserPoint::where('user_id', $user->id)->first();
                    if (! $userPoint) {
                        if (! $dryRun) {
                            UserPoint::create([
                                'user_id' => $user->id,
                                'points' => $latestBalance,
                                'level' => $reputation->determineLevel($latestBalance),
                            ]);
                        }
                        $pointsCreated++;
                        continue;
                    }

                    if ((int) $userPoint->points !== $latestBalance) {
                        if (! $dryRun) {
                            $userPoint->points = $latestBalance;
                            $userPoint->level = $reputation->determineLevel($latestBalance);
                            $userPoint->save();
                        }
                        $pointsUpdated++;
                    }
                }
            });

        $this->info('Eligible users: ' . $eligible);
        $this->info('Profile points awarded: ' . $awarded . ($dryRun ? ' (dry-run)' : ''));
        $this->info('User points created: ' . $pointsCreated . ($dryRun ? ' (dry-run)' : ''));
        $this->info('User points updated: ' . $pointsUpdated . ($dryRun ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }
}
