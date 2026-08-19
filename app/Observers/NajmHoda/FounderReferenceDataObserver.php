<?php

namespace App\Observers\NajmHoda;

use App\Models\ExperienceField;
use App\Models\OccupationalField;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Illuminate\Database\Eloquent\Model;

class FounderReferenceDataObserver
{
    public function created(Model $model): void
    {
        if ((int) ($model->status ?? 0) !== 0) {
            return;
        }

        $this->bus()->emit('najm_hoda.input.founder.reference.pending', [
            'reference_type' => $this->referenceType($model),
            'reference_id' => (int) $model->getKey(),
            'name' => (string) ($model->name ?? ''),
            'parent_id' => $model->parent_id !== null ? (int) $model->parent_id : null,
            'status' => (int) ($model->status ?? 0),
            'scope' => 'founder_operations',
            'category' => 'reference_approval',
            'risk' => 'low',
            'action_required' => true,
        ]);
    }

    public function updated(Model $model): void
    {
        if (! array_key_exists('status', $model->getChanges())) {
            return;
        }

        $this->bus()->emit('najm_hoda.input.founder.reference.status_changed', [
            'reference_type' => $this->referenceType($model),
            'reference_id' => (int) $model->getKey(),
            'name' => (string) ($model->name ?? ''),
            'from_status' => (int) $model->getOriginal('status'),
            'to_status' => (int) ($model->status ?? 0),
            'scope' => 'founder_operations',
            'category' => 'reference_approval',
            'risk' => 'low',
            'action_required' => false,
        ]);
    }

    protected function referenceType(Model $model): string
    {
        return match (true) {
            $model instanceof ExperienceField => 'experience',
            $model instanceof OccupationalField => 'occupational',
            default => class_basename($model),
        };
    }

    protected function bus(): RuntimeEventBus
    {
        /** @var RuntimeEventBus $bus */
        $bus = app(RuntimeEventBus::class);

        return $bus;
    }
}
