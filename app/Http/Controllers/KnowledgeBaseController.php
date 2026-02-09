<?php

namespace App\Http\Controllers;

use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\KbTag;
use Illuminate\Http\Request;

class KnowledgeBaseController extends Controller
{
    public function index(Request $request)
    {
        $categories = KbCategory::with(['children' => function ($query) {
            $query->where('is_active', true);
        }])->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $query = KbArticle::with('category')
            ->published()
            ->when($request->filled('category'), fn ($q) => $q->where('category_id', $request->category))
            ->when($request->filled('tag'), fn ($q) => $q->whereHas('tags', fn ($t) => $t->where('kb_tags.id', $request->tag)))
            ->when($request->filled('q'), fn ($q) => $q->where(function ($sub) use ($request) {
                $search = $request->q;
                $sub->where('title', 'like', "%$search%")
                    ->orWhere('content', 'like', "%$search%");
            }))
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at');

        $articles = $query->paginate(12)->withQueryString();
        $tags = KbTag::where('is_active', true)->orderBy('name')->get();

        return view('support.kb.index', compact('categories', 'articles', 'tags'));
    }

    public function show(string $slug)
    {
        $article = KbArticle::with(['category', 'tags'])->where('slug', $slug)->published()->firstOrFail();
        $article->increment('view_count');

        $relatedArticles = KbArticle::published()
            ->where('category_id', $article->category_id)
            ->where('id', '!=', $article->id)
            ->latest('published_at')
            ->take(4)
            ->get();

        return view('support.kb.show', compact('article', 'relatedArticles'));
    }

    public function suggest(Request $request)
    {
        $query = KbArticle::published()
            ->select('id', 'title', 'slug', 'excerpt', 'category_id')
            ->with('category')
            ->limit(5);

        if ($search = $request->get('q')) {
            $query->where(function ($sub) use ($search) {
                $sub->where('title', 'like', "%$search%")
                    ->orWhere('content', 'like', "%$search%");
            });
        }

        if ($categoryId = $request->get('category_id')) {
            $query->where('category_id', $categoryId);
        }

        $articles = $query->get();

        return response()->json($articles);
    }
}

