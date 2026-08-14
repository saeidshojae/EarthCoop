<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GroupRoleBulkOperation;
use App\Models\GroupRoleBulkOperationItem;
use App\Models\GroupUser;
use App\Services\TemporaryGroupRoleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GlobalGroupRoleController extends Controller
{
    private const LEVELS = [
        'global', 'continent', 'country', 'province', 'county', 'section',
        'city', 'rural', 'region', 'village', 'neighborhood', 'street', 'alley',
    ];

    public function index()
    {
        $operations = GroupRoleBulkOperation::query()
            ->with('creator:id,first_name,last_name,email')
            ->latest()
            ->limit(20)
            ->get();

        return view('admin.groups.global-roles', compact('operations'));
    }

    public function preview(Request $request)
    {
        $validated = $this->validateInput($request, false);
        $query = $this->candidateQuery($validated);

        $memberships = (clone $query)->count('group_user.id');
        $groups = (clone $query)->distinct()->count('group_user.group_id');
        $cancellations = (clone $query)
            ->where('group_user.role_override_active', true)
            ->whereColumn('group_user.role_override_original_role', '!=', 'group_user.role')
            ->where('group_user.role_override_original_role', (int) $validated['target_role'])
            ->count('group_user.id');

        return response()->json([
            'groups' => $groups,
            'memberships' => $memberships,
            'will_apply' => max(0, $memberships - $cancellations),
            'will_cancel' => $cancellations,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateInput($request, true);
        $operation = DB::transaction(function () use ($validated, $request) {
            $operation = GroupRoleBulkOperation::create([
                'created_by' => $request->user()->id,
                'filters' => [
                    'group_category' => $validated['group_category'],
                    'location_level' => $validated['location_level'] ?? null,
                ],
                'source_role' => (int) $validated['source_role'],
                'target_role' => (int) $validated['target_role'],
                'duration_unit' => $validated['duration_unit'],
                'duration_value' => $validated['duration_value'] ?? null,
                'status' => 'pending',
            ]);

            $total = 0;
            $this->candidateQuery($validated)
                ->select(['group_user.id', 'group_user.group_id', 'group_user.user_id'])
                ->orderBy('group_user.id')
                ->chunk(1000, function ($memberships) use ($operation, &$total) {
                    $now = now();
                    $rows = $memberships->map(fn ($membership) => [
                        'operation_id' => $operation->id,
                        'membership_id' => $membership->id,
                        'group_id' => $membership->group_id,
                        'user_id' => $membership->user_id,
                        'status' => 'pending',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all();
                    GroupRoleBulkOperationItem::insert($rows);
                    $total += count($rows);
                });

            $operation->update([
                'total_items' => $total,
                'status' => $total > 0 ? 'pending' : 'completed',
                'completed_at' => $total > 0 ? null : now(),
            ]);

            return $operation->refresh();
        });

        return response()->json($this->operationPayload($operation), 201);
    }

    public function process(Request $request, GroupRoleBulkOperation $operation, TemporaryGroupRoleService $roleService)
    {
        if ($operation->status === 'completed') {
            return response()->json($this->operationPayload($operation));
        }

        $itemIds = DB::transaction(function () use ($operation) {
            $locked = GroupRoleBulkOperation::query()->lockForUpdate()->findOrFail($operation->id);
            $locked->items()
                ->where('status', 'processing')
                ->where('updated_at', '<=', now()->subMinutes(5))
                ->update(['status' => 'pending', 'updated_at' => now()]);
            $ids = $locked->items()->where('status', 'pending')->orderBy('id')->limit(100)->pluck('id');
            if ($ids->isNotEmpty()) {
                GroupRoleBulkOperationItem::whereIn('id', $ids)->update(['status' => 'processing', 'updated_at' => now()]);
                $locked->update(['status' => 'processing', 'started_at' => $locked->started_at ?: now()]);
            }

            return $ids;
        });

        $expiresAt = $this->expiryFor($operation->duration_unit, $operation->duration_value);
        foreach (GroupRoleBulkOperationItem::whereIn('id', $itemIds)->orderBy('id')->get() as $item) {
            try {
                $membership = GroupUser::query()->find($item->membership_id);
                if (!$membership || !$membership->status) {
                    $item->update(['status' => 'done', 'result' => 'skipped']);
                    continue;
                }

                $roleService->restoreIfExpired($membership);
                $membership->refresh();
                if ((int) $membership->role !== (int) $operation->source_role) {
                    $item->update(['status' => 'done', 'result' => 'skipped', 'error' => null]);
                    continue;
                }
                $result = $membership->role_override_active
                    && (int) $membership->role_override_original_role === (int) $operation->target_role
                        ? 'cancelled'
                        : 'applied';

                $roleService->apply(
                    $membership,
                    (int) $operation->target_role,
                    $expiresAt,
                    $request->user(),
                    'system_admin_bulk'
                );
                $item->update(['status' => 'done', 'result' => $result, 'error' => null]);
            } catch (\Throwable $exception) {
                report($exception);
                $item->update(['status' => 'failed', 'result' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 1000)]);
            }
        }

        $this->refreshCounters($operation);

        return response()->json($this->operationPayload($operation->refresh()));
    }

    public function status(GroupRoleBulkOperation $operation)
    {
        $this->refreshCounters($operation);

        return response()->json($this->operationPayload($operation->refresh()));
    }

    private function candidateQuery(array $validated): Builder
    {
        $query = GroupUser::query()
            ->join('groups', 'groups.id', '=', 'group_user.group_id')
            ->whereNull('group_user.deleted_at')
            ->where('group_user.status', 1)
            ->where('group_user.role', (int) $validated['source_role']);

        $types = match ($validated['group_category']) {
            'general' => [0, '0', 'general'],
            'specialized' => [1, '1', 2, '2', 'speciality', 'specialized'],
            'exclusive' => [3, '3', 4, '4', 'exclusive'],
            default => null,
        };
        if ($types !== null) {
            $query->whereIn('groups.group_type', $types);
        }
        if (!empty($validated['location_level'])) {
            $query->where('groups.location_level', $validated['location_level']);
        }

        return $query;
    }

    private function validateInput(Request $request, bool $withDuration): array
    {
        $rules = [
            'group_category' => ['required', Rule::in(['all', 'general', 'specialized', 'exclusive'])],
            'location_level' => ['nullable', Rule::in(self::LEVELS)],
            'source_role' => ['required', 'integer', Rule::in([0, 1])],
            'target_role' => ['required', 'integer', Rule::in([0, 1]), 'different:source_role'],
        ];
        if ($withDuration) {
            $rules += [
                'duration_unit' => ['required', Rule::in(['day', 'month', 'unlimited'])],
                'duration_value' => ['nullable', 'integer', 'min:1', 'max:31', 'required_unless:duration_unit,unlimited'],
            ];
        }

        $validated = $request->validate($rules);
        if ($withDuration && $validated['duration_unit'] === 'month' && (int) $validated['duration_value'] > 12) {
            abort(422, 'مدت ماهانه حداکثر ۱۲ ماه است.');
        }

        return $validated;
    }

    private function expiryFor(string $unit, ?int $value): ?\Carbon\CarbonInterface
    {
        return match ($unit) {
            'day' => now()->addDays((int) $value),
            'month' => now()->addMonthsNoOverflow((int) $value),
            default => null,
        };
    }

    private function refreshCounters(GroupRoleBulkOperation $operation): void
    {
        $counts = $operation->items()->selectRaw('result, COUNT(*) as aggregate')->groupBy('result')->pluck('aggregate', 'result');
        $processed = $operation->items()->whereIn('status', ['done', 'failed'])->count();
        $hasWork = $operation->items()->whereIn('status', ['pending', 'processing'])->exists();
        $operation->update([
            'processed_items' => $processed,
            'applied_items' => (int) ($counts['applied'] ?? 0),
            'cancelled_items' => (int) ($counts['cancelled'] ?? 0),
            'skipped_items' => (int) ($counts['skipped'] ?? 0),
            'failed_items' => (int) ($counts['failed'] ?? 0),
            'status' => $hasWork ? 'processing' : 'completed',
            'completed_at' => $hasWork ? null : ($operation->completed_at ?: now()),
        ]);
    }

    private function operationPayload(GroupRoleBulkOperation $operation): array
    {
        return [
            'id' => $operation->id,
            'status' => $operation->status,
            'total' => $operation->total_items,
            'processed' => $operation->processed_items,
            'applied' => $operation->applied_items,
            'cancelled' => $operation->cancelled_items,
            'skipped' => $operation->skipped_items,
            'failed' => $operation->failed_items,
            'percent' => $operation->total_items > 0
                ? (int) floor(($operation->processed_items / $operation->total_items) * 100)
                : 100,
        ];
    }
}
