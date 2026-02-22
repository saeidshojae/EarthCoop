<?php

namespace App\Observers\NajmHoda;

use App\Modules\NajmBahar\Models\Investment;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;

class NajmBaharInvestmentObserver
{
    public function created(Investment $investment): void
    {
        $this->bus()->emit('najm_hoda.input.najm_bahar.investment.created', [
            'investment_id' => (int) $investment->id,
            'project_id' => $investment->project_id !== null ? (int) $investment->project_id : null,
            'transaction_id' => $investment->transaction_id !== null ? (int) $investment->transaction_id : null,
            'investor_type' => (string) ($investment->investor_type ?? ''),
            'investor_id' => $investment->investor_id !== null ? (int) $investment->investor_id : null,
            'amount' => (int) ($investment->amount ?? 0),
            'status' => (string) ($investment->status ?? 'pending'),
            'scope' => 'economy:najm-bahar',
            'risk' => 'medium',
        ]);
    }

    public function updated(Investment $investment): void
    {
        $changes = array_merge($investment->getChanges(), $investment->getDirty());

        if (array_key_exists('status', $changes)) {
            $this->bus()->emit('najm_hoda.input.najm_bahar.investment.status_changed', [
                'investment_id' => (int) $investment->id,
                'project_id' => $investment->project_id !== null ? (int) $investment->project_id : null,
                'from_status' => (string) $investment->getOriginal('status'),
                'to_status' => (string) ($investment->status ?? ''),
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
