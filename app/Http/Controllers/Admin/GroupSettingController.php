<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ElectionPolicyVersion;
use App\Models\GroupSetting;
use App\Services\Elections\ElectionPolicyVersionService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class GroupSettingController extends Controller
{
    public function __construct(private readonly ElectionPolicyVersionService $policyVersions) {}

    public function index(Request $request)
    {
        if ($request->filled('history')) {
            $setting = GroupSetting::query()->findOrFail((int) $request->input('history'));
            $policies = ElectionPolicyVersion::query()
                ->with(['managerContractVersion', 'inspectorContractVersion'])
                ->where('group_setting_id', $setting->id)
                ->orderByDesc('version')
                ->get();

            $currentPolicy = $policies
                ->filter(fn (ElectionPolicyVersion $policy) => $policy->effective_at !== null && $policy->effective_at->lte(now()))
                ->first(fn (ElectionPolicyVersion $policy) => $policy->retired_at === null || $policy->retired_at->gt(now()));

            $futurePolicies = $policies
                ->filter(fn (ElectionPolicyVersion $policy) => $policy->effective_at !== null && $policy->effective_at->gt(now()))
                ->values();

            return view('admin.system-settings.elections.history', compact(
                'setting',
                'policies',
                'currentPolicy',
                'futurePolicies',
            ));
        }

        if ($request->filled('reporting')) {
            $setting = GroupSetting::query()->findOrFail((int) $request->input('reporting'));
            $currentPolicy = ElectionPolicyVersion::query()
                ->where('group_setting_id', $setting->id)
                ->where('effective_at', '<=', now())
                ->where(function ($query) {
                    $query->whereNull('retired_at')->orWhere('retired_at', '>', now());
                })
                ->orderByDesc('version')
                ->first();

            return view('admin.system-settings.elections.reporting', compact('setting', 'currentPolicy'));
        }

        $sort = $request->get('sort');
        $baseLevels = [
            'global', 'continent', 'country', 'province', 'county', 'district',
            'city', 'region', 'neighborhood', 'street', 'alley',
        ];

        if (in_array($sort, ['experience', 'job', 'age', 'gender'], true)) {
            $groupSettings = GroupSetting::query()
                ->where('level', 'like', '%_'.$sort)
                ->orderByRaw("FIELD(SUBSTRING_INDEX(level, '_', 1), 'global','continent','country','province','county','district','city','region','neighborhood','street','alley')")
                ->get();
        } else {
            // Public/general groups are exactly the canonical unsuffixed levels.
            // Do not rely on historical IDs: fresh seeds and later inserts make
            // ID-based filtering unstable and caused specialized settings to leak
            // into the public tab.
            $groupSettings = GroupSetting::query()
                ->whereIn('level', $baseLevels)
                ->orderByRaw("FIELD(level, 'global','continent','country','province','county','district','city','region','neighborhood','street','alley')")
                ->get();
            $sort = 'public';
        }

        return view('admin.system-settings.elections.index', compact('groupSettings', 'sort'));
    }

    public function edit(Request $request, GroupSetting $setting)
    {
        $setting->election_status = $setting->election_status == 1 ? 0 : 1;
        $setting->save();

        $this->policyVersions->publishFromSetting(
            $setting,
            $request->user()?->id,
            'admin_election_status_toggle',
            now(),
        );

        $status = $setting->election_status == 1 ? 'فعال' : 'غیرفعال';
        return back()->with('success', "وضعیت انتخابات برای {$setting->name()} به {$status} تغییر یافت و نسخه جدید سیاست ثبت شد.");
    }

    public function update(Request $request, GroupSetting $setting)
    {
        $validated = $request->validate([
            'manager_count' => 'required|integer|min:0',
            'inspector_count' => 'required|integer|min:0',
            'election_time' => 'required|integer|min:1',
            'max_for_election' => 'required|integer|min:1',
            'second_election_time' => 'required|integer|min:0',
            'response_duration_days' => 'nullable|integer|min:1|max:365',
            'election_report_min_distinct_voters' => 'nullable|integer|min:2|max:1000000',
            'election_report_bucket_days' => 'nullable|integer|min:1|max:365',
            'election_meaningful_trend_min_net_change' => 'nullable|integer|min:1|max:1000000',
            'effective_at' => 'nullable|date',
            'change_reason' => 'nullable|string|max:500',
        ], [
            'manager_count.required' => 'تعداد مدیران الزامی است',
            'manager_count.integer' => 'تعداد مدیران باید عدد باشد',
            'manager_count.min' => 'تعداد مدیران نمی‌تواند منفی باشد',
            'inspector_count.required' => 'تعداد بازرسان الزامی است',
            'inspector_count.integer' => 'تعداد بازرسان باید عدد باشد',
            'inspector_count.min' => 'تعداد بازرسان نمی‌تواند منفی باشد',
            'election_time.required' => 'مدت رأی‌گیری الزامی است',
            'election_time.integer' => 'مدت رأی‌گیری باید عدد باشد',
            'election_time.min' => 'مدت رأی‌گیری باید حداقل ۱ روز باشد',
            'max_for_election.required' => 'حدنصاب شروع انتخابات الزامی است',
            'max_for_election.integer' => 'حدنصاب شروع انتخابات باید عدد باشد',
            'max_for_election.min' => 'حدنصاب شروع انتخابات باید حداقل ۱ باشد',
            'second_election_time.required' => 'فاصله چرخه‌های انتخابات الزامی است',
            'second_election_time.integer' => 'فاصله چرخه‌ها باید عدد باشد',
            'second_election_time.min' => 'فاصله چرخه‌ها نمی‌تواند منفی باشد',
        ]);

        $effectiveAt = ! empty($validated['effective_at']) ? Carbon::parse($validated['effective_at']) : now();
        $snapshot = [
            'election_status' => (bool) $setting->election_status,
            'manager_count' => (int) $validated['manager_count'],
            'inspector_count' => (int) $validated['inspector_count'],
            'voting_duration_days' => (int) $validated['election_time'],
            'start_threshold' => (int) $validated['max_for_election'],
            'cycle_interval_months' => (int) $validated['second_election_time'],
            'response_duration_days' => isset($validated['response_duration_days'])
                ? (int) $validated['response_duration_days']
                : null,
            'report_min_distinct_voters' => isset($validated['election_report_min_distinct_voters'])
                ? (int) $validated['election_report_min_distinct_voters'] : (int) ($setting->election_report_min_distinct_voters ?: 10),
            'report_bucket_days' => isset($validated['election_report_bucket_days'])
                ? (int) $validated['election_report_bucket_days'] : (int) ($setting->election_report_bucket_days ?: 7),
            'meaningful_trend_min_net_change' => isset($validated['election_meaningful_trend_min_net_change'])
                ? (int) $validated['election_meaningful_trend_min_net_change'] : (int) ($setting->election_meaningful_trend_min_net_change ?: 3),
        ];

        $policy = $this->policyVersions->publishSnapshot(
            $setting,
            $snapshot,
            $request->user()?->id,
            $validated['change_reason'] ?? 'admin_policy_update',
            $effectiveAt,
        );

        if (! $effectiveAt->isFuture()) {
            $this->policyVersions->syncEffectiveMirrors(5000);
            $setting->refresh();
        }

        $timing = $effectiveAt->isFuture()
            ? ' برای اجرای آینده زمان‌بندی شد'
            : ' و بلافاصله مؤثر شد';

        return back()->with('success', "تنظیمات انتخابات برای {$setting->name()} به‌عنوان نسخه {$policy->version}{$timing}.");
    }
}
