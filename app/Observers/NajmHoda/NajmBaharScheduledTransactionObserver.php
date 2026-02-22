<?php

namespace App\Observers\NajmHoda;

use App\Modules\NajmBahar\Models\ScheduledTransaction;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;

class NajmBaharScheduledTransactionObserver
{
    public function created(ScheduledTransaction $scheduledTransaction): void
    {
        $this->bus()->emit('najm_hoda.input.najm_bahar.scheduled_transaction.created', [
            'scheduled_transaction_id' => (int) $scheduledTransaction->id,
            'transaction_id' => $scheduledTransaction->transaction_id !== null ? (int) $scheduledTransaction->transaction_id : null,
            'status' => (string) ($scheduledTransaction->status ?? 'pending'),
            'attempts' => (int) ($scheduledTransaction->attempts ?? 0),
            'scope' => 'economy:najm-bahar',
            'risk' => 'medium',
        ]);
    }

    public function updated(ScheduledTransaction $scheduledTransaction): void
    {
        $changes = array_merge($scheduledTransaction->getChanges(), $scheduledTransaction->getDirty());

        if (array_key_exists('status', $changes)) {
            $this->bus()->emit('najm_hoda.input.najm_bahar.scheduled_transaction.status_changed', [
                'scheduled_transaction_id' => (int) $scheduledTransaction->id,
                'transaction_id' => $scheduledTransaction->transaction_id !== null ? (int) $scheduledTransaction->transaction_id : null,
                'from_status' => (string) $scheduledTransaction->getOriginal('status'),
                'to_status' => (string) ($scheduledTransaction->status ?? ''),
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
