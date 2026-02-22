<?php

namespace App\Observers\NajmHoda;

use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class NajmBaharGenericModelObserver
{
    /**
     * @var array<class-string<Model>, string>
     */
    protected array $entityMap = [
        \App\Modules\NajmBahar\Models\Account::class => 'account',
        \App\Modules\NajmBahar\Models\SubAccount::class => 'sub_account',
        \App\Modules\NajmBahar\Models\LedgerEntry::class => 'ledger_entry',
        \App\Modules\NajmBahar\Models\Fee::class => 'fee',
        \App\Modules\NajmBahar\Models\SalaryRule::class => 'salary_rule',
        \App\Modules\NajmBahar\Models\SalaryRun::class => 'salary_run',
        \App\Modules\NajmBahar\Models\SalaryRunItem::class => 'salary_run_item',
        \App\Modules\NajmBahar\Models\Project::class => 'project',
        \App\Modules\NajmBahar\Models\ProjectReview::class => 'project_review',
        \App\Modules\NajmBahar\Models\ProjectCategory::class => 'project_category',
    ];

    /**
     * @var array<string, array<int, string>>
     */
    protected array $fieldMap = [
        'account' => ['account_number', 'user_id', 'type', 'balance', 'status'],
        'sub_account' => ['account_id', 'sub_account_code', 'balance', 'status'],
        'ledger_entry' => ['transaction_id', 'account_id', 'amount', 'entry_type'],
        'fee' => ['name', 'type', 'transaction_type', 'is_active', 'fixed_amount', 'percentage'],
        'salary_rule' => ['name', 'rule_type', 'group_id', 'user_id', 'role_code', 'amount_gol', 'is_active', 'requires_senior_approval'],
        'salary_run' => ['run_date', 'period_start', 'period_end', 'status', 'created_by'],
        'salary_run_item' => ['run_id', 'rule_id', 'group_id', 'user_id', 'amount_gol', 'status', 'transaction_id'],
        'project' => ['owner_type', 'owner_id', 'title', 'project_type', 'investment_method', 'required_capital', 'status', 'assigned_to_type', 'assigned_to_id'],
        'project_review' => ['project_id', 'reviewer_id', 'action'],
        'project_category' => ['name', 'parent_id', 'level', 'status'],
    ];

    public function created(Model $model): void
    {
        $entity = $this->entityName($model);
        if ($entity === null) {
            return;
        }

        $this->emit($entity, 'created', $model, [
            'changes' => $this->changes($model),
        ]);
    }

    public function updated(Model $model): void
    {
        $entity = $this->entityName($model);
        if ($entity === null) {
            return;
        }

        $changes = $this->changes($model);
        $this->emit($entity, 'updated', $model, [
            'changes' => $changes,
        ]);

        if ($this->hasFieldChange($changes, ['status', 'is_active', 'assignment_status', 'action'])) {
            $field = $this->firstChangedField($changes, ['status', 'is_active', 'assignment_status', 'action']);
            if ($field !== null) {
                $this->emit($entity, 'status_changed', $model, [
                    'field' => $field,
                    'from' => $model->getOriginal($field),
                    'to' => $model->getAttribute($field),
                ]);
            }
        }
    }

    public function deleted(Model $model): void
    {
        $entity = $this->entityName($model);
        if ($entity === null) {
            return;
        }

        $this->emit($entity, 'deleted', $model, []);
    }

    protected function entityName(Model $model): ?string
    {
        return $this->entityMap[$model::class] ?? null;
    }

    /**
     * @param array<string, mixed> $extra
     */
    protected function emit(string $entity, string $action, Model $model, array $extra): void
    {
        $id = $model->getKey();
        $idValue = is_numeric($id) ? (int) $id : (string) $id;
        $selected = Arr::only($model->getAttributes(), $this->fieldMap[$entity] ?? []);

        $this->bus()->emit("najm_hoda.input.najm_bahar.{$entity}.{$action}", array_merge([
            'entity' => $entity,
            'model' => $model::class,
            'model_id' => $idValue,
            'data' => $selected,
            'scope' => 'economy:najm-bahar',
            'risk' => in_array($entity, ['ledger_entry', 'account', 'sub_account'], true) ? 'medium' : 'low',
        ], $extra));
    }

    /**
     * @return array<string, mixed>
     */
    protected function changes(Model $model): array
    {
        return array_merge($model->getChanges(), $model->getDirty());
    }

    /**
     * @param array<string, mixed> $changes
     * @param array<int, string> $fields
     */
    protected function hasFieldChange(array $changes, array $fields): bool
    {
        foreach ($fields as $field) {
            if (array_key_exists($field, $changes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $changes
     * @param array<int, string> $fields
     */
    protected function firstChangedField(array $changes, array $fields): ?string
    {
        foreach ($fields as $field) {
            if (array_key_exists($field, $changes)) {
                return $field;
            }
        }

        return null;
    }

    protected function bus(): RuntimeEventBus
    {
        /** @var RuntimeEventBus $bus */
        $bus = app(RuntimeEventBus::class);
        return $bus;
    }
}

