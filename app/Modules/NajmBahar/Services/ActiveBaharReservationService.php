<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\ActiveBaharReservation;
use App\Modules\NajmBahar\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ActiveBaharReservationService
{
    public function reserve(string $payerAccountNumber, int $amount, string $reservationKey, string $referenceType, int|string $referenceId, array $metadata = []): ActiveBaharReservation
    {
        $this->assertPositive($amount);
        $this->assertKey($reservationKey);

        return DB::transaction(function () use ($payerAccountNumber, $amount, $reservationKey, $referenceType, $referenceId, $metadata) {
            $existing = ActiveBaharReservation::query()->where('reservation_key', $reservationKey)->lockForUpdate()->first();
            if ($existing) return $this->assertSameReservation($existing, $payerAccountNumber, $amount, $referenceType, $referenceId);

            $payer = Account::query()->where('account_number', $payerAccountNumber)->lockForUpdate()->first();
            if (! $payer) throw new RuntimeException('Payer account not found.');

            $available = $this->availableActive($payer);
            if ($available < $amount) throw new RuntimeException('Insufficient available Active Bahar.');

            return ActiveBaharReservation::create([
                'payer_account_id' => $payer->id,
                'amount' => $amount,
                'status' => ActiveBaharReservation::RESERVED,
                'reference_type' => $referenceType,
                'reference_id' => (string) $referenceId,
                'reservation_key' => $reservationKey,
                'metadata' => $metadata,
                'reserved_at' => now(),
            ]);
        });
    }

    public function release(string $reservationKey, string $releaseKey): ActiveBaharReservation
    {
        $this->assertKey($releaseKey);
        return DB::transaction(function () use ($reservationKey, $releaseKey) {
            $reservation = $this->lockedReservation($reservationKey);
            if ($reservation->release_key === $releaseKey && $reservation->status === ActiveBaharReservation::RELEASED) return $reservation;
            if ($reservation->status !== ActiveBaharReservation::RESERVED) throw new RuntimeException('Only an active reservation can be released.');
            $reservation->forceFill(['status'=>ActiveBaharReservation::RELEASED,'release_key'=>$releaseKey,'released_at'=>now()])->save();
            return $reservation->fresh();
        });
    }

    public function settle(string $reservationKey, string $payeeAccountNumber, string $settlementKey, array $metadata = []): ActiveBaharReservation
    {
        $this->assertKey($settlementKey);
        return DB::transaction(function () use ($reservationKey, $payeeAccountNumber, $settlementKey, $metadata) {
            $reservation = $this->lockedReservation($reservationKey);
            if ($reservation->settlement_key === $settlementKey && $reservation->status === ActiveBaharReservation::SETTLED) return $reservation;
            if ($reservation->status !== ActiveBaharReservation::RESERVED) throw new RuntimeException('Only an active reservation can be settled.');

            $accounts = Account::query()->whereIn('id', [$reservation->payer_account_id])->lockForUpdate()->get()->keyBy('id');
            $payer = $accounts->get($reservation->payer_account_id);
            $payee = Account::query()->where('account_number', $payeeAccountNumber)->lockForUpdate()->first();
            if (! $payer || ! $payee) throw new RuntimeException('Settlement account not found.');

            if ((int)$payer->balance_active < (int)$reservation->amount) throw new RuntimeException('Reserved Active Bahar is no longer backed by payer balance.');

            $payer->balance_active = (int)$payer->balance_active - (int)$reservation->amount;
            $payer->balance = (int)$payer->balance_active + (int)$payer->balance_faded;
            $payer->save();

            $payee->balance_active = (int)$payee->balance_active + (int)$reservation->amount;
            $payee->balance = (int)$payee->balance_active + (int)$payee->balance_faded;
            $payee->save();

            $ledgerMeta = array_merge($metadata, ['reservation_key'=>$reservationKey,'settlement_key'=>$settlementKey,'balance_type'=>'active','reference_type'=>$reservation->reference_type,'reference_id'=>$reservation->reference_id]);
            LedgerEntry::create(['transaction_id'=>null,'account_id'=>$payer->id,'amount'=>-(int)$reservation->amount,'entry_type'=>'debit','meta'=>$ledgerMeta]);
            LedgerEntry::create(['transaction_id'=>null,'account_id'=>$payee->id,'amount'=>(int)$reservation->amount,'entry_type'=>'credit','meta'=>$ledgerMeta]);

            $reservation->forceFill(['payee_account_id'=>$payee->id,'settled_amount'=>$reservation->amount,'status'=>ActiveBaharReservation::SETTLED,'settlement_key'=>$settlementKey,'settled_at'=>now(),'metadata'=>array_merge((array)$reservation->metadata,$metadata)])->save();
            return $reservation->fresh();
        });
    }

    public function refund(string $reservationKey, int $amount, string $refundKey, array $metadata = []): ActiveBaharReservation
    {
        $this->assertPositive($amount); $this->assertKey($refundKey);
        return DB::transaction(function () use ($reservationKey, $amount, $refundKey, $metadata) {
            $reservation = $this->lockedReservation($reservationKey);
            $refunds = (array) data_get($reservation->metadata, 'refund_keys', []);
            if (isset($refunds[$refundKey])) return $reservation;
            if (! in_array($reservation->status, [ActiveBaharReservation::SETTLED, ActiveBaharReservation::PARTIALLY_REFUNDED], true)) throw new RuntimeException('Only settled reservations can be refunded.');
            if ((int)$reservation->refunded_amount + $amount > (int)$reservation->settled_amount) throw new RuntimeException('Refund exceeds settled amount.');

            $payer = Account::query()->whereKey($reservation->payer_account_id)->lockForUpdate()->first();
            $payee = Account::query()->whereKey($reservation->payee_account_id)->lockForUpdate()->first();
            if (! $payer || ! $payee) throw new RuntimeException('Refund account not found.');
            if ((int)$payee->balance_active < $amount) throw new RuntimeException('Payee has insufficient Active Bahar for refund.');

            $payee->balance_active=(int)$payee->balance_active-$amount; $payee->balance=(int)$payee->balance_active+(int)$payee->balance_faded; $payee->save();
            $payer->balance_active=(int)$payer->balance_active+$amount; $payer->balance=(int)$payer->balance_active+(int)$payer->balance_faded; $payer->save();

            $ledgerMeta=array_merge($metadata,['reservation_key'=>$reservationKey,'refund_key'=>$refundKey,'balance_type'=>'active']);
            LedgerEntry::create(['transaction_id'=>null,'account_id'=>$payee->id,'amount'=>-$amount,'entry_type'=>'debit','meta'=>$ledgerMeta]);
            LedgerEntry::create(['transaction_id'=>null,'account_id'=>$payer->id,'amount'=>$amount,'entry_type'=>'credit','meta'=>$ledgerMeta]);

            $refunds[$refundKey]=$amount; $newRefunded=(int)$reservation->refunded_amount+$amount;
            $reservation->forceFill(['refunded_amount'=>$newRefunded,'status'=>$newRefunded===(int)$reservation->settled_amount?ActiveBaharReservation::REFUNDED:ActiveBaharReservation::PARTIALLY_REFUNDED,'metadata'=>array_merge((array)$reservation->metadata,$metadata,['refund_keys'=>$refunds])])->save();
            return $reservation->fresh();
        });
    }

    public function availableActive(Account $account): int
    {
        $reserved=(int)ActiveBaharReservation::query()->where('payer_account_id',$account->id)->where('status',ActiveBaharReservation::RESERVED)->sum('amount');
        return max(0,(int)$account->balance_active-$reserved);
    }

    protected function lockedReservation(string $key): ActiveBaharReservation
    {
        $reservation=ActiveBaharReservation::query()->where('reservation_key',$key)->lockForUpdate()->first();
        if (! $reservation) throw new RuntimeException('Active Bahar reservation not found.');
        return $reservation;
    }

    protected function assertSameReservation(ActiveBaharReservation $r,string $payer,int $amount,string $type,int|string $id): ActiveBaharReservation
    {
        $r->loadMissing('payerAccount');
        if ($r->payerAccount?->account_number!==$payer || (int)$r->amount!==$amount || $r->reference_type!==$type || $r->reference_id!==(string)$id) throw new RuntimeException('Reservation idempotency key conflicts with an existing reservation.');
        return $r;
    }

    protected function assertPositive(int $amount): void { if ($amount<=0) throw new \InvalidArgumentException('Amount must be positive integer Gol.'); }
    protected function assertKey(string $key): void { if (trim($key)==='') throw new \InvalidArgumentException('Idempotency key is required.'); }
}
