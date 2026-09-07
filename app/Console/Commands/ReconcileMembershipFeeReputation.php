<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserPointTransaction;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use App\Services\ReputationService;
use Illuminate\Console\Command;

class ReconcileMembershipFeeReputation extends Command
{
    protected $signature = 'reputation:reconcile-membership-fees
        {--user= : فقط شناسه یک کاربر را بررسی کن}
        {--dry-run : فقط موارد قابل اصلاح را گزارش کن و داده‌ای تغییر نده}';

    protected $description = 'Reconcile completed Najm Bahar membership-fee payments with missing reputation events.';

    public function handle(ReputationService $reputation): int
    {
        $userFilter = $this->option('user');
        $dryRun = (bool) $this->option('dry-run');
        $seen = [];
        $eligible = 0;
        $awarded = 0;
        $alreadyPresent = 0;
        $invalid = 0;

        NajmTransaction::query()
            ->where('metadata->type', 'membership_fee')
            ->where('status', 'completed')
            ->orderBy('id')
            ->chunkById(200, function ($transactions) use (
                $reputation,
                $userFilter,
                $dryRun,
                &$seen,
                &$eligible,
                &$awarded,
                &$alreadyPresent,
                &$invalid
            ) {
                foreach ($transactions as $transaction) {
                    $meta = is_array($transaction->metadata) ? $transaction->metadata : [];
                    $userId = isset($meta['user_id']) ? (int) $meta['user_id'] : 0;
                    $paymentYear = isset($meta['payment_year']) ? (int) $meta['payment_year'] : 0;

                    if ($userId <= 0 || $paymentYear <= 0) {
                        $invalid++;
                        continue;
                    }
                    if ($userFilter !== null && (int) $userFilter !== $userId) {
                        continue;
                    }

                    $eventKey = 'membership_fee_paid:user:' . $userId . ':year:' . $paymentYear;
                    if (isset($seen[$eventKey])) {
                        continue;
                    }
                    $seen[$eventKey] = true;

                    if (UserPointTransaction::where('event_key', $eventKey)->exists()
                        || UserPointTransaction::where('user_id', $userId)
                            ->where('action', 'membership_fee_paid')
                            ->where('reference_id', $paymentYear)
                            ->exists()) {
                        $alreadyPresent++;
                        continue;
                    }

                    $user = User::find($userId);
                    if (! $user) {
                        $invalid++;
                        continue;
                    }

                    $eligible++;
                    if ($dryRun) {
                        $this->line("[DRY] user={$userId} year={$paymentYear} event={$eventKey}");
                        continue;
                    }

                    $result = $reputation->applyAction(
                        $user,
                        'membership_fee_paid',
                        [
                            'payment_year' => $paymentYear,
                            'payment_source' => $meta['payment_source'] ?? 'historical_reconciliation',
                            'policy_version_id' => $meta['policy_version_id'] ?? null,
                            'reconciled_from_transaction_id' => (int) $transaction->id,
                        ],
                        $paymentYear,
                        'najm_bahar_membership_reconciliation',
                        $eventKey
                    );

                    if ($result !== null) {
                        $awarded++;
                    }
                }
            });

        $this->info(sprintf(
            'Membership reputation reconciliation: eligible=%d awarded=%d already_present=%d invalid=%d mode=%s',
            $eligible,
            $awarded,
            $alreadyPresent,
            $invalid,
            $dryRun ? 'dry-run' : 'apply'
        ));

        return self::SUCCESS;
    }
}
