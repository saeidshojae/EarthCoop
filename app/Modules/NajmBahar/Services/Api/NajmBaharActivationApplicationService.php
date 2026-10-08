<?php

namespace App\Modules\NajmBahar\Services\Api;

use App\Models\User;
use App\Models\UserPointConsumption;
use App\Models\UserPointConversion;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\MonetaryPolicyService;
use App\Modules\NajmBahar\Services\MonetaryService;
use App\Services\ParticipationPointSummaryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NajmBaharActivationApplicationService
{
    public function __construct(
        private readonly AccountService $accounts,
        private readonly MonetaryService $monetary,
        private readonly MonetaryPolicyService $policy,
        private readonly ParticipationPointSummaryService $points,
    ) {
    }

    public function eligibility(User $user): array
    {
        $policy = $this->policy->current();
        $this->assertEnabled($policy);

        $account = $this->accounts->getMainAccountForUser((int) $user->id);
        if (! $account instanceof Account) {
            throw (new ModelNotFoundException())->setModel(Account::class);
        }

        $summary = $this->points->forUser((int) $user->id);
        $ratio = max(1, (int) data_get($policy, 'parameters.reputation_to_gol_ratio', 100));
        $remaining = max(0, (int) ($summary['remaining_convertible_points'] ?? 0));
        $wholePoints = intdiv($remaining, $ratio) * $ratio;
        $pointCapacityGol = intdiv($wholePoints, $ratio);
        $dimAvailable = max(0, (int) ($account->balance_faded ?? 0));

        return [
            'activation_contract_version' => 1,
            'enabled' => true,
            'source' => 'participation',
            'remaining_convertible_points' => $remaining,
            'conversion_ratio_points_per_gol' => $ratio,
            'max_convertible_points' => $wholePoints,
            'max_activation_gol' => min($pointCapacityGol, $dimAvailable),
            'max_activation_points' => min($pointCapacityGol, $dimAvailable) * $ratio,
            'dim_available_gol' => $dimAvailable,
            'active_gol' => max(0, (int) ($account->balance_active ?? 0)),
            'policy_version_id' => $policy['version_id'] ?? null,
            'policy_version' => $policy['version'] ?? null,
            'policy_source' => (string) ($policy['source'] ?? 'unknown'),
        ];
    }

    /**
     * @return array{
     *   requested_points:int,
     *   consumed_points:int,
     *   activated_gol:int,
     *   already_applied:bool,
     *   transaction:Transaction,
     *   account:Account
     * }
     */
    public function activate(User $user, int $requestedPoints, string $requestKey, ?array $expected = null): array
    {
        if ($requestedPoints <= 0) {
            throw new NajmBaharActivationException(
                'activation_not_eligible',
                'Requested participation points must be positive.',
                409,
            );
        }

        $requestKey = trim($requestKey);
        if ($requestKey === '') {
            throw new \InvalidArgumentException('Activation request key is required.');
        }

        $policy = $this->policy->current();
        $this->assertEnabled($policy);

        $account = $this->accounts->getMainAccountForUser((int) $user->id);
        if (! $account instanceof Account) {
            throw (new ModelNotFoundException())->setModel(Account::class);
        }

        $ratio = max(1, (int) data_get($policy, 'parameters.reputation_to_gol_ratio', 100));
        $convertiblePoints = intdiv($requestedPoints, $ratio) * $ratio;
        $amountGol = intdiv($convertiblePoints, $ratio);

        if ($amountGol <= 0) {
            throw new NajmBaharActivationException(
                'activation_not_eligible',
                "At least {$ratio} eligible participation points are required.",
                409,
            );
        }

        $conversionKey = 'reputation-conversion:'.(int) $user->id.':'.$requestKey;
        $policyVersionId = $policy['version_id'] ?? null;
        $policyVersion = $policy['version'] ?? null;

        return DB::transaction(function () use (
            $user,
            $account,
            $requestedPoints,
            $convertiblePoints,
            $amountGol,
            $ratio,
            $policyVersionId,
            $policyVersion,
            $requestKey,
            $conversionKey,
            $expected,
        ) {
            if ($expected !== null) {
                // Fail closed before creating any financial or point-consumption identity.
                // Eligibility is reevaluated within the transaction for the same user.
                $fresh = $this->eligibility($user);
                $snapshotFields = [
                    'activation_contract_version',
                    'policy_version_id',
                    'policy_version',
                    'conversion_ratio_points_per_gol',
                    'remaining_convertible_points',
                    'dim_available_gol',
                    'max_activation_gol',
                ];
                foreach ($snapshotFields as $field) {
                    if (! array_key_exists($field, $expected)
                        || $expected[$field] !== $fresh[$field]) {
                        throw new NajmBaharActivationException(
                            'activation_terms_changed',
                            'Activation terms changed after review; refresh eligibility.',
                            409,
                        );
                    }
                }

                if ($requestedPoints % $ratio !== 0) {
                    throw new NajmBaharActivationException(
                        'activation_not_eligible',
                        'Native activation points must be an exact multiple of the conversion ratio.',
                        409,
                    );
                }

                if ($requestedPoints > $fresh['max_activation_points']) {
                    throw new NajmBaharActivationException(
                        'activation_not_eligible',
                        'Requested points exceed current activation eligibility.',
                        409,
                    );
                }
            }

            $identity = UserPointConversion::firstOrCreate(
                [
                    'user_id' => (int) $user->id,
                    'request_key' => $requestKey,
                ],
                [
                    'conversion_key' => $conversionKey,
                    'requested_points' => $requestedPoints,
                    'consumed_points' => 0,
                    'amount_gol' => $amountGol,
                    'ratio' => $ratio,
                    'policy_version_id' => $policyVersionId,
                    'policy_version' => $policyVersion,
                    'status' => 'pending',
                ],
            );

            $identity = UserPointConversion::query()
                ->whereKey($identity->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $identity->requested_points !== $requestedPoints) {
                throw new NajmBaharActivationException(
                    'idempotency_key_reused',
                    'The activation request key was already used with a different request.',
                    409,
                );
            }

            if ($identity->status === 'applied') {
                $transaction = $this->transactionForConversionKey((string) $identity->conversion_key);

                return [
                    'requested_points' => (int) $identity->requested_points,
                    'consumed_points' => (int) $identity->consumed_points,
                    'activated_gol' => (int) $identity->amount_gol,
                    'already_applied' => true,
                    'transaction' => $transaction,
                    'account' => Account::query()->findOrFail($account->id),
                ];
            }

            $transactions = $this->points
                ->convertibleTransactionsQuery((int) $user->id)
                ->lockForUpdate()
                ->get();

            $positiveRemainingPoints = (int) $transactions->sum(function ($transaction) {
                return max(
                    0,
                    (int) $transaction->delta - (int) ($transaction->consumptions_sum_points_consumed ?? 0),
                );
            });
            $availablePoints = max(
                0,
                $positiveRemainingPoints - $this->points->participationReversalPoints((int) $user->id),
            );

            if ($convertiblePoints > $availablePoints) {
                throw new NajmBaharActivationException(
                    'activation_not_eligible',
                    'Eligible participation points are insufficient for this activation.',
                    409,
                );
            }

            $lockedAccount = Account::query()
                ->whereKey($account->id)
                ->where('user_id', (int) $user->id)
                ->lockForUpdate()
                ->first();
            if (! $lockedAccount instanceof Account) {
                throw (new ModelNotFoundException())->setModel(Account::class);
            }

            if ((int) ($lockedAccount->balance_faded ?? 0) < $amountGol) {
                throw new NajmBaharActivationException(
                    'insufficient_dim',
                    'Dim balance is insufficient for this activation.',
                    409,
                );
            }

            $remaining = $convertiblePoints;
            foreach ($transactions as $transaction) {
                if ($remaining <= 0) {
                    break;
                }

                $alreadyConsumed = (int) ($transaction->consumptions_sum_points_consumed ?? 0);
                $availableFromTransaction = max(0, (int) $transaction->delta - $alreadyConsumed);
                $toConsume = min($availableFromTransaction, $remaining);

                if ($toConsume <= 0) {
                    continue;
                }

                UserPointConsumption::create([
                    'user_id' => (int) $user->id,
                    'user_point_conversion_id' => (int) $identity->id,
                    'user_point_transaction_id' => (int) $transaction->id,
                    'points_consumed' => $toConsume,
                    'conversion_key' => $conversionKey,
                    'policy_version_id' => $policyVersionId,
                    'policy_version' => $policyVersion,
                ]);

                $remaining -= $toConsume;
            }

            if ($remaining !== 0) {
                throw new NajmBaharActivationException(
                    'activation_not_eligible',
                    'Eligible participation point consumption could not be completed.',
                    409,
                );
            }

            $activation = $this->monetary->activateDim(
                $lockedAccount,
                $amountGol,
                "تبدیل {$convertiblePoints} امتیاز به پول فعال",
                [
                    'type' => 'reputation_conversion',
                    'user_id' => (int) $user->id,
                    'points_converted' => $convertiblePoints,
                    'ratio' => $ratio,
                    'policy_version_id' => $policyVersionId,
                    'policy_version' => $policyVersion,
                    'user_point_conversion_id' => (int) $identity->id,
                ],
                $conversionKey,
                false,
            );

            $identity->update([
                'consumed_points' => $convertiblePoints,
                'status' => 'applied',
            ]);

            Log::info('Participation points converted to active Bahar', [
                'user_id' => (int) $user->id,
                'user_point_conversion_id' => (int) $identity->id,
                'points' => $convertiblePoints,
                'amount_gol' => $amountGol,
                'ratio' => $ratio,
                'policy_version_id' => $policyVersionId,
                'conversion_key' => $conversionKey,
            ]);

            return [
                'requested_points' => $requestedPoints,
                'consumed_points' => $convertiblePoints,
                'activated_gol' => (int) $activation['amount'],
                'already_applied' => ! (bool) $activation['applied'],
                'transaction' => $activation['transaction'],
                'account' => Account::query()->findOrFail($lockedAccount->id),
            ];
        });
    }

    private function assertEnabled(array $policy): void
    {
        if (! (bool) data_get($policy, 'parameters.reputation_conversion_enabled', false)) {
            throw new NajmBaharActivationException(
                'activation_disabled',
                'Participation-point activation is currently disabled by policy.',
                403,
            );
        }
    }

    private function transactionForConversionKey(string $conversionKey): Transaction
    {
        $transaction = Transaction::query()
            ->where('metadata->idempotency_key', $conversionKey)
            ->first();

        if (! $transaction instanceof Transaction) {
            throw new \RuntimeException('Applied activation transaction evidence is missing.');
        }

        return $transaction;
    }
}
