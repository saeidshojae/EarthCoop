<?php

namespace App\Observers\NajmHoda;

use App\Modules\NajmBahar\Models\Transaction;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;

class NajmBaharTransactionObserver
{
    public function created(Transaction $transaction): void
    {
        $this->bus()->emit('najm_hoda.input.najm_bahar.transaction.created', [
            'transaction_id' => (int) $transaction->id,
            'tracking_number' => (string) ($transaction->tracking_number ?? ''),
            'from_account_id' => $transaction->from_account_id !== null ? (int) $transaction->from_account_id : null,
            'to_account_id' => $transaction->to_account_id !== null ? (int) $transaction->to_account_id : null,
            'amount' => (int) ($transaction->amount ?? 0),
            'type' => (string) ($transaction->type ?? 'unknown'),
            'status' => (string) ($transaction->status ?? 'pending'),
            'scope' => 'economy:najm-bahar',
            'risk' => 'medium',
        ]);
    }

    public function updated(Transaction $transaction): void
    {
        $changes = array_merge($transaction->getChanges(), $transaction->getDirty());

        if (array_key_exists('status', $changes)) {
            $this->bus()->emit('najm_hoda.input.najm_bahar.transaction.status_changed', [
                'transaction_id' => (int) $transaction->id,
                'tracking_number' => (string) ($transaction->tracking_number ?? ''),
                'from_status' => (string) $transaction->getOriginal('status'),
                'to_status' => (string) ($transaction->status ?? ''),
                'scope' => 'economy:najm-bahar',
                'risk' => 'medium',
            ]);
        }
    }

    protected function bus(): RuntimeEventBus
    {
        /** @var RuntimeEventBus $bus */
        $bus = app(RuntimeEventBus::class);
        return $bus;
    }
}
