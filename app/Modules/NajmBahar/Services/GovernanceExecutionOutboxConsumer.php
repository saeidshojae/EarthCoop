<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\Governance\Models\EconomicAction;
use App\Modules\Governance\Models\PublicContributionObligation;
use App\Modules\Governance\Models\PublicContributionPlan;
use App\Modules\Governance\Models\PublicExecutionAuthorization;
use App\Modules\Governance\Services\PublicExecutionBridge;
use Illuminate\Support\Facades\DB;
use Throwable;

class GovernanceExecutionOutboxConsumer
{
    public function consume(EconomicAction $action): EconomicAction
    {
        try {
            return DB::transaction(function () use ($action) {
                $lockedAction = EconomicAction::whereKey($action->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedAction->status === 'completed') {
                    return $lockedAction;
                }

                if ($lockedAction->action_type !== PublicExecutionBridge::PUBLIC_PROJECT_EXECUTION_AUTHORIZED) {
                    throw new \RuntimeException('Najm Bahar consumer cannot execute this governance action type.');
                }

                if (! in_array($lockedAction->status, ['pending', 'failed'], true)) {
                    throw new \RuntimeException('Governance economic action is not available for consumption.');
                }

                $payload = (array) ($lockedAction->payload ?? []);
                $planId = (int) ($payload['public_contribution_plan_id'] ?? 0);
                $authorizationId = (int) ($payload['execution_authorization_id'] ?? 0);
                if ($planId <= 0 || $authorizationId <= 0) {
                    throw new \RuntimeException('Execution outbox payload is incomplete.');
                }

                $lockedAction->update([
                    'status' => 'processing',
                    'attempts' => (int) $lockedAction->attempts + 1,
                    'claimed_at' => now(),
                    'failure_reason' => null,
                    'failed_at' => null,
                ]);

                $plan = PublicContributionPlan::whereKey($planId)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ((int) $plan->resolution_id !== (int) $lockedAction->resolution_id
                    || (int) $plan->group_id !== (int) $lockedAction->group_id) {
                    throw new \RuntimeException('Execution outbox does not match its public contribution plan.');
                }
                if ($plan->status !== 'execution_queued'
                    || (int) $plan->committed_total_gol !== (int) $plan->total_required_gol) {
                    throw new \RuntimeException('Public contribution plan is not ready for monetary execution.');
                }

                $authorization = PublicExecutionAuthorization::whereKey($authorizationId)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ((int) $authorization->plan_id !== (int) $plan->id || $authorization->status !== 'authorized') {
                    throw new \RuntimeException('Execution authorization is not valid for this contribution plan.');
                }

                $group = $plan->group()->firstOrFail();
                $executionAccount = app(AccountService::class)->ensureLegalEntityAccountForGroup($group);

                $obligations = PublicContributionObligation::where('plan_id', $plan->id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                if ($obligations->isEmpty()) {
                    throw new \RuntimeException('Public contribution plan has no obligations to settle.');
                }

                $committedTotal = (int) $obligations->sum(fn ($obligation) => (int) $obligation->committed_gol);
                if ($committedTotal !== (int) $plan->total_required_gol) {
                    throw new \RuntimeException('Committed obligation total does not match the approved public capital.');
                }

                $transactionIds = [];
                foreach ($obligations as $obligation) {
                    $amount = (int) $obligation->amount_gol;
                    if ($obligation->status !== 'committed'
                        || (int) $obligation->committed_gol !== $amount
                        || (int) $obligation->paid_gol !== 0) {
                        throw new \RuntimeException('Every public obligation must be fully committed and unpaid before execution.');
                    }

                    $sourceAccount = app(AccountService::class)->getMainAccountForUser((int) $obligation->user_id);
                    if (! $sourceAccount) {
                        throw new \RuntimeException('Obligated member has no Najm Bahar main account.');
                    }

                    $transaction = app(DimCommitmentService::class)->settleCommittedToActive(
                        $sourceAccount,
                        $executionAccount,
                        $amount,
                        'Settle committed Dim for public execution obligation #' . $obligation->id,
                        'public-execution:action:' . $lockedAction->id . ':obligation:' . $obligation->id,
                        [
                            'governance_economic_action_id' => (int) $lockedAction->id,
                            'public_contribution_plan_id' => (int) $plan->id,
                            'public_contribution_obligation_id' => (int) $obligation->id,
                            'execution_authorization_id' => (int) $authorization->id,
                            'resolution_id' => (int) $plan->resolution_id,
                            'group_id' => (int) $plan->group_id,
                        ]
                    );
                    $transactionIds[] = (int) $transaction->id;

                    $metadata = (array) ($obligation->metadata ?? []);
                    $metadata['execution_settlement_transaction_id'] = (int) $transaction->id;
                    $obligation->update([
                        'paid_gol' => $amount,
                        'committed_gol' => 0,
                        'status' => 'paid',
                        'paid_at' => now(),
                        'metadata' => $metadata,
                    ]);
                }

                $planMetadata = (array) ($plan->metadata ?? []);
                $planMetadata['monetary_execution_started'] = true;
                $planMetadata['monetary_execution_completed'] = true;
                $planMetadata['execution_account_id'] = (int) $executionAccount->id;
                $planMetadata['execution_economic_action_id'] = (int) $lockedAction->id;
                $plan->update([
                    'status' => 'executed',
                    'committed_total_gol' => 0,
                    'closed_at' => now(),
                    'metadata' => $planMetadata,
                ]);

                $authorization->update(['status' => 'consumed']);

                $resolution = $lockedAction->resolution()->lockForUpdate()->first();
                if ($resolution) {
                    $resolutionMetadata = (array) ($resolution->metadata ?? []);
                    $resolutionMetadata['economic_bridge_applied'] = true;
                    $resolutionMetadata['economic_effect_executed'] = true;
                    $resolutionMetadata['public_execution_account_id'] = (int) $executionAccount->id;
                    $resolution->update([
                        'effect_status' => 'executed',
                        'metadata' => $resolutionMetadata,
                    ]);
                }

                $lockedAction->update([
                    'status' => 'completed',
                    'result' => [
                        'public_contribution_plan_id' => (int) $plan->id,
                        'execution_account_id' => (int) $executionAccount->id,
                        'settled_total_gol' => (int) $plan->total_required_gol,
                        'settlement_transaction_ids' => $transactionIds,
                    ],
                    'completed_at' => now(),
                    'failure_reason' => null,
                    'failed_at' => null,
                ]);

                return $lockedAction->fresh();
            }, 3);
        } catch (Throwable $e) {
            DB::transaction(function () use ($action, $e) {
                $failedAction = EconomicAction::whereKey($action->id)->lockForUpdate()->first();
                if (! $failedAction || $failedAction->status === 'completed') {
                    return;
                }

                $failedAction->update([
                    'status' => 'failed',
                    'attempts' => (int) $failedAction->attempts + 1,
                    'failure_reason' => mb_substr($e->getMessage(), 0, 2000),
                    'failed_at' => now(),
                ]);
            }, 3);

            throw $e;
        }
    }
}
