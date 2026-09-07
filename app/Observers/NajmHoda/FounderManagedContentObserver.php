<?php

namespace App\Observers\NajmHoda;

use App\Models\EmailTemplate;
use App\Modules\Blog\Models\Post;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Illuminate\Database\Eloquent\Model;

class FounderManagedContentObserver
{
    public function created(Model $model): void
    {
        $this->emit($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->emit($model, 'updated', array_keys($model->getChanges()));
    }

    public function deleted(Model $model): void
    {
        $this->emit($model, 'deleted');
    }

    protected function emit(Model $model, string $operation, array $changedFields = []): void
    {
        $descriptor = $this->descriptor($model);
        if ($descriptor === null) {
            return;
        }

        // Deliberately exclude body/content and recipient data from runtime events.
        $payload = [
            'domain' => $descriptor['domain'],
            'entity_type' => $descriptor['entity_type'],
            'entity_id' => (int) $model->getKey(),
            'operation' => $operation,
            'status' => $descriptor['status'],
            'changed_fields' => array_values(array_diff($changedFields, ['body', 'content'])),
            'scope' => 'founder_operations',
            'category' => $descriptor['category'],
            'risk' => $descriptor['risk'],
            'action_required' => false,
        ];

        if ($model instanceof EmailTemplate) {
            $payload['is_active'] = (bool) $model->is_active;
            $payload['template_category'] = $model->category !== null ? (string) $model->category : null;
        }

        if ($model instanceof Post) {
            $payload['author_id'] = $model->user_id !== null ? (int) $model->user_id : null;
            $payload['is_featured'] = (bool) $model->is_featured;
            $payload['published_at'] = optional($model->published_at)->toIso8601String();
        }

        $this->bus()->emit(
            'najm_hoda.input.' . $descriptor['domain'] . '.' . $operation,
            $payload
        );
    }

    protected function descriptor(Model $model): ?array
    {
        return match (true) {
            $model instanceof EmailTemplate => [
                'domain' => 'email',
                'entity_type' => 'email_template',
                'category' => 'communications',
                'risk' => 'medium',
                'status' => $model->is_active ? 'active' : 'inactive',
            ],
            $model instanceof Post => [
                'domain' => 'blog',
                'entity_type' => 'blog_post',
                'category' => 'editorial',
                'risk' => 'medium',
                'status' => (string) ($model->status ?? 'unknown'),
            ],
            default => null,
        };
    }

    protected function bus(): RuntimeEventBus
    {
        /** @var RuntimeEventBus $bus */
        $bus = app(RuntimeEventBus::class);
        return $bus;
    }
}
