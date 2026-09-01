<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Models\ElectionPolicyVersion;
use App\Models\ElectionProcessReview;
use App\Models\ElectionResponsibilityContractVersion;
use App\Models\GroupSetting;
use App\Services\Elections\ElectionProcessReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ElectionManagementController extends Controller
{
    public function __construct(private readonly ElectionProcessReviewService $reviews) {}

    public function index()
    {
        $now = now();

        $stats = [
            'open_cycles' => Schema::hasTable('elections')
                ? Election::query()->where('lifecycle_status', 'open')->count() : 0,
            'settling_cycles' => Schema::hasTable('elections')
                ? Election::query()->whereIn('lifecycle_status', ['tallying', 'awaiting_acceptance', 'appointing'])->count() : 0,
            'active_policies' => Schema::hasTable('election_policy_versions')
                ? ElectionPolicyVersion::query()
                    ->where('effective_at', '<=', $now)
                    ->where(fn ($q) => $q->whereNull('retired_at')->orWhere('retired_at', '>', $now))
                    ->count() : 0,
            'future_policies' => Schema::hasTable('election_policy_versions')
                ? ElectionPolicyVersion::query()->where('effective_at', '>', $now)->count() : 0,
            'open_reviews' => Schema::hasTable('election_process_reviews')
                ? ElectionProcessReview::query()
                    ->whereNotIn('human_status', ['decided', 'expired'])
                    ->count() : 0,
            'active_contracts' => Schema::hasTable('election_responsibility_contract_versions')
                ? ElectionResponsibilityContractVersion::query()->where('is_active', true)->count() : 0,
            'group_settings' => Schema::hasTable('group_setting') ? GroupSetting::query()->count() : 0,
        ];

        $recentCycles = Schema::hasTable('elections')
            ? Election::query()
                ->with('group:id,name')
                ->latest('id')
                ->limit(8)
                ->get()
            : collect();

        $attention = [];

        if ($stats['group_settings'] === 0) {
            $attention[] = 'هیچ سیاست پایه‌ای برای سطوح گروه‌ها ثبت نشده است.';
        }

        if ($stats['active_contracts'] < 2) {
            $attention[] = 'قرارداد فعال مدیر و بازرس کامل نیست؛ آغاز چرخه جدید ممکن است fail-closed شود.';
        }

        if ($stats['open_reviews'] > 0) {
            $attention[] = $stats['open_reviews'].' پرونده بازبینی نیازمند پیگیری وجود دارد.';
        }

        return view('admin.elections.index', compact('stats', 'recentCycles', 'attention'));
    }

    public function reviews(Request $request)
    {
        $status = $request->string('status')->toString();

        $reviews = ElectionProcessReview::query()
            ->with(['election.group:id,name', 'requester:id,name', 'subject:id,name'])
            ->when($status !== '', fn ($q) => $q->where('human_status', $status))
            ->orderByRaw("CASE WHEN human_status IN ('requested','in_review') THEN 0 WHEN human_status = 'not_requested' THEN 1 ELSE 2 END")
            ->orderBy('decision_due_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.elections.reviews', compact('reviews', 'status'));
    }

    public function stay(Request $request, ElectionProcessReview $review)
    {
        $validated = $request->validate(['reason' => 'required|string|max:2000']);
        $this->reviews->setInterimStay($review, $request->user(), $validated['reason']);

        return back()->with('success', 'توقف موقت اعمال شد و در audit ثبت گردید.');
    }

    public function decide(Request $request, ElectionProcessReview $review)
    {
        $validated = $request->validate([
            'decision' => 'required|in:upheld,corrected,dismissed',
            'reason' => 'required|string|max:5000',
            'remediation_reference' => 'nullable|string|max:255',
        ]);

        $this->reviews->decide(
            $review,
            $request->user(),
            $validated['decision'],
            $validated['reason'],
            $validated['remediation_reference'] ?? null,
        );

        return back()->with('success', 'تصمیم نهایی بازبینی ثبت شد.');
    }
}
