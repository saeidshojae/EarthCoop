<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NajmBaharAgreement;
use App\Models\Setting;
use Illuminate\Http\Request;

class NajmBaharController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // انتقال داده‌های قدیمی از settings به جدول جدید (فقط یک بار)
        $this->migrateOldData();
        
        $query = NajmBaharAgreement::with('parent', 'children');

        // جستجو
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        // فیلتر توافقنامه‌های اصلی (بدون والد)
        if ($request->filled('filter')) {
            if ($request->filter == 'main') {
                $query->whereNull('parent_id');
            } elseif ($request->filter == 'children') {
                $query->whereNotNull('parent_id');
            }
        }

        $agreements = $query->orderBy('order')->orderBy('created_at', 'desc')->get();
        
        // آمار
        $stats = [
            'total' => NajmBaharAgreement::count(),
            'main' => NajmBaharAgreement::whereNull('parent_id')->count(),
            'children' => NajmBaharAgreement::whereNotNull('parent_id')->count(),
        ];
        
        return view('admin.najm-bahar.index', compact('agreements', 'stats'));
    }

    /**
     * Migrate old data from settings table
     */
    private function migrateOldData()
    {
        // فقط اگر هیچ داده‌ای در جدول جدید وجود نداشته باشد
        if (NajmBaharAgreement::count() == 0) {
            $setting = Setting::find(1);
            if ($setting && !empty($setting->najm_summary)) {
                // ایجاد توافقنامه از داده‌های قدیمی
                NajmBaharAgreement::create([
                    'title' => 'توافقنامه نجم بهار',
                    'content' => $setting->najm_summary,
                    'parent_id' => null,
                    'order' => 0
                ]);
            }
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $mainAgreements = NajmBaharAgreement::whereNull('parent_id')->orderBy('order')->orderBy('title')->get();
        return view('admin.najm-bahar.create', compact('mainAgreements'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'parent_id' => 'nullable|exists:najm_bahar_agreements,id',
            'order' => 'nullable|integer|min:0'
        ]);
        
        if($validated['parent_id'] == 'null' || empty($validated['parent_id'])){
            $validated['parent_id'] = null;
        }

        $validated['order'] = $validated['order'] ?? 0;

        NajmBaharAgreement::create($validated);

        return redirect()->route('admin.najm-bahar.index')
            ->with('success', 'توافقنامه با موفقیت ایجاد شد.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($najmBahar)
    {
        $agreement = NajmBaharAgreement::findOrFail($najmBahar);
        $mainAgreements = NajmBaharAgreement::whereNull('parent_id')
            ->where('id', '!=', $agreement->id)
            ->orderBy('order')
            ->orderBy('title')
            ->get();
        return view('admin.najm-bahar.edit', compact('agreement', 'mainAgreements'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $najmBahar)
    {
        $agreement = NajmBaharAgreement::findOrFail($najmBahar);
        
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'parent_id' => 'nullable|exists:najm_bahar_agreements,id',
            'order' => 'nullable|integer|min:0'
        ]);
        
        if($validated['parent_id'] == 'null' || empty($validated['parent_id'])){
            $validated['parent_id'] = null;
        }

        $validated['order'] = $validated['order'] ?? 0;

        // جلوگیری از ایجاد حلقه در سلسله‌مراتب
        if ($validated['parent_id'] && $validated['parent_id'] == $agreement->id) {
            return back()->withErrors(['parent_id' => 'نمی‌توانید توافقنامه را به خودش یا زیرمجموعه‌هایش اختصاص دهید.']);
        }

        // بررسی اینکه آیا parent_id یکی از زیرمجموعه‌های فعلی است
        $childrenIds = $agreement->children->pluck('id')->toArray();
        if ($validated['parent_id'] && in_array($validated['parent_id'], $childrenIds)) {
            return back()->withErrors(['parent_id' => 'نمی‌توانید توافقنامه را به زیرمجموعه‌هایش اختصاص دهید.']);
        }

        $agreement->update($validated);

        return redirect()->route('admin.najm-bahar.index')
            ->with('success', 'توافقنامه با موفقیت ویرایش شد.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($najmBahar)
    {
        $agreement = NajmBaharAgreement::findOrFail($najmBahar);
        
        // بررسی اینکه آیا زیرمجموعه دارد
        if ($agreement->children->count() > 0) {
            return redirect()->route('admin.najm-bahar.index')
                ->with('error', 'نمی‌توانید توافقنامه‌ای که زیرمجموعه دارد را حذف کنید. ابتدا زیرمجموعه‌ها را حذف کنید.');
        }

        $agreement->delete();

        return redirect()->route('admin.najm-bahar.index')
            ->with('success', 'توافقنامه با موفقیت حذف شد.');
    }
}
