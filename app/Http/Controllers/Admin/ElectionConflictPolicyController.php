<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ElectionConflictPolicyRule;
use App\Models\ElectionConflictPolicyVersion;
use App\Services\Elections\ElectionConflictPolicyService;
use App\Services\Elections\ElectionConflictPolicyVersionService;
use App\Services\Elections\ElectionGroupDomainClassifier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Temporal\Contracts\TemporalService;
use App\Temporal\Context\TemporalContextResolver;
use Illuminate\Validation\ValidationException;

class ElectionConflictPolicyController extends Controller
{
    public function index(ElectionConflictPolicyService $policies)
    {
        $current = $policies->effectiveVersion()->load('rules');
        $versions = ElectionConflictPolicyVersion::query()->orderByDesc('version')->get();
        $levels = array_keys(ElectionGroupDomainClassifier::LEVEL_RANK);
        return view('admin.system-settings.elections.conflict-policy', compact('current','versions','levels'));
    }

    public function store(Request $request, ElectionConflictPolicyVersionService $versions, TemporalService $temporal, TemporalContextResolver $contexts)
    {
        $validated = $request->validate([
            'current_position' => 'required|in:'.implode(',', ElectionConflictPolicyRule::POSITIONS),
            'current_domain_type' => 'required|in:'.implode(',', ElectionConflictPolicyRule::DOMAINS),
            'current_level' => 'required|string|max:32',
            'new_position' => 'required|in:'.implode(',', ElectionConflictPolicyRule::POSITIONS),
            'new_domain_type' => 'required|in:'.implode(',', ElectionConflictPolicyRule::DOMAINS),
            'new_level' => 'required|string|max:32',
            'decision' => 'required|in:'.implode(',', ElectionConflictPolicyRule::DECISIONS),
            'rule_reason' => 'nullable|string|max:500',
            'change_reason' => 'required|string|max:500',
            'effective_at' => 'nullable|string|max:80',
        ]);
        if (! empty($validated['effective_at'])) {
            try {
                $validated['effective_at'] = $temporal
                    ->parseDateTime((string) $validated['effective_at'], $contexts->defaultContext())
                    ->format('Y-m-d H:i:s');
            } catch (\InvalidArgumentException|\ValueError) {
                throw ValidationException::withMessages(['effective_at' => 'زمان اثر معتبر نیست.']);
            }
        }

        $rule = collect($validated)->only([
            'current_position','current_domain_type','current_level','new_position','new_domain_type','new_level','decision'
        ])->all();
        $rule['reason'] = $validated['rule_reason'] ?? null;
        $version = $versions->addOrReplaceRule(
            $rule, $request->user(), $validated['change_reason'],
            isset($validated['effective_at']) ? Carbon::parse($validated['effective_at']) : now(),
        );
        return back()->with('success', "نسخه {$version->version} سیاست تعارض منتشر شد.");
    }
}
