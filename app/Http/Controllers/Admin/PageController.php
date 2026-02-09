<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PageController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Page::query();

        // فیلتر وضعیت
        if ($request->filled('status')) {
            if ($request->status === 'published') {
                $query->where('is_published', true);
            } elseif ($request->status === 'draft') {
                $query->where('is_published', false);
            }
        }

        // جستجو
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $pages = $query->latest()->get();
        return view('admin.pages.index', compact('pages'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.pages.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|max:255',
            'template' => 'nullable|string|in:default,about,help,cooperation',
            'content' => 'required',
            'meta_title' => 'nullable|max:255',
            'meta_description' => 'nullable',
            'is_published' => 'boolean',
            'title_fa' => 'nullable|string',
            'title_en' => 'nullable|string',
            'title_ar' => 'nullable|string',
            'content_fa' => 'nullable|string',
            'content_en' => 'nullable|string',
            'content_ar' => 'nullable|string',
        ]);

        $validated['slug'] = Str::slug($request->title);
        $validated['template'] = $request->template ?? 'default';
        
        // Prepare translations
        $validated['title_translations'] = [
            'fa' => $request->title_fa ?? $request->title,
            'en' => $request->title_en ?? $request->title,
            'ar' => $request->title_ar ?? $request->title,
        ];
        
        $validated['content_translations'] = [
            'fa' => $request->content_fa ?? $request->content,
            'en' => $request->content_en ?? $request->content,
            'ar' => $request->content_ar ?? $request->content,
        ];
        
        // Remove temporary fields
        unset($validated['title_fa'], $validated['title_en'], $validated['title_ar']);
        unset($validated['content_fa'], $validated['content_en'], $validated['content_ar']);
        
        Page::create($validated);

        return redirect()->route('admin.pages.index')
            ->with('success', 'صفحه با موفقیت ایجاد شد.');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Page $page)
    {
        return view('admin.pages.edit', compact('page'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Page $page)
    {
        $validated = $request->validate([
            'title' => 'required|max:255',
            'template' => 'nullable|string|in:default,about,help,cooperation',
            'content' => 'required',
            'meta_title' => 'nullable|max:255',
            'meta_description' => 'nullable',
            'is_published' => 'boolean',
            'show_in_header' => 'boolean',
            'title_fa' => 'nullable|string',
            'title_en' => 'nullable|string',
            'title_ar' => 'nullable|string',
            'content_fa' => 'nullable|string',
            'content_en' => 'nullable|string',
            'content_ar' => 'nullable|string',
        ]);

        $validated['slug'] = Str::slug($request->title);
        $validated['template'] = $request->template ?? 'default';
        $validated['show_in_header'] = $request->has('show_in_header') ? true : false;
        
        // Prepare translations
        $validated['title_translations'] = [
            'fa' => $request->title_fa ?? $request->title,
            'en' => $request->title_en ?? $request->title,
            'ar' => $request->title_ar ?? $request->title,
        ];
        
        $validated['content_translations'] = [
            'fa' => $request->content_fa ?? $request->content,
            'en' => $request->content_en ?? $request->content,
            'ar' => $request->content_ar ?? $request->content,
        ];
        
        // Remove temporary fields
        unset($validated['title_fa'], $validated['title_en'], $validated['title_ar']);
        unset($validated['content_fa'], $validated['content_en'], $validated['content_ar']);
        
        $page->update($validated);

        return redirect()->route('admin.pages.index')
            ->with('success', 'صفحه با موفقیت به‌روزرسانی شد.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Page $page)
    {
        $page->delete();
        return redirect()->route('admin.pages.index')
            ->with('success', 'صفحه با موفقیت حذف شد.');
    }
   
    public function upload(Request $request)
{
    if ($request->hasFile('upload')) {
        $file = $request->file('upload');
        $filename = time().'_'.$file->getClientOriginalName();
        $filename = str_replace(' ', '_', time().'_'.$file->getClientOriginalName());

        $file->move(public_path('uploads'), $filename);

        $url = url('uploads/'.$filename); // ساختن لینک کامل

        return response()->json([
            'uploaded' => 1,
            'fileName' => $filename,
            'url' => $url,
        ]);
    }

    return response()->json([
        'uploaded' => 0,
        'error' => [
            'message' => 'هیچ فایلی آپلود نشد.'
        ]
    ]);
}

    
}
