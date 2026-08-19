<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\ActiveBaharReservation;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ActiveBaharReservationService
{
    public function reserve(string $payerAccountNumber, int $amount, string $reservationKey, string $referenceType, int|string $referenceId, array $metadata = []): ActiveBaharReservation
    {
        $this->assertPositive($amount); $this->assertKey($reservationKey);
        return DB::transaction(function () use ($payerAccountNumber,$amount,$reservationKey,$referenceType,$referenceId,$metadata) {
            $existing=ActiveBaharReservation::query()->where('reservation_key',$reservationKey)->lockForUpdate()->first();
            if ($existing) return $this->assertSameReservation($existing,$payerAccountNumber,$amount,$referenceType,$referenceId);
            $payer=Account::query()->where('account_number',$payerAccountNumber)->lockForUpdate()->first();
            if (! $payer) throw new RuntimeException('Payer account not found.');
            if ($this->availableActive($payer)<$amount) throw new RuntimeException('Insufficient available Active Bahar.');
            return ActiveBaharReservation::create(['payer_account_id'=>$payer->id,'amount'=>$amount,'status'=>ActiveBaharReservation::RESERVED,'reference_type'=>$referenceType,'reference_id'=>(string)$referenceId,'reservation_key'=>$reservationKey,'metadata'=>$metadata,'reserved_at'=>now()]);
        });
    }

    public function release(string $reservationKey,string $releaseKey): ActiveBaharReservation
    {
        $this->assertKey($releaseKey);
        return DB::transaction(function () use ($reservationKey,$releaseKey) {
            $r=$this->lockedReservation($reservationKey);
            if ($r->release_key===$releaseKey && $r->status===ActiveBaharReservation::RELEASED) return $r;
            if ($r->status!==ActiveBaharReservation::RESERVED) throw new RuntimeException('Only an active reservation can be released.');
            $r->forceFill(['status'=>ActiveBaharReservation::RELEASED,'release_key'=>$releaseKey,'released_at'=>now()])->save(); return $r->fresh();
        });
    }

    public function settle(string $reservationKey,string $payeeAccountNumber,string $settlementKey,array $metadata=[]): ActiveBaharReservation
    {
        $this->assertKey($settlementKey);
        return DB::transaction(function () use ($reservationKey,$payeeAccountNumber,$settlementKey,$metadata) {
            $r=$this->lockedReservation($reservationKey);
            if ($r->settlement_key===$settlementKey && $r->status===ActiveBaharReservation::SETTLED) return $r;
            if ($r->status!==ActiveBaharReservation::RESERVED) throw new RuntimeException('Only an active reservation can be settled.');
            [$payer,$payee]=$this->lockAccounts((int)$r->payer_account_id,$payeeAccountNumber);
            if ((int)$payer->balance_active<(int)$r->amount) throw new RuntimeException('Reserved Active Bahar is no longer backed by payer balance.');

            $payer->balance_active=(int)$payer->balance_active-(int)$r->amount; $payer->balance=(int)$payer->balance_active+(int)$payer->balance_faded; $payer->save();
            $payee->balance_active=(int)$payee->balance_active+(int)$r->amount; $payee->balance=(int)$payee->balance_active+(int)$payee->balance_faded; $payee->save();

            $meta=array_merge((array)$r->metadata,$metadata,['idempotency_key'=>$settlementKey,'reservation_key'=>$reservationKey,'balance_type'=>'active','reference_type'=>$r->reference_type,'reference_id'=>$r->reference_id]);
            $tx=NajmTransaction::create(['idempotency_key'=>$settlementKey,'from_account_id'=>$payer->id,'to_account_id'=>$payee->id,'amount'=>(int)$r->amount,'type'=>'reservation_settlement','status'=>'completed','metadata'=>$meta,'description'=>'Active Bahar reservation settlement']);
            LedgerEntry::create(['transaction_id'=>$tx->id,'account_id'=>$payer->id,'amount'=>-(int)$r->amount,'entry_type'=>'debit','meta'=>$meta]);
            LedgerEntry::create(['transaction_id'=>$tx->id,'account_id'=>$payee->id,'amount'=>(int)$r->amount,'entry_type'=>'credit','meta'=>$meta]);
            $r->forceFill(['payee_account_id'=>$payee->id,'settled_amount'=>$r->amount,'status'=>ActiveBaharReservation::SETTLED,'settlement_key'=>$settlementKey,'settled_at'=>now(),'metadata'=>$meta])->save(); return $r->fresh();
        });
    }

    public function refund(string $reservationKey,int $amount,string $refundKey,array $metadata=[]): ActiveBaharReservation
    {
        $this->assertPositive($amount); $this->assertKey($refundKey);
        return DB::transaction(function () use ($reservationKey,$amount,$refundKey,$metadata) {
            $r=$this->lockedReservation($reservationKey); $refunds=(array)data_get($r->metadata,'refund_keys',[]);
            if (isset($refunds[$refundKey])) return $r;
            if (!in_array($r->status,[ActiveBaharReservation::SETTLED,ActiveBaharReservation::PARTIALLY_REFUNDED],true)) throw new RuntimeException('Only settled reservations can be refunded.');
            if ((int)$r->refunded_amount+$amount>(int)$r->settled_amount) throw new RuntimeException('Refund exceeds settled amount.');
            [$payer,$payee]=$this->lockAccountsByIds((int)$r->payer_account_id,(int)$r->payee_account_id);
            if ((int)$payee->balance_active<$amount) throw new RuntimeException('Payee has insufficient Active Bahar for refund.');

            $payee->balance_active=(int)$payee->balance_active-$amount; $payee->balance=(int)$payee->balance_active+(int)$payee->balance_faded; $payee->save();
            $payer->balance_active=(int)$payer->balance_active+$amount; $payer->balance=(int)$payer->balance_active+(int)$payer->balance_faded; $payer->save();
            $meta=array_merge($metadata,['idempotency_key'=>$refundKey,'reservation_key'=>$reservationKey,'balance_type'=>'active','refund_key'=>$refundKey]);
            $tx=NajmTransaction::create(['idempotency_key'=>$refundKey,'from_account_id'=>$payee->id,'to_account_id'=>$payer->id,'amount'=>$amount,'type'=>'reservation_refund','status'=>'completed','metadata'=>$meta,'description'=>'Active Bahar reservation refund']);
            LedgerEntry::create(['transaction_id'=>$tx->id,'account_id'=>$payee->id,'amount'=>-$amount,'entry_type'=>'debit','meta'=>$meta]); LedgerEntry::create(['transaction_id'=>$tx->id,'account_id'=>$payer->id,'amount'=>$amount,'entry_type'=>'credit','meta'=>$meta]);
            $refunds[$refundKey]=$amount; $new=(int)$r->refunded_amount+$amount; $r->forceFill(['refunded_amount'=>$new,'status'=>$new===(int)$r->settled_amount?ActiveBaharReservation::REFUNDED:ActiveBaharReservation::PARTIALLY_REFUNDED,'metadata'=>array_merge((array)$r->metadata,$metadata,['refund_keys'=>$refunds])])->save(); return $r->fresh();
        });
    }

    public function availableActive(Account $account): int
    {
        $reserved=(int)ActiveBaharReservation::query()->where('payer_account_id',$account->id)->where('status',ActiveBaharReservation::RESERVED)->sum('amount');
        return max(0,(int)$account->balance_active-$reserved);
    }

    protected function lockAccounts(int $payerId,string $payeeNumber): array
    {
        $payeeId=(int)(Account::query()->where('account_number',$payeeNumber)->value('id')??0); if ($payeeId<=0) throw new RuntimeException('Payee account not found.'); return $this->lockAccountsByIds($payerId,$payeeId);
    }
    protected function lockAccountsByIds(int $payerId,int $payeeId): array
    {
        if ($payerId===$payeeId) throw new RuntimeException('Payer and payee accounts must differ.');
        $ids=[$payerId,$payeeId]; sort($ids,SORT_NUMERIC); $locked=Account::query()->whereIn('id',$ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $payer=$locked->get($payerId); $payee=$locked->get($payeeId); if(!$payer||!$payee) throw new RuntimeException('Settlement account not found.'); return [$payer,$payee];
    }
    protected function lockedReservation(string $key): ActiveBaharReservation { $r=ActiveBaharReservation::query()->where('reservation_key',$key)->lockForUpdate()->first(); if(!$r) throw new RuntimeException('Active Bahar reservation not found.'); return $r; }
    protected function assertSameReservation(ActiveBaharReservation $r,string $payer,int $amount,string $type,int|string $id): ActiveBaharReservation { $r->loadMissing('payerAccount'); if($r->payerAccount?->account_number!==$payer||(int)$r->amount!==$amount||$r->reference_type!==$type||$r->reference_id!==(string)$id) throw new RuntimeException('Reservation idempotency key conflicts with an existing reservation.'); return $r; }
    protected function assertPositive(int $amount): void { if($amount<=0) throw new \InvalidArgumentException('Amount must be positive integer Gol.'); }
    protected function assertKey(string $key): void { if(trim($key)==='') throw new \InvalidArgumentException('Idempotency key is required.'); }
}
