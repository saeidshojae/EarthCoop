<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Term;
use Illuminate\Http\Request;

class RuleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Term::with('term', 'childs');

        // جستجو
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        // فیلتر اساسنامه‌های اصلی (بدون والد)
        if ($request->filled('filter')) {
            if ($request->filter == 'main') {
                $query->whereNull('parent_id');
            } elseif ($request->filter == 'children') {
                $query->whereNotNull('parent_id');
            }
        }

        $terms = $query->orderBy('created_at', 'desc')->get();
        
        // آمار
        $stats = [
            'total' => Term::count(),
            'main' => Term::whereNull('parent_id')->count(),
            'children' => Term::whereNotNull('parent_id')->count(),
        ];
        
        return view('admin.rule.index', compact('terms', 'stats'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $mainTerms = Term::whereNull('parent_id')->orderBy('title')->get();
        return view('admin.rule.create', compact('mainTerms'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'parent_id' => 'nullable|exists:terms,id'
        ]);
        
        if($validated['parent_id'] == 'null' || empty($validated['parent_id'])){
            $validated['parent_id'] = null;
        }

        Term::create($validated);

        return redirect()->route('admin.rule.index')
            ->with('success', 'اساسنامه با موفقیت ایجاد شد.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($rule)
    {
        $term = Term::findOrFail($rule);
        $mainTerms = Term::whereNull('parent_id')
            ->where('id', '!=', $term->id)
            ->orderBy('title')
            ->get();
        return view('admin.rule.edit', compact('term', 'mainTerms'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $rule)
    {
        $term = Term::findOrFail($rule);
        
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'parent_id' => 'nullable|exists:terms,id'
        ]);
        
        if($validated['parent_id'] == 'null' || empty($validated['parent_id'])){
            $validated['parent_id'] = null;
        }

        // جلوگیری از ایجاد حلقه در سلسله‌مراتب
        if ($validated['parent_id'] && $validated['parent_id'] == $term->id) {
            return back()->withErrors(['parent_id' => 'نمی‌توانید اساسنامه را به خودش یا زیرمجموعه‌هایش اختصاص دهید.']);
        }

        // بررسی اینکه آیا parent_id یکی از زیرمجموعه‌های فعلی است
        $childrenIds = $term->childs->pluck('id')->toArray();
        if ($validated['parent_id'] && in_array($validated['parent_id'], $childrenIds)) {
            return back()->withErrors(['parent_id' => 'نمی‌توانید اساسنامه را به زیرمجموعه‌هایش اختصاص دهید.']);
        }

        $term->update($validated);

        return redirect()->route('admin.rule.index')
            ->with('success', 'اساسنامه با موفقیت ویرایش شد.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($rule)
    {
        $term = Term::findOrFail($rule);
        
        // بررسی اینکه آیا زیرمجموعه دارد
        if ($term->childs->count() > 0) {
            return redirect()->route('admin.rule.index')
                ->with('error', 'نمی‌توانید اساسنامه‌ای که زیرمجموعه دارد را حذف کنید. ابتدا زیرمجموعه‌ها را حذف کنید.');
        }

        $term->delete();

        return redirect()->route('admin.rule.index')
            ->with('success', 'اساسنامه با موفقیت حذف شد.');
    }
}
