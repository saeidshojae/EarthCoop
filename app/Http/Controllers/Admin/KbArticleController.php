<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\KbTag;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KbArticleController extends Controller
{
    public function index(Request $request)
    {
        $query = KbArticle::with(['category', 'author'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->category_id))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($sub) use ($search) {
                    $sub->where('title', 'like', "%$search%")
                        ->orWhere('slug', 'like', "%$search%")
                        ->orWhere('excerpt', 'like', "%$search%");
                });
            })
            ->orderByDesc('published_at')
            ->orderByDesc('created_at');

        $articles = $query->paginate(20)->withQueryString();
        $categories = KbCategory::orderBy('sort_order')->get();

        return view('admin.kb.articles.index', compact('articles', 'categories'));
    }

    public function create()
    {
        $categories = KbCategory::orderBy('sort_order')->get();
        $tags = KbTag::orderBy('name')->get();

        return view('admin.kb.articles.create', compact('categories', 'tags'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:kb_articles,slug'],
            'category_id' => ['nullable', 'exists:kb_categories,id'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,published,archived'],
            'is_featured' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['exists:kb_tags,id'],
            'published_at' => ['nullable', 'date'],
        ]);

        $article = new KbArticle($validated);
        $article->author_id = auth()->id();
        $article->last_editor_id = auth()->id();
        $article->slug = $validated['slug'] ?? Str::slug($validated['title']) . '-' . Str::random(4);
        $article->save();

        $article->tags()->sync($validated['tags'] ?? []);

        return redirect()->route('admin.kb.articles.index')->with('success', 'مقاله با موفقیت ایجاد شد.');
    }

    public function edit(KbArticle $article)
    {
        $categories = KbCategory::orderBy('sort_order')->get();
        $tags = KbTag::orderBy('name')->get();
        $article->load('tags');

        return view('admin.kb.articles.edit', compact('article', 'categories', 'tags'));
    }

    public function update(Request $request, KbArticle $article)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', "unique:kb_articles,slug,{$article->id}"],
            'category_id' => ['nullable', 'exists:kb_categories,id'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,published,archived'],
            'is_featured' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['exists:kb_tags,id'],
            'published_at' => ['nullable', 'date'],
        ]);

        $article->fill($validated);
        $article->last_editor_id = auth()->id();

        if (!empty($validated['slug'])) {
            $article->slug = $validated['slug'];
        } else {
            $article->slug = Str::slug($validated['title']) . '-' . Str::random(4);
        }

        $article->save();
        $article->tags()->sync($validated['tags'] ?? []);

        return redirect()->route('admin.kb.articles.index')->with('success', 'مقاله با موفقیت بروزرسانی شد.');
    }

    public function destroy(KbArticle $article)
    {
        $article->tags()->detach();
        $article->delete();

        return redirect()->route('admin.kb.articles.index')->with('success', 'مقاله حذف شد.');
    }

    public function toggleStatus(KbArticle $article)
    {
        $article->status = $article->status === 'published' ? 'draft' : 'published';
        $article->save();

        return back()->with('success', 'وضعیت مقاله بروزرسانی شد.');
    }
}




