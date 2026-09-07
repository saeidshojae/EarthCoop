<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\FounderNajmBaharTransactionIntent;
use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\ActiveBaharReservation;
use App\Modules\NajmBahar\Services\TransactionService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use Illuminate\Support\Facades\DB;

class FounderNajmBaharTransactionDecisionService
{
    public function __construct(
        protected FounderActionRequestService $requests,
        protected FounderActionExecutionService $execution,
        protected NajmHodaAutonomyApprovalService $approvals,
        protected TransactionService $transactions
    ) {}

    /** @return array<string,mixed> */
    public function requestExecute(FounderNajmBaharTransactionIntent $intent, int $requestedBy): array
    {
        if (! in_array((string) $intent->status, ['draft', 'approved'], true)) {
            return ['success'=>false,'status'=>'blocked','reason'=>'transaction_intent_not_executable'];
        }

        return $this->requests->prepare('najm_bahar', 'execute_transaction', [
            'entity_type' => 'founder_najm_bahar_transaction_intent',
            'entity_id' => (int) $intent->id,
            'requested_by' => $requestedBy,
            'reason_code' => 'najm-bahar-transaction-intent-' . (int) $intent->id,
            'source_event' => 'founder_ops_najm_bahar_transaction_intent',
        ]);
    }

    /** @return array<string,mixed> */
    public function decideAndExecute(string $requestId, string $decision, int $founderId, ?string $reason = null): array
    {
        if (! in_array($founderId, $this->founderIds(), true)) {
            return ['success'=>false,'status'=>'forbidden','reason'=>'founder_not_authorized'];
        }

        $pending = collect($this->approvals->pending(200))
            ->first(fn (array $item): bool => (string)($item['id']??'') === $requestId);
        if (! is_array($pending)) return ['success'=>false,'status'=>'not_found','reason'=>'approval_request_not_pending'];

        if ((string)data_get($pending,'plan_item.domain') !== 'najm_bahar'
            || (string)data_get($pending,'plan_item.domain_action') !== 'execute_transaction'
            || (string)data_get($pending,'context.entity_type') !== 'founder_najm_bahar_transaction_intent') {
            return ['success'=>false,'status'=>'invalid_request','reason'=>'approval_contract_mismatch'];
        }

        $intentId=(int)data_get($pending,'context.entity_id',0);
        $intent=$intentId>0?FounderNajmBaharTransactionIntent::query()->find($intentId):null;
        if (!$intent) return ['success'=>false,'status'=>'not_found','reason'=>'transaction_intent_not_found'];
        if (!in_array((string)$intent->status,['draft','approved'],true)) return ['success'=>false,'status'=>'blocked','reason'=>'transaction_intent_not_executable'];

        $founder=User::query()->find($founderId);
        if (!$founder) return ['success'=>false,'status'=>'not_found','reason'=>'founder_user_not_found'];

        $decisionResult=$this->approvals->decide($requestId,$decision,$founderId,$reason);
        if (!($decisionResult['success']??false)) return $decisionResult;
        if ($decision==='reject') {
            $intent->update(['status'=>'rejected','approved_by_user_id'=>$founderId,'rejected_at'=>now()]);
            return ['success'=>true,'status'=>'rejected','intent_id'=>$intentId];
        }

        $intent->update(['status'=>'approved','approved_by_user_id'=>$founderId,'approved_at'=>now()]);

        return $this->execution->execute('najm_bahar','execute_transaction',function () use ($intentId): array {
            return DB::transaction(function () use ($intentId): array {
                $intent=FounderNajmBaharTransactionIntent::query()->whereKey($intentId)->lockForUpdate()->firstOrFail();
                if ((string)$intent->status==='executed' && $intent->transaction_id) {
                    return ['intent_id'=>$intent->id,'transaction_id'=>$intent->transaction_id,'idempotent'=>true];
                }
                if ((string)$intent->balance_type!=='active' || (int)$intent->amount<=0) {
                    throw new \RuntimeException('Founder transaction intent is not a valid Active Bahar transfer.');
                }

                $ids=[(int)$intent->from_account_id,(int)$intent->to_account_id]; sort($ids,SORT_NUMERIC);
                $accounts=Account::query()->whereIn('id',$ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $from=$accounts->get((int)$intent->from_account_id); $to=$accounts->get((int)$intent->to_account_id);
                if(!$from||!$to||(int)$from->status!==1||(int)$to->status!==1) throw new \RuntimeException('Transaction intent accounts are unavailable.');
                if((int)$from->id===(int)$to->id) throw new \RuntimeException('Transaction intent source and destination must differ.');

                $reserved=(int)ActiveBaharReservation::query()
                    ->where('payer_account_id',$from->id)
                    ->where('status',ActiveBaharReservation::RESERVED)
                    ->lockForUpdate()->sum('amount');
                $available=max(0,(int)$from->balance_active-$reserved);
                if($available<(int)$intent->amount) throw new \RuntimeException('Insufficient available Active Bahar after open reservations.');

                $metadata=array_merge((array)$intent->metadata,[
                    'founder_ops_intent_id'=>(int)$intent->id,
                    'founder_ops'=>true,
                ]);
                unset($metadata['system_operation']);

                $tx=$this->transactions->transfer(
                    (string)$from->account_number,
                    (string)$to->account_number,
                    (int)$intent->amount,
                    $intent->description,
                    $metadata,
                    (string)$intent->idempotency_key,
                    'active',
                    $intent->transaction_type ?: 'founder_approved_transfer'
                );

                $intent->forceFill(['status'=>'executed','transaction_id'=>$tx->id,'executed_at'=>now()])->save();
                return ['intent_id'=>$intent->id,'transaction_id'=>$tx->id,'tracking_number'=>$tx->tracking_number,'idempotent'=>false];
            });
        },$requestId,['entity_type'=>'founder_najm_bahar_transaction_intent','entity_id'=>$intentId,'requested_by'=>$founderId]);
    }

    /** @return array<int,int> */
    protected function founderIds(): array
    {
        return array_values(array_filter(array_map('intval',(array)config('najm-hoda-founder-action-policy.founder_approval.user_ids',[]))));
    }
}
