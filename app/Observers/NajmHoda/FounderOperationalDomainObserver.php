<?php

namespace App\Observers\NajmHoda;

use App\Models\Announcement;
use App\Models\Election;
use App\Models\Group;
use App\Models\GroupSetting;
use App\Models\NotificationSetting;
use App\Models\ReportedMessage;
use App\Models\Setting;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

class FounderOperationalDomainObserver
{
    public function created(Model $model): void { $this->emit($model, 'created'); }
    public function updated(Model $model): void { $this->emit($model, 'updated', array_keys($model->getChanges())); }
    public function deleted(Model $model): void { $this->emit($model, 'deleted'); }

    protected function emit(Model $model, string $operation, array $changedFields = []): void
    {
        $descriptor = $this->descriptor($model);
        if ($descriptor === null) return;

        $payload = [
            'domain' => $descriptor['domain'],
            'entity_type' => $descriptor['entity_type'],
            'entity_id' => (int) $model->getKey(),
            'operation' => $operation,
            'changed_fields' => array_values(array_diff($changedFields, [
                'content', 'reason', 'description', 'admin_note', 'manager_note',
                'welcome_content', 'home_content', 'najm_summary',
            ])),
            'scope' => 'founder_operations',
            'category' => $descriptor['category'],
            'risk' => $descriptor['risk'],
            'action_required' => false,
        ];

        if ($model instanceof Group) {
            $payload['group_type'] = $model->group_type;
            $payload['location_level'] = $model->location_level;
            $payload['is_open'] = (bool) $model->is_open;
        } elseif ($model instanceof Election) {
            $payload['group_id'] = (int) $model->group_id;
            $payload['is_closed'] = (bool) $model->is_closed;
            $payload['starts_at'] = $this->dateValue($model->starts_at);
            $payload['ends_at'] = $this->dateValue($model->ends_at);
        } elseif ($model instanceof ReportedMessage) {
            $payload['group_id'] = $model->group_id !== null ? (int) $model->group_id : null;
            $payload['message_id'] = $model->message_id !== null ? (int) $model->message_id : null;
            $payload['status'] = (string) ($model->status ?? 'unknown');
            $payload['escalated_to_admin'] = (bool) $model->escalated_to_admin;
        } elseif ($model instanceof Announcement) {
            $payload['group_level'] = $model->group_level;
            $payload['should_pin'] = (bool) $model->should_pin;
            $payload['created_by'] = $model->created_by !== null ? (int) $model->created_by : null;
        } elseif ($model instanceof NotificationSetting) {
            $payload['user_id'] = (int) $model->user_id;
        } elseif ($model instanceof GroupSetting) {
            $payload['level'] = (string) $model->level;
            $payload['election_status'] = $model->election_status;
            $payload['max_for_election'] = $model->max_for_election;
        } elseif ($model instanceof Setting) {
            $payload['settings_record'] = (int) $model->getKey();
        }

        $this->bus()->emit('najm_hoda.input.' . $descriptor['domain'] . '.' . $operation, $payload);
    }

    protected function descriptor(Model $model): ?array
    {
        return match (true) {
            $model instanceof Group => ['domain' => 'groups', 'entity_type' => 'group', 'category' => 'community_operations', 'risk' => 'medium'],
            $model instanceof Election => ['domain' => 'governance', 'entity_type' => 'election', 'category' => 'elections', 'risk' => 'high'],
            $model instanceof ReportedMessage => ['domain' => 'moderation', 'entity_type' => 'reported_message', 'category' => 'moderation', 'risk' => 'high'],
            $model instanceof Announcement => ['domain' => 'notification', 'entity_type' => 'announcement', 'category' => 'communications', 'risk' => 'medium'],
            $model instanceof NotificationSetting => ['domain' => 'notification', 'entity_type' => 'notification_setting', 'category' => 'preferences', 'risk' => 'low'],
            $model instanceof GroupSetting => ['domain' => 'admin_settings', 'entity_type' => 'group_setting', 'category' => 'system_configuration', 'risk' => 'high'],
            $model instanceof Setting => ['domain' => 'admin_settings', 'entity_type' => 'system_setting', 'category' => 'system_configuration', 'risk' => 'high'],
            default => null,
        };
    }

    protected function dateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        try { return CarbonImmutable::parse($value)->toIso8601String(); }
        catch (\Throwable) { return is_scalar($value) ? (string) $value : null; }
    }

    protected function bus(): RuntimeEventBus
    {
        /** @var RuntimeEventBus $bus */
        $bus = app(RuntimeEventBus::class);
        return $bus;
    }
}
