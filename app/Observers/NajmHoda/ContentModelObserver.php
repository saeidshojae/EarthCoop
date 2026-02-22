<?php

namespace App\Observers\NajmHoda;

use App\Services\NajmHoda\Runtime\NajmHodaDomainEventPolicyLinkService;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class ContentModelObserver
{
    public function created(Model $model): void
    {
        $this->emit($model, 'created', 'low');
    }

    public function updated(Model $model): void
    {
        $this->emit($model, 'updated', 'low');
    }

    public function deleted(Model $model): void
    {
        $this->emit($model, 'deleted', 'medium');
    }

    protected function emit(Model $model, string $outcome, string $risk): void
    {
        $modelName = $this->resolveModelName($model);
        $event = 'najm_hoda.input.content.service.' . $modelName . '.' . $outcome;
        $payload = [
            'model' => $modelName,
            'model_id' => (int) $model->getKey(),
            'scope' => 'content',
            'risk' => $risk,
        ];

        try {
            /** @var RuntimeEventBus $bus */
            $bus = app(RuntimeEventBus::class);
            $bus->emit($event, $payload);

            /** @var NajmHodaDomainEventPolicyLinkService $link */
            $link = app(NajmHodaDomainEventPolicyLinkService::class);
            $link->ingest($event, $payload);
        } catch (Throwable) {
            // no-op
        }
    }

    protected function resolveModelName(Model $model): string
    {
        $base = class_basename($model);
        $normalized = preg_replace('/(?<!^)[A-Z]/', '_$0', $base);

        return strtolower((string) $normalized);
    }
}

