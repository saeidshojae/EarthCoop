<?php

namespace App\Http\Controllers\NajmBahar;

use App\Http\Controllers\Controller;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\NajmBahar\Models\Investment;
use App\Modules\NajmBahar\Models\ProjectCategory;
use App\Modules\NajmBahar\Services\InvestmentService;
use App\Modules\NajmBahar\Services\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InvestmentController extends Controller
{
    protected InvestmentService $investmentService;
    protected ProjectService $projectService;

    public function __construct(
        InvestmentService $investmentService,
        ProjectService $projectService
    ) {
        $this->investmentService = $investmentService;
        $this->projectService = $projectService;
    }

    /**
     * نمایش لیست پروژه‌های قابل سرمایه‌گذاری
     */
    public function index(Request $request)
    {
        $filters = [
            'category_level1_id' => $request->input('category'),
            'project_type' => $request->input('type'),
            'sort_by' => $request->input('sort_by', 'created_at'),
            'sort_order' => $request->input('sort_order', 'desc'),
        ];

        // Investment catalogue is public-facing. Approved private projects must
        // never leak into it, regardless of how ProjectService is reused elsewhere.
        $projects = $this->projectService->getApprovedProjects($filters)
            ->where('project_visibility', 'public')
            ->values();
        
        $categories = ProjectCategory::active()
            ->level(1)
            ->root()
            ->orderBy('order')
            ->get();

        return view('najm-bahar.investments.index', compact('projects', 'categories'));
    }

    /**
     * نمایش جزئیات پروژه برای سرمایه‌گذاری
     */
    public function show(Project $project)
    {
        $this->authorize('view', $project);

        if ($project->status !== 'approved') {
            return redirect()
                ->route('najm-bahar.investments.index')
                ->with('error', 'این پروژه قابل سرمایه‌گذاری نیست.');
        }

        $project->load([
            'owner',
            'categoryLevel1',
            'categoryLevel2',
            'categoryLevel3',
            'investments' => function ($query) {
                $query->paid();
            }
        ]);

        $stats = $this->investmentService->getProjectInvestmentStats($project);

        return view('najm-bahar.investments.show', compact('project', 'stats'));
    }

    /**
     * ثبت درخواست سرمایه‌گذاری
     */
    public function store(Request $request, Project $project)
    {
        // Authorization is enforced before validating/accepting investment input so
        // an outsider cannot probe or invest in an approved-but-private project.
        $this->authorize('view', $project);

        $validated = $request->validate([
            'amount' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $investor = Auth::user();
            
            $investment = $this->investmentService->createInvestment(
                $project,
                $investor,
                $validated['amount'],
                ['notes' => $validated['notes'] ?? null]
            );

            return redirect()
                ->route('najm-bahar.investments.payment', $investment)
                ->with('success', 'درخواست سرمایه‌گذاری ثبت شد. لطفاً پرداخت را تکمیل کنید.');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * صفحه پرداخت سرمایه‌گذاری
     */
    public function payment(Investment $investment)
    {
        // بررسی دسترسی
        $this->authorize('view', $investment);

        if ($investment->status !== 'pending') {
            return redirect()
                ->route('najm-bahar.my-investments')
                ->with('error', 'این سرمایه‌گذاری قبلاً پرداخت شده است.');
        }

        $investment->load('project');

        return view('najm-bahar.investments.payment', compact('investment'));
    }

    /**
     * پردازش پرداخت سرمایه‌گذاری
     */
    public function processPayment(Request $request, Investment $investment)
    {
        // بررسی دسترسی
        $this->authorize('update', $investment);

        try {
            $payer = Auth::user();
            
            $this->investmentService->processInvestmentPayment(
                $investment,
                $payer,
                $request->input('tracking_code')
            );

            // فعال‌سازی خودکار
            $this->investmentService->activateInvestment($investment);

            return redirect()
                ->route('najm-bahar.my-investments')
                ->with('success', 'پرداخت با موفقیت انجام شد و سرمایه‌گذاری فعال شد.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * لیست سرمایه‌گذاری‌های کاربر
     */
    public function myInvestments(Request $request)
    {
        $user = Auth::user();
        
        $status = $request->input('status');
        $statuses = $status ? [$status] : [];
        
        $investments = $this->investmentService->getInvestmentsByInvestor($user, $statuses);
        $stats = $this->investmentService->getInvestorStats($user);

        return view('najm-bahar.investments.my-investments', compact('investments', 'stats'));
    }

    /**
     * جزئیات یک سرمایه‌گذاری
     */
    public function showInvestment(Investment $investment)
    {
        // بررسی دسترسی
        $this->authorize('view', $investment);

        $investment->load(['project', 'project.owner', 'transaction']);

        return view('najm-bahar.investments.show-investment', compact('investment'));
    }

    /**
     * لغو سرمایه‌گذاری
     */
    public function cancel(Request $request, Investment $investment)
    {
        // بررسی دسترسی
        $this->authorize('delete', $investment);

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $this->investmentService->cancelInvestment($investment, $validated['reason']);

            return redirect()
                ->route('najm-bahar.my-investments')
                ->with('success', 'سرمایه‌گذاری لغو شد.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
