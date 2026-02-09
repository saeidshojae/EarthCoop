<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\BaharMoney;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\NajmBahar\Models\SalaryRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NajmBaharSalaryRuleController extends Controller
{
    public function index(Request $request)
    {
        $query = SalaryRule::query();

        $groupTypeOptions = $this->groupTypeOptions();
        $locationLevelOptions = $this->locationLevelOptions();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('rule_type')) {
            $query->where('rule_type', $request->input('rule_type'));
        }

        if ($request->filled('schedule_type')) {
            $query->where('schedule_type', $request->input('schedule_type'));
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            $query->where('is_active', $status === 'active');
        }

        $groupTypes = array_values(array_filter((array) $request->input('group_types', [])));
        if (!empty($groupTypes)) {
            $query->where(function ($typeQuery) use ($groupTypes) {
                foreach ($groupTypes as $type) {
                    $typeQuery->orWhereJsonContains('meta->group_types', $type);
                }
            });
        }

        $locationLevels = array_values(array_filter((array) $request->input('location_levels', [])));
        if (!empty($locationLevels)) {
            $query->where(function ($levelQuery) use ($locationLevels) {
                foreach ($locationLevels as $level) {
                    $levelQuery->orWhereJsonContains('meta->location_levels', $level);
                }
            });
        }

        $rules = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('admin.najm-bahar.salaries.index', compact('rules', 'groupTypeOptions', 'locationLevelOptions'));
    }

    public function create()
    {
        $users = User::orderBy('first_name')->orderBy('last_name')->get();
        $groupTypeOptions = $this->groupTypeOptions();
        $locationLevelOptions = $this->locationLevelOptions();

        return view('admin.najm-bahar.salaries.create', compact('users', 'groupTypeOptions', 'locationLevelOptions'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateRule($request);

        $validated['amount_gol'] = BaharMoney::parseToGol($validated['amount']);
        unset($validated['amount']);

        $validated['meta'] = $this->buildRuleMeta($request);

        if ($validated['rule_type'] === 'role') {
            $validated['requires_senior_approval'] = false;
        }

        SalaryRule::create($validated);

        return redirect()->route('admin.najm-bahar.salaries.index')
            ->with('success', 'قانون پرداخت با موفقیت ایجاد شد.');
    }

    public function edit(SalaryRule $salaryRule)
    {
        $users = User::orderBy('first_name')->orderBy('last_name')->get();
        $groupTypeOptions = $this->groupTypeOptions();
        $locationLevelOptions = $this->locationLevelOptions();

        return view('admin.najm-bahar.salaries.edit', compact('salaryRule', 'users', 'groupTypeOptions', 'locationLevelOptions'));
    }

    public function update(Request $request, SalaryRule $salaryRule)
    {
        $validated = $this->validateRule($request, $salaryRule->id);

        $validated['amount_gol'] = BaharMoney::parseToGol($validated['amount']);
        unset($validated['amount']);

        $validated['meta'] = $this->buildRuleMeta($request, $salaryRule);

        if ($validated['rule_type'] === 'role') {
            $validated['requires_senior_approval'] = false;
        }

        $salaryRule->update($validated);

        return redirect()->route('admin.najm-bahar.salaries.index')
            ->with('success', 'قانون پرداخت با موفقیت به‌روزرسانی شد.');
    }

    public function destroy(SalaryRule $salaryRule)
    {
        $salaryRule->delete();

        return redirect()->route('admin.najm-bahar.salaries.index')
            ->with('success', 'قانون پرداخت حذف شد.');
    }

    private function validateRule(Request $request, ?int $ruleId = null): array
    {
        $groupTypeValues = array_keys($this->groupTypeOptions());
        $locationLevelValues = array_keys($this->locationLevelOptions());

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'rule_type' => 'required|in:role,user,project',
            'group_id' => 'nullable|exists:groups,id',
            'user_id' => 'nullable|exists:users,id',
            'role_code' => 'nullable|in:2,3',
            'project_id' => 'nullable|string|max:255',
            'group_types' => 'nullable|array',
            'group_types.*' => 'in:' . implode(',', $groupTypeValues),
            'location_levels' => 'nullable|array',
            'location_levels.*' => 'in:' . implode(',', $locationLevelValues),
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'schedule_type' => 'required|in:monthly,interval,one_time',
            'interval_days' => 'nullable|integer|min:1',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
            'min_activity_score' => 'nullable|integer|min:0',
            'requires_senior_approval' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $validator->after(function ($validator) use ($request) {
            $ruleType = $request->input('rule_type');
            $scheduleType = $request->input('schedule_type');

            if ($ruleType === 'role' && ! $request->filled('role_code')) {
                $validator->errors()->add('role_code', 'نقش برای قوانین مدیر/بازرس الزامی است.');
            }

            if (in_array($ruleType, ['user', 'project'], true) && ! $request->filled('user_id')) {
                $validator->errors()->add('user_id', 'کاربر برای قوانین کاربر یا پروژه‌ای الزامی است.');
            }

            if ($scheduleType === 'interval' && ! $request->filled('interval_days')) {
                $validator->errors()->add('interval_days', 'برای پرداخت دوره‌ای باید تعداد روز مشخص شود.');
            }
        });

        $validated = $validator->validate();

        $validated['min_activity_score'] = $validated['min_activity_score'] ?? 0;
        $validated['is_active'] = $request->has('is_active');
        $validated['requires_senior_approval'] = $request->has('requires_senior_approval');

        return $validated;
    }

    private function buildRuleMeta(Request $request, ?SalaryRule $salaryRule = null): array
    {
        $meta = $salaryRule?->meta ?? [];

        if ($request->input('rule_type') === 'role') {
            $meta['group_types'] = $this->filterList($request, 'group_types', array_keys($this->groupTypeOptions()));
            $meta['location_levels'] = $this->filterList($request, 'location_levels', array_keys($this->locationLevelOptions()));
        } else {
            unset($meta['group_types'], $meta['location_levels']);
        }

        return $meta;
    }

    private function filterList(Request $request, string $key, array $allowed): array
    {
        $values = array_filter((array) $request->input($key, []), static fn ($value) => $value !== null && $value !== '');
        $values = array_values(array_intersect($values, $allowed));

        return $values;
    }

    private function groupTypeOptions(): array
    {
        return [
            'general' => 'عمومی',
            'specialty' => 'تخصصی',
            'exclusive' => 'اختصاصی',
        ];
    }

    private function locationLevelOptions(): array
    {
        return [
            'global' => 'جهانی',
            'continent' => 'قاره',
            'country' => 'کشور',
            'province' => 'استان',
            'county' => 'شهرستان',
            'section' => 'بخش',
            'city_rural' => 'شهر/دهستان',
            'region_village' => 'منطقه شهری/روستا',
            'neighborhood' => 'محله',
            'street' => 'خیابان',
            'alley' => 'کوچه',
        ];
    }
}
