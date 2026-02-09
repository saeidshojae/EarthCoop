<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\NajmBahar\Models\Fee;
use App\Modules\NajmBahar\Services\FeeService;
use App\Helpers\BaharMoney;
use Illuminate\Http\Request;

class NajmBaharFeeController extends Controller
{
    protected $feeService;

    public function __construct(FeeService $feeService)
    {
        $this->feeService = $feeService;
    }

    /**
     * نمایش لیست کارمزدها
     */
    public function index(Request $request)
    {
        $query = Fee::query();

        // جستجو
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // فیلتر وضعیت
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // فیلتر نوع
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $fees = $query->orderBy('created_at', 'desc')->paginate(20);

        // آمار
        $stats = [
            'total' => Fee::count(),
            'active' => Fee::where('is_active', true)->count(),
            'inactive' => Fee::where('is_active', false)->count(),
        ];

        return view('admin.najm-bahar.fees.index', compact('fees', 'stats'));
    }

    /**
     * نمایش فرم ایجاد کارمزد
     */
    public function create()
    {
        return view('admin.najm-bahar.fees.create');
    }

    /**
     * ذخیره کارمزد جدید
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:fixed,percentage,combined',
            'fixed_amount' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'percentage' => 'nullable|numeric|min:0|max:100',
            'transaction_type' => 'required|in:all,immediate,scheduled,fee,adjustment',
            'min_amount' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'max_amount' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'is_active' => 'boolean',
            'description' => 'nullable|string',
        ]);

        $validated['fixed_amount'] = isset($validated['fixed_amount']) && $validated['fixed_amount'] !== null
            ? BaharMoney::parseToGol($validated['fixed_amount'])
            : null;
        $validated['min_amount'] = isset($validated['min_amount']) && $validated['min_amount'] !== null
            ? BaharMoney::parseToGol($validated['min_amount'])
            : null;
        $validated['max_amount'] = isset($validated['max_amount']) && $validated['max_amount'] !== null
            ? BaharMoney::parseToGol($validated['max_amount'])
            : null;

        // اعتبارسنجی منطقی
        if ($validated['type'] === 'percentage' || $validated['type'] === 'combined') {
            if (empty($validated['percentage']) || $validated['percentage'] <= 0) {
                return back()->withErrors(['percentage' => 'برای نوع درصدی یا ترکیبی، درصد باید بیشتر از صفر باشد.'])->withInput();
            }
        }

        if ($validated['type'] === 'fixed' || $validated['type'] === 'combined') {
            if ($validated['fixed_amount'] === null || $validated['fixed_amount'] < 0) {
                return back()->withErrors(['fixed_amount' => 'برای نوع ثابت یا ترکیبی، مبلغ ثابت باید بیشتر یا مساوی صفر باشد.'])->withInput();
            }
        }

        if ($validated['max_amount'] !== null && $validated['min_amount'] !== null && $validated['max_amount'] <= $validated['min_amount']) {
            return back()->withErrors(['max_amount' => 'حداکثر مبلغ باید بزرگتر از حداقل مبلغ باشد.'])->withInput();
        }

        $validated['is_active'] = $request->has('is_active');

        Fee::create($validated);

        return redirect()->route('admin.najm-bahar.fees.index')
            ->with('success', 'کارمزد با موفقیت ایجاد شد.');
    }

    /**
     * نمایش فرم ویرایش
     */
    public function edit(Fee $fee)
    {
        return view('admin.najm-bahar.fees.edit', compact('fee'));
    }

    /**
     * به‌روزرسانی کارمزد
     */
    public function update(Request $request, Fee $fee)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:fixed,percentage,combined',
            'fixed_amount' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'percentage' => 'nullable|numeric|min:0|max:100',
            'transaction_type' => 'required|in:all,immediate,scheduled,fee,adjustment',
            'min_amount' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'max_amount' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'is_active' => 'boolean',
            'description' => 'nullable|string',
        ]);

        $validated['fixed_amount'] = isset($validated['fixed_amount']) && $validated['fixed_amount'] !== null
            ? BaharMoney::parseToGol($validated['fixed_amount'])
            : null;
        $validated['min_amount'] = isset($validated['min_amount']) && $validated['min_amount'] !== null
            ? BaharMoney::parseToGol($validated['min_amount'])
            : null;
        $validated['max_amount'] = isset($validated['max_amount']) && $validated['max_amount'] !== null
            ? BaharMoney::parseToGol($validated['max_amount'])
            : null;

        // اعتبارسنجی منطقی
        if ($validated['type'] === 'percentage' || $validated['type'] === 'combined') {
            if (empty($validated['percentage']) || $validated['percentage'] <= 0) {
                return back()->withErrors(['percentage' => 'برای نوع درصدی یا ترکیبی، درصد باید بیشتر از صفر باشد.'])->withInput();
            }
        }

        if ($validated['type'] === 'fixed' || $validated['type'] === 'combined') {
            if ($validated['fixed_amount'] === null || $validated['fixed_amount'] < 0) {
                return back()->withErrors(['fixed_amount' => 'برای نوع ثابت یا ترکیبی، مبلغ ثابت باید بیشتر یا مساوی صفر باشد.'])->withInput();
            }
        }

        if ($validated['max_amount'] !== null && $validated['min_amount'] !== null && $validated['max_amount'] <= $validated['min_amount']) {
            return back()->withErrors(['max_amount' => 'حداکثر مبلغ باید بزرگتر از حداقل مبلغ باشد.'])->withInput();
        }

        $validated['is_active'] = $request->has('is_active');

        $fee->update($validated);

        return redirect()->route('admin.najm-bahar.fees.index')
            ->with('success', 'کارمزد با موفقیت به‌روزرسانی شد.');
    }

    /**
     * حذف کارمزد
     */
    public function destroy(Fee $fee)
    {
        $fee->delete();

        return redirect()->route('admin.najm-bahar.fees.index')
            ->with('success', 'کارمزد با موفقیت حذف شد.');
    }

    /**
     * تست کارمزد
     */
    public function test(Request $request)
    {
        $validated = $request->validate([
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'transaction_type' => 'required|in:all,immediate,scheduled,fee,adjustment',
        ]);

        $amount = BaharMoney::parseToGol($validated['amount']);
        if ($amount <= 0) {
            return response()->json(['message' => 'amount must be greater than zero'], 422);
        }

        $result = $this->feeService->testFee(
            $amount,
            $validated['transaction_type']
        );

        return response()->json($result);
    }
}

