<?php

namespace App\Modules\NajmBahar\Services;

use App\Models\GroupUser;
use App\Models\User;
use App\Modules\NajmBahar\Models\SalaryRule;
use App\Modules\NajmBahar\Models\SalaryRun;
use App\Modules\NajmBahar\Models\SalaryRunItem;
use App\Modules\NajmBahar\Services\AccountNumberService;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\TransactionService;
use App\Services\NajmHoda\Runtime\NajmHodaDomainEventPolicyLinkService;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class SalaryService
{
    public function createRun(Carbon $runDate, ?int $createdBy = null): SalaryRun
    {
        $context = [
            'run_date' => $runDate->toDateString(),
            'created_by' => $createdBy,
            'scope' => 'economy:najm-bahar',
            'risk' => 'medium',
        ];
        $this->emitRuntime('najm_hoda.input.najm_bahar.service.salary_run.create.requested', $context);

        $periodStart = $runDate->copy()->startOfMonth()->toDateString();
        $periodEnd = $runDate->copy()->endOfMonth()->toDateString();

        try {
            $run = SalaryRun::create([
                'run_date' => $runDate->toDateString(),
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'status' => 'pending',
                'created_by' => $createdBy,
            ]);

            $rules = SalaryRule::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->get();

            foreach ($rules as $rule) {
                if (! $this->isRuleDue($rule, $runDate)) {
                    continue;
                }

                $itemsCreated = $this->createItemsForRule($run, $rule, $runDate);

                if ($itemsCreated > 0) {
                    $rule->last_run_at = $runDate;
                    $rule->save();
                }
            }

            $this->emitRuntime('najm_hoda.input.najm_bahar.service.salary_run.create.succeeded', array_merge($context, [
                'salary_run_id' => (int) $run->id,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ]));

            return $run;
        } catch (Throwable $exception) {
            $this->emitRuntime('najm_hoda.input.najm_bahar.service.salary_run.create.failed', array_merge($context, [
                'error' => $exception->getMessage(),
            ]));
            throw $exception;
        }
    }

    public function processRun(SalaryRun $run, ?int $processedBy = null): array
    {
        $context = [
            'salary_run_id' => (int) $run->id,
            'processed_by' => $processedBy,
            'scope' => 'economy:najm-bahar',
            'risk' => 'medium',
        ];
        $this->emitRuntime('najm_hoda.input.najm_bahar.service.salary_run.process.requested', $context);

        $processed = 0;
        $failed = 0;
        $blocked = 0;

        try {
            $items = SalaryRunItem::where('run_id', $run->id)->orderBy('id')->get();

            foreach ($items as $item) {
                $status = $this->evaluateItemStatus($item);
                if ($status !== 'ready') {
                    $item->status = $status;
                    $item->save();
                    $this->emitRuntime('najm_hoda.input.najm_bahar.service.salary_run.item.blocked', array_merge($context, [
                        'salary_run_item_id' => (int) $item->id,
                        'status' => $status,
                    ]));
                    $blocked++;
                    continue;
                }

                try {
                    $this->payItem($item, $processedBy);
                    $processed++;
                } catch (Throwable $e) {
                    $item->status = 'failed';
                    $item->blocked_reason = $e->getMessage();
                    $item->save();
                    $this->emitRuntime('najm_hoda.input.najm_bahar.service.salary_run.item.failed', array_merge($context, [
                        'salary_run_item_id' => (int) $item->id,
                        'error' => $e->getMessage(),
                    ]));
                    $failed++;
                }
            }

            $run->status = $failed > 0 ? 'failed' : 'processed';
            $run->save();

            $result = [
                'processed' => $processed,
                'blocked' => $blocked,
                'failed' => $failed,
            ];
            $this->emitRuntime('najm_hoda.input.najm_bahar.service.salary_run.process.succeeded', array_merge($context, $result, [
                'run_status' => (string) $run->status,
            ]));
            return $result;
        } catch (Throwable $exception) {
            $this->emitRuntime('najm_hoda.input.najm_bahar.service.salary_run.process.failed', array_merge($context, [
                'error' => $exception->getMessage(),
            ]));
            throw $exception;
        }
    }

    public function refreshItemStatus(SalaryRunItem $item): SalaryRunItem
    {
        $item->status = $this->evaluateItemStatus($item);
        $item->blocked_reason = $item->status === 'ready' ? null : $this->statusReason($item);
        $item->save();

        return $item;
    }

    private function createItemsForRule(SalaryRun $run, SalaryRule $rule, Carbon $runDate): int
    {
        $count = 0;
        $recipients = $this->resolveRecipients($rule);

        foreach ($recipients as $recipient) {
            $period = $this->resolvePeriod($rule, $runDate);
            $item = new SalaryRunItem();
            $item->run_id = $run->id;
            $item->rule_id = $rule->id;
            $item->group_id = $recipient['group_id'] ?? $rule->group_id;
            $item->user_id = $recipient['user_id'];
            $item->role_code = $recipient['role_code'] ?? $rule->role_code;
            $item->project_id = $recipient['project_id'] ?? $rule->project_id;
            $item->period_start = $period['start'];
            $item->period_end = $period['end'];
            $item->amount_gol = $rule->amount_gol;
            $item->activity_threshold = $rule->min_activity_score;
            $item->requires_senior_approval = (bool) $rule->requires_senior_approval;
            $item->status = $this->evaluateItemStatus($item);
            $item->blocked_reason = $item->status === 'ready' ? null : $this->statusReason($item);
            $item->save();
            $count++;
        }

        return $count;
    }

    private function resolveRecipients(SalaryRule $rule): array
    {
        if ($rule->rule_type === 'role') {
            $groupTypeFilters = array_values(array_filter((array) data_get($rule->meta, 'group_types', [])));
            $locationLevelFilters = $this->expandLocationLevels(
                array_values(array_filter((array) data_get($rule->meta, 'location_levels', [])))
            );

            $query = GroupUser::query()
                ->where('status', 1)
                ->whereIn('role', [2, 3]);

            if ($rule->role_code !== null) {
                $query->where('role', $rule->role_code);
            }

            if ($rule->group_id) {
                $query->where('group_id', $rule->group_id);
            }

            if (!empty($groupTypeFilters) || !empty($locationLevelFilters)) {
                $query->whereHas('group', function ($groupQuery) use ($groupTypeFilters, $locationLevelFilters) {
                    if (!empty($locationLevelFilters)) {
                        $groupQuery->whereIn('location_level', $locationLevelFilters);
                    }

                    if (!empty($groupTypeFilters)) {
                        $groupQuery->where(function ($typeQuery) use ($groupTypeFilters) {
                            foreach ($groupTypeFilters as $type) {
                                $typeQuery->orWhere(function ($subQuery) use ($type) {
                                    if ($type === 'exclusive') {
                                        $subQuery->where(function ($exclusiveQuery) {
                                            $exclusiveQuery
                                                ->whereNotNull('gender')
                                                ->orWhereNotNull('age_group_id')
                                                ->orWhereIn('group_type', [3, 4]);
                                        });
                                        return;
                                    }

                                    if ($type === 'specialty') {
                                        $subQuery->where(function ($specialtyQuery) {
                                            $specialtyQuery
                                                ->whereNotNull('specialty_id')
                                                ->orWhereNotNull('experience_id')
                                                ->orWhereIn('group_type', [1, 'speciality', 'specialized']);
                                        });
                                        return;
                                    }

                                    $subQuery
                                        ->whereNull('gender')
                                        ->whereNull('age_group_id')
                                        ->whereNull('specialty_id')
                                        ->whereNull('experience_id')
                                        ->whereNotIn('group_type', [1, 3, 4, 'speciality', 'specialized']);
                                });
                            }
                        });
                    }
                });
            }

            return $query->get()->map(function (GroupUser $groupUser) {
                return [
                    'user_id' => $groupUser->user_id,
                    'group_id' => $groupUser->group_id,
                    'role_code' => $groupUser->role,
                ];
            })->toArray();
        }

        if ($rule->user_id) {
            return [[
                'user_id' => $rule->user_id,
                'group_id' => $rule->group_id,
                'project_id' => $rule->project_id,
            ]];
        }

        return [];
    }

    private function expandLocationLevels(array $levels): array
    {
        $map = [
            'city_rural' => ['city', 'rural'],
            'region_village' => ['region', 'village'],
        ];

        $expanded = [];
        foreach ($levels as $level) {
            if (isset($map[$level])) {
                $expanded = array_merge($expanded, $map[$level]);
                continue;
            }
            $expanded[] = $level;
        }

        return array_values(array_unique($expanded));
    }

    private function resolvePeriod(SalaryRule $rule, Carbon $runDate): array
    {
        if ($rule->schedule_type === 'interval' && $rule->interval_days) {
            $end = $runDate->copy();
            $start = $runDate->copy()->subDays($rule->interval_days)->addDay();
            return [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ];
        }

        if ($rule->schedule_type === 'one_time') {
            $date = $rule->start_at ? Carbon::parse($rule->start_at) : $runDate;
            return [
                'start' => $date->toDateString(),
                'end' => $date->toDateString(),
            ];
        }

        return [
            'start' => $runDate->copy()->startOfMonth()->toDateString(),
            'end' => $runDate->copy()->endOfMonth()->toDateString(),
        ];
    }

    private function isRuleDue(SalaryRule $rule, Carbon $runDate): bool
    {
        if ($rule->start_at && $runDate->lt(Carbon::parse($rule->start_at))) {
            return false;
        }

        if ($rule->end_at && $runDate->gt(Carbon::parse($rule->end_at))) {
            return false;
        }

        if ($rule->schedule_type === 'monthly') {
            if ($rule->last_run_at && $rule->last_run_at->isSameMonth($runDate)) {
                return false;
            }
            return true;
        }

        if ($rule->schedule_type === 'interval') {
            if (! $rule->interval_days) {
                return false;
            }
            if (! $rule->last_run_at) {
                return true;
            }
            return $rule->last_run_at->diffInDays($runDate) >= $rule->interval_days;
        }

        if ($rule->schedule_type === 'one_time') {
            return $rule->last_run_at === null;
        }

        return false;
    }

    private function evaluateItemStatus(SalaryRunItem $item): string
    {
        $needsActivity = $item->activity_threshold !== null && $item->activity_threshold > 0;
        $needsApproval = (bool) $item->requires_senior_approval;

        if ($needsActivity) {
            if ($item->activity_score === null) {
                return 'blocked';
            }

            if ((int) $item->activity_score < (int) $item->activity_threshold) {
                return 'blocked';
            }
        }

        if ($needsApproval && ! $item->senior_approved_at) {
            return 'blocked';
        }

        return 'ready';
    }

    private function statusReason(SalaryRunItem $item): string
    {
        if ($item->activity_threshold !== null && $item->activity_threshold > 0) {
            if ($item->activity_score === null) {
                return 'activity_score_required';
            }
            if ((int) $item->activity_score < (int) $item->activity_threshold) {
                return 'activity_score_low';
            }
        }

        if ($item->requires_senior_approval && ! $item->senior_approved_at) {
            return 'senior_approval_required';
        }

        return 'ready';
    }

    private function payItem(SalaryRunItem $item, ?int $processedBy = null): void
    {
        $accountService = app(AccountService::class);
        $transactionService = app(TransactionService::class);

        $systemAccountNumber = AccountNumberService::makeSystemAccountNumber();
        $membershipCode = AccountNumberService::makeSubAccountCode($systemAccountNumber, 1);
        $systemSubAccount = $accountService->getSystemSubAccountByCode($membershipCode);
        $fromAccountNumber = $systemSubAccount?->sub_account_code ?? $membershipCode;

        $userAccountNumber = AccountNumberService::makeMainAccountNumberForUser($item->user_id);
        if (! $accountService->getMainAccountForUser($item->user_id)) {
            $accountService->createMainAccountForUser($item->user_id);
        }

        $description = 'پرداخت حقوق از حساب حق عضویت';
        if ($item->project_id) {
            $description .= ' - پروژه ' . $item->project_id;
        }

        $meta = [
            'salary_run_id' => $item->run_id,
            'salary_rule_id' => $item->rule_id,
            'salary_item_id' => $item->id,
            'processed_by' => $processedBy,
        ];

        $tx = $transactionService->transfer(
            $fromAccountNumber,
            $userAccountNumber,
            $item->amount_gol,
            $description,
            $meta
        );

        $item->transaction_id = $tx->id;
        $item->status = 'paid';
        $item->blocked_reason = null;
        $item->save();

        $this->emitRuntime('najm_hoda.input.najm_bahar.service.salary_run.item.paid', [
            'salary_run_id' => (int) $item->run_id,
            'salary_run_item_id' => (int) $item->id,
            'transaction_id' => (int) $tx->id,
            'user_id' => (int) $item->user_id,
            'amount_gol' => (int) $item->amount_gol,
            'processed_by' => $processedBy,
            'scope' => 'economy:najm-bahar',
            'risk' => 'medium',
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function emitRuntime(string $event, array $payload): void
    {
        try {
            /** @var RuntimeEventBus $bus */
            $bus = app(RuntimeEventBus::class);
            $bus->emit($event, $payload);
            /** @var NajmHodaDomainEventPolicyLinkService $link */
            $link = app(NajmHodaDomainEventPolicyLinkService::class);
            $link->ingest($event, $payload);
        } catch (Throwable) {
            // Do not fail salary processing because of telemetry emission.
        }
    }
}
