<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\NajmBahar\Models\SalaryRun;
use App\Modules\NajmBahar\Models\SalaryRunItem;
use App\Modules\NajmBahar\Services\SalaryService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class NajmBaharSalaryRunController extends Controller
{
    public function index(Request $request)
    {
        $runs = SalaryRun::orderByDesc('run_date')->paginate(20);

        return view('admin.najm-bahar.salary-runs.index', compact('runs'));
    }

    public function store(Request $request, SalaryService $salaryService)
    {
        $date = $request->input('run_date');
        $runDate = $date ? Carbon::parse($date) : Carbon::today();

        $run = $salaryService->createRun($runDate, auth()->id());

        return redirect()->route('admin.najm-bahar.salary-runs.show', $run)
            ->with('success', 'پرداخت ماهانه ایجاد شد.');
    }

    public function show(SalaryRun $salaryRun, Request $request)
    {
        $query = SalaryRunItem::where('run_id', $salaryRun->id)->orderBy('id');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $items = $query->paginate(30)->withQueryString();

        return view('admin.najm-bahar.salary-runs.show', compact('salaryRun', 'items'));
    }

    public function updateItem(Request $request, SalaryRun $salaryRun, SalaryRunItem $item, SalaryService $salaryService)
    {
        $validated = $request->validate([
            'activity_score' => 'nullable|integer|min:0',
            'senior_approved' => 'nullable|boolean',
        ]);

        if (array_key_exists('activity_score', $validated)) {
            $item->activity_score = $validated['activity_score'];
        }

        if ($request->has('senior_approved')) {
            if ($request->boolean('senior_approved')) {
                $item->senior_approved_at = now();
                $item->senior_approved_by = auth()->id();
            } else {
                $item->senior_approved_at = null;
                $item->senior_approved_by = null;
            }
        }

        $salaryService->refreshItemStatus($item);

        return back()->with('success', 'آیتم به‌روزرسانی شد.');
    }

    public function process(SalaryRun $salaryRun, SalaryService $salaryService)
    {
        $result = $salaryService->processRun($salaryRun, auth()->id());

        return back()->with('success', 'پرداخت‌ها پردازش شد.');
    }
}
