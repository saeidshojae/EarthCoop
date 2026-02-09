<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KbTag;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KbTagController extends Controller
{
    public function index()
    {
        $tags = KbTag::orderBy('name')->get();

        return view('admin.kb.tags.index', compact('tags'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(4);
        $validated['is_active'] = $request->boolean('is_active', true);

        KbTag::create($validated);

        return back()->with('success', 'تگ ایجاد شد.');
    }

    public function update(Request $request, KbTag $tag)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['slug'] = $tag->slug ?? Str::slug($validated['name']) . '-' . Str::random(4);
        $validated['is_active'] = $request->boolean('is_active', true);

        $tag->update($validated);

        return back()->with('success', 'تگ بروزرسانی شد.');
    }

    public function destroy(KbTag $tag)
    {
        $tag->delete();

        return back()->with('success', 'تگ حذف شد.');
    }
}




