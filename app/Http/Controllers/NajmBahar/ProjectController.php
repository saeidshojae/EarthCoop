<?php

namespace App\Http\Controllers\NajmBahar;

use App\Http\Controllers\Controller;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\NajmBahar\Models\ProjectCategory;
use App\Modules\NajmBahar\Services\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    protected ProjectService $projectService;

    public function __construct(ProjectService $projectService)
    {
        $this->projectService = $projectService;
    }

    /**
     * نمایش لیست پروژه‌های کاربر
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $projects = $this->projectService->getProjectsByOwner($user);
        $stats = $this->projectService->getProjectStatistics($user);

        return view('najm-bahar.projects.index', compact('projects', 'stats'));
    }

    /**
     * فرم ایجاد پروژه جدید
     */
    public function create()
    {
        $categories = ProjectCategory::active()
            ->level(1)
            ->root()
            ->orderBy('order')
            ->get();

        return view('najm-bahar.projects.create', compact('categories'));
    }

    /**
     * ذخیره پروژه جدید
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category_level1_id' => 'required|exists:najm_bahar_project_categories,id',
            'category_level2_id' => 'nullable|exists:najm_bahar_project_categories,id',
            'category_level3_id' => 'nullable|exists:najm_bahar_project_categories,id',
            'project_type' => 'required|in:production,service,infrastructure,research,social',
            'project_visibility' => 'required|in:public,private',
            'project_stage' => 'required|in:idea,documented,prototype,active',
            'summary' => 'nullable|string|max:1000',
            'description' => 'nullable|string',
            'problem_statement' => 'required|string',
            'solution_description' => 'required|string',
            'value_proposition' => 'nullable|string',
            'target_market' => 'required|in:local,professional,general,external',
            'existing_assets' => 'nullable|string',
            'geographic_continent_id' => 'nullable|integer',
            'geographic_country_id' => 'nullable|integer',
            'geographic_province_id' => 'nullable|integer',
            'geographic_county_id' => 'nullable|integer',
            'geographic_section_id' => 'nullable|integer',
            'geographic_city_id' => 'nullable|integer',
            'geographic_rural_id' => 'nullable|integer',
            'geographic_region_id' => 'nullable|integer',
            'geographic_neighborhood_id' => 'nullable|integer',
            'geographic_street_id' => 'nullable|integer',
            'geographic_alley_id' => 'nullable|integer',
            'investment_method' => 'required|in:auction_shares,capital_participation',
            
            // Conditional fields for auction method
            'base_value_min' => 'required_if:investment_method,auction_shares|nullable|integer|min:1',
            'base_value_max' => 'required_if:investment_method,auction_shares|nullable|integer|min:1|gte:base_value_min',
            'total_shares' => 'required_if:investment_method,auction_shares|nullable|integer|min:1',
            'initial_auction_percent' => 'required_if:investment_method,auction_shares|nullable|numeric|min:0|max:100',
            'max_user_ownership_percent' => 'required_if:investment_method,auction_shares|nullable|numeric|min:0|max:100',
            'auction_period' => 'required_if:investment_method,auction_shares|nullable|in:monthly,quarterly,semi_annual,annual',
            
            // Conditional fields for capital participation method
            'required_capital' => 'required_if:investment_method,capital_participation|nullable|integer|min:1',
            'profit_percentage' => 'required_if:investment_method,capital_participation|nullable|numeric|min:0.01|max:100',
            'investment_duration_months' => 'required_if:investment_method,capital_participation|nullable|integer|min:1',
            
            // Common fields
            'risk_level' => 'required|in:low,medium,high',
            'main_risks' => 'nullable|string',
            'oversight_type' => 'required|in:guild,insurance,both,none',
            'reporting_interval' => 'required|in:monthly,quarterly,semi_annual,annual',
            'fund_usage_scope' => 'required|in:project_only',
            'accept_transparency' => 'accepted',
            'failure_policy' => 'required|in:refund,asset_conversion,vote',
            'value_update_trigger' => 'required|in:stage_progress,oversight_approval',
            'accept_rules' => 'accepted',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|mimes:pdf,doc,docx,xls,xlsx|max:10240',
        ]);

        try {
            // آپلود فایل‌ها
            if ($request->hasFile('attachments')) {
                $uploadedFiles = [];
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('projects/attachments', 'public');
                    $uploadedFiles[] = [
                        'original_name' => $file->getClientOriginalName(),
                        'path' => $path,
                        'size' => $file->getSize(),
                    ];
                }
                $validated['attachments'] = $uploadedFiles;
            }

            if (!empty($validated['main_risks'])) {
                $validated['main_risks'] = preg_split('/\r\n|\r|\n|,/', $validated['main_risks']);
                $validated['main_risks'] = array_values(array_filter(array_map('trim', $validated['main_risks'])));
            }

            $user = Auth::user();
            $project = $this->projectService->createProject($user, $validated);

            return redirect()
                ->route('najm-bahar.projects.show', $project)
                ->with('success', 'پروژه با موفقیت ایجاد شد.');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * نمایش جزئیات پروژه
     */
    public function show(Project $project)
    {
        // بررسی دسترسی
        $this->authorize('view', $project);

        $project->load(['categoryLevel1', 'categoryLevel2', 'categoryLevel3', 'reviews', 'investments']);

        return view('najm-bahar.projects.show', compact('project'));
    }

    /**
     * فرم ویرایش پروژه
     */
    public function edit(Project $project)
    {
        // بررسی دسترسی
        $this->authorize('update', $project);

        $categories = ProjectCategory::active()
            ->level(1)
            ->root()
            ->orderBy('order')
            ->get();

        return view('najm-bahar.projects.edit', compact('project', 'categories'));
    }

    /**
     * بروزرسانی پروژه
     */
    public function update(Request $request, Project $project)
    {
        // بررسی دسترسی
        $this->authorize('update', $project);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category_level1_id' => 'required|exists:najm_bahar_project_categories,id',
            'category_level2_id' => 'nullable|exists:najm_bahar_project_categories,id',
            'category_level3_id' => 'nullable|exists:najm_bahar_project_categories,id',
            'project_type' => 'required|in:production,service,infrastructure,research,social',
            'project_visibility' => 'required|in:public,private',
            'project_stage' => 'required|in:idea,documented,prototype,active',
            'summary' => 'nullable|string|max:1000',
            'description' => 'nullable|string',
            'problem_statement' => 'required|string',
            'solution_description' => 'required|string',
            'value_proposition' => 'nullable|string',
            'target_market' => 'required|in:local,professional,general,external',
            'existing_assets' => 'nullable|string',
            'geographic_continent_id' => 'nullable|integer',
            'geographic_country_id' => 'nullable|integer',
            'geographic_province_id' => 'nullable|integer',
            'geographic_county_id' => 'nullable|integer',
            'geographic_section_id' => 'nullable|integer',
            'geographic_city_id' => 'nullable|integer',
            'geographic_rural_id' => 'nullable|integer',
            'geographic_region_id' => 'nullable|integer',
            'geographic_neighborhood_id' => 'nullable|integer',
            'geographic_street_id' => 'nullable|integer',
            'geographic_alley_id' => 'nullable|integer',
            'investment_method' => 'required|in:auction_shares,capital_participation',
            
            // Conditional fields for auction method
            'base_value_min' => 'required_if:investment_method,auction_shares|nullable|integer|min:1',
            'base_value_max' => 'required_if:investment_method,auction_shares|nullable|integer|min:1|gte:base_value_min',
            'total_shares' => 'required_if:investment_method,auction_shares|nullable|integer|min:1',
            'initial_auction_percent' => 'required_if:investment_method,auction_shares|nullable|numeric|min:0|max:100',
            'max_user_ownership_percent' => 'required_if:investment_method,auction_shares|nullable|numeric|min:0|max:100',
            'auction_period' => 'required_if:investment_method,auction_shares|nullable|in:monthly,quarterly,semi_annual,annual',
            
            // Conditional fields for capital participation method
            'required_capital' => 'required_if:investment_method,capital_participation|nullable|integer|min:1',
            'profit_percentage' => 'required_if:investment_method,capital_participation|nullable|numeric|min:0.01|max:100',
            'investment_duration_months' => 'required_if:investment_method,capital_participation|nullable|integer|min:1',
            
            // Common fields
            'risk_level' => 'required|in:low,medium,high',
            'main_risks' => 'nullable|string',
            'oversight_type' => 'required|in:guild,insurance,both,none',
            'reporting_interval' => 'required|in:monthly,quarterly,semi_annual,annual',
            'fund_usage_scope' => 'required|in:project_only',
            'accept_transparency' => 'accepted',
            'failure_policy' => 'required|in:refund,asset_conversion,vote',
            'value_update_trigger' => 'required|in:stage_progress,oversight_approval',
            'accept_rules' => 'accepted',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|mimes:pdf,doc,docx,xls,xlsx|max:10240',
        ]);

        try {
            // آپلود فایل‌های جدید
            if ($request->hasFile('attachments')) {
                $uploadedFiles = $project->attachments ?? [];
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('projects/attachments', 'public');
                    $uploadedFiles[] = [
                        'original_name' => $file->getClientOriginalName(),
                        'path' => $path,
                        'size' => $file->getSize(),
                    ];
                }
                $validated['attachments'] = $uploadedFiles;
            }

            $this->projectService->updateProject($project, $validated);

            return redirect()
                ->route('najm-bahar.projects.show', $project)
                ->with('success', 'پروژه با موفقیت بروزرسانی شد.');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * ارسال پروژه برای بررسی
     */
    public function submit(Project $project)
    {
        // بررسی دسترسی
        $this->authorize('update', $project);

        try {
            $this->projectService->submitForReview($project);

            return redirect()
                ->route('najm-bahar.projects.show', $project)
                ->with('success', 'پروژه برای بررسی ارسال شد.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * حذف (نرم) پروژه
     */
    public function destroy(Project $project)
    {
        // بررسی دسترسی
        $this->authorize('delete', $project);

        // فقط پروژه‌های draft قابل حذف هستند
        if ($project->status !== 'draft') {
            return back()->with('error', 'فقط پروژه‌های پیش‌نویس قابل حذف هستند.');
        }

        $project->delete();

        return redirect()
            ->route('najm-bahar.projects.index')
            ->with('success', 'پروژه با موفقیت حذف شد.');
    }

    /**
     * دریافت دسته‌بندی‌های فرزند برای AJAX
     */
    public function getSubCategories(Request $request)
    {
        $parentId = $request->input('parent_id');
        
        $categories = ProjectCategory::active()
            ->where('parent_id', $parentId)
            ->orderBy('order')
            ->get(['id', 'name', 'level']);

        return response()->json($categories);
    }
}
