<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KbCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KbCategoryController extends Controller
{
    public function index()
    {
        $categories = KbCategory::with('children')->whereNull('parent_id')->orderBy('sort_order')->get();

        return view('admin.kb.categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:kb_categories,id'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(4);
        $validated['is_active'] = $request->boolean('is_active', true);

        KbCategory::create($validated);

        return back()->with('success', 'دسته‌بندی ایجاد شد.');
    }

    public function update(Request $request, KbCategory $category)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:kb_categories,id'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['slug'] = $category->slug ?? Str::slug($validated['name']) . '-' . Str::random(4);
        $validated['is_active'] = $request->boolean('is_active', true);

        $category->update($validated);

        return back()->with('success', 'دسته‌بندی بروزرسانی شد.');
    }

    public function destroy(KbCategory $category)
    {
        $category->delete();

        return back()->with('success', 'دسته‌بندی حذف شد.');
    }
}




