<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\KbTag;
use Illuminate\Http\Request;

class KbController extends Controller
{
    /**
     * نمایش لیست مقالات پایگاه دانش
     */
    public function index(Request $request)
    {
        $query = KbArticle::with(['category', 'author'])
            ->where('status', 'published')
            ->orderBy('published_at', 'desc');

        $selectedCategory = null;

        // فیلتر بر اساس دسته
        if ($request->filled('category')) {
            $categoryId = (int) $request->input('category');
            $selectedCategory = KbCategory::where('is_active', true)->find($categoryId);

            if ($selectedCategory) {
                // همه زیرشاخه‌ها (هر عمق) را هم لحاظ کن تا با کلیک روی دسته والد لیست خالی نشود
                $allCategories = KbCategory::select(['id', 'parent_id'])
                    ->where('is_active', true)
                    ->get();

                $childrenByParent = $allCategories->groupBy('parent_id');
                $categoryIds = [];
                $stack = [$selectedCategory->id];

                while (!empty($stack)) {
                    $pid = array_pop($stack);
                    if (in_array($pid, $categoryIds, true)) {
                        continue;
                    }
                    $categoryIds[] = $pid;

                    foreach (($childrenByParent[$pid] ?? collect()) as $child) {
                        $stack[] = $child->id;
                    }
                }

                $query->whereIn('category_id', $categoryIds);
            } else {
                $query->where('category_id', $categoryId);
            }
        }

        // فیلتر بر اساس تگ
        if ($request->filled('tag')) {
            $query->whereHas('tags', function($q) use ($request) {
                $q->where('kb_tags.id', $request->input('tag'));
            });
        }

        // جستجو
        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function($s) use ($q) {
                $s->where('title', 'like', "%{$q}%")
                  ->orWhere('excerpt', 'like', "%{$q}%")
                  ->orWhere('content', 'like', "%{$q}%");
            });
        }

        $articles = $query->paginate(12)->withQueryString();

        // کارت‌ها: فقط دسته‌های سطح اول + زیرمجموعه‌ها (active)
        $categories = KbCategory::with(['children' => function ($q) {
                $q->where('is_active', true)->orderBy('sort_order');
            }])
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // فیلتر dropdown: همه دسته‌های active
        $categoryOptions = KbCategory::where('is_active', true)->orderBy('name')->get();

        // اگر selectedCategory بالا با children لود نشده بود (مثلا category خالی)، همین مقدار null می‌ماند
        $tags = KbTag::where('is_active', true)->orderBy('name')->get();
        $featuredArticles = KbArticle::where('status', 'published')
            ->where('is_featured', true)
            ->orderBy('published_at', 'desc')
            ->take(6)
            ->get();

        return view('support.kb.index', compact('articles', 'categories', 'categoryOptions', 'selectedCategory', 'tags', 'featuredArticles'));
    }

    /**
     * نمایش مقاله
     */
    public function show($slug)
    {
        $article = KbArticle::with(['category', 'author', 'tags'])
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        // افزایش تعداد بازدید
        $article->increment('view_count');

        // مقالات مرتبط
        $relatedArticles = KbArticle::where('status', 'published')
            ->where('category_id', $article->category_id)
            ->where('id', '!=', $article->id)
            ->orderBy('published_at', 'desc')
            ->take(4)
            ->get();

        // مقالات محبوب
        $popularArticles = KbArticle::where('status', 'published')
            ->orderBy('view_count', 'desc')
            ->take(5)
            ->get();

        return view('support.kb.show', compact('article', 'relatedArticles', 'popularArticles'));
    }

    /**
     * پیشنهاد مقالات بر اساس جستجو (AJAX)
     */
    public function suggest(Request $request)
    {
        $query = $request->input('q', '');
        
        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $articles = KbArticle::where('status', 'published')
            ->where(function($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('excerpt', 'like', "%{$query}%")
                  ->orWhere('content', 'like', "%{$query}%");
            })
            ->orderBy('view_count', 'desc')
            ->take(4)
            ->get()
            ->map(function($article) {
                return [
                    'title' => $article->title,
                    'slug' => $article->slug,
                    'excerpt' => $article->excerpt,
                    'category' => $article->category?->name,
                ];
            });

        return response()->json($articles);
    }
}

