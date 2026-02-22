<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\KbTag;
use App\Services\NajmHoda\Runtime\NajmHodaDomainEventPolicyLinkService;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

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
        $this->emitRuntime('najm_hoda.input.content.service.kb_article.store.requested', [
            'actor_id' => auth()->id() !== null ? (int) auth()->id() : null,
            'scope' => 'content',
            'risk' => 'low',
        ]);

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

        try {
            $article = new KbArticle($validated);
            $article->author_id = auth()->id();
            $article->last_editor_id = auth()->id();
            $article->slug = $validated['slug'] ?? Str::slug($validated['title']) . '-' . Str::random(4);
            $article->save();
            $article->tags()->sync($validated['tags'] ?? []);
        } catch (Throwable $e) {
            $this->emitRuntime('najm_hoda.input.content.service.kb_article.store.failed', [
                'actor_id' => auth()->id() !== null ? (int) auth()->id() : null,
                'error' => $e->getMessage(),
                'scope' => 'content',
                'risk' => 'medium',
            ]);

            throw $e;
        }

        $this->emitRuntime('najm_hoda.input.content.service.kb_article.store.succeeded', [
            'article_id' => (int) $article->id,
            'actor_id' => auth()->id() !== null ? (int) auth()->id() : null,
            'scope' => 'content',
            'risk' => 'low',
        ]);

        return redirect()->route('admin.kb.articles.index')->with('success', 'Article created successfully.');
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
        $this->emitRuntime('najm_hoda.input.content.service.kb_article.update.requested', [
            'article_id' => (int) $article->id,
            'actor_id' => auth()->id() !== null ? (int) auth()->id() : null,
            'scope' => 'content',
            'risk' => 'low',
        ]);

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

        try {
            $article->fill($validated);
            $article->last_editor_id = auth()->id();
            $article->slug = !empty($validated['slug'])
                ? $validated['slug']
                : Str::slug($validated['title']) . '-' . Str::random(4);

            $article->save();
            $article->tags()->sync($validated['tags'] ?? []);
        } catch (Throwable $e) {
            $this->emitRuntime('najm_hoda.input.content.service.kb_article.update.failed', [
                'article_id' => (int) $article->id,
                'actor_id' => auth()->id() !== null ? (int) auth()->id() : null,
                'error' => $e->getMessage(),
                'scope' => 'content',
                'risk' => 'medium',
            ]);

            throw $e;
        }

        $this->emitRuntime('najm_hoda.input.content.service.kb_article.update.succeeded', [
            'article_id' => (int) $article->id,
            'actor_id' => auth()->id() !== null ? (int) auth()->id() : null,
            'scope' => 'content',
            'risk' => 'low',
        ]);

        return redirect()->route('admin.kb.articles.index')->with('success', 'Article updated successfully.');
    }

    public function destroy(KbArticle $article)
    {
        $this->emitRuntime('najm_hoda.input.content.service.kb_article.delete.requested', [
            'article_id' => (int) $article->id,
            'actor_id' => auth()->id() !== null ? (int) auth()->id() : null,
            'scope' => 'content',
            'risk' => 'medium',
        ]);

        try {
            $article->tags()->detach();
            $article->delete();
        } catch (Throwable $e) {
            $this->emitRuntime('najm_hoda.input.content.service.kb_article.delete.failed', [
                'article_id' => (int) $article->id,
                'actor_id' => auth()->id() !== null ? (int) auth()->id() : null,
                'error' => $e->getMessage(),
                'scope' => 'content',
                'risk' => 'high',
            ]);

            throw $e;
        }

        $this->emitRuntime('najm_hoda.input.content.service.kb_article.delete.succeeded', [
            'article_id' => (int) $article->id,
            'actor_id' => auth()->id() !== null ? (int) auth()->id() : null,
            'scope' => 'content',
            'risk' => 'medium',
        ]);

        return redirect()->route('admin.kb.articles.index')->with('success', 'Article deleted successfully.');
    }

    public function toggleStatus(KbArticle $article)
    {
        $this->emitRuntime('najm_hoda.input.content.service.kb_article.toggle_status.requested', [
            'article_id' => (int) $article->id,
            'actor_id' => auth()->id() !== null ? (int) auth()->id() : null,
            'scope' => 'content',
            'risk' => 'low',
        ]);

        try {
            $article->status = $article->status === 'published' ? 'draft' : 'published';
            $article->save();
        } catch (Throwable $e) {
            $this->emitRuntime('najm_hoda.input.content.service.kb_article.toggle_status.failed', [
                'article_id' => (int) $article->id,
                'actor_id' => auth()->id() !== null ? (int) auth()->id() : null,
                'error' => $e->getMessage(),
                'scope' => 'content',
                'risk' => 'medium',
            ]);

            throw $e;
        }

        $this->emitRuntime('najm_hoda.input.content.service.kb_article.toggle_status.succeeded', [
            'article_id' => (int) $article->id,
            'status' => (string) $article->status,
            'actor_id' => auth()->id() !== null ? (int) auth()->id() : null,
            'scope' => 'content',
            'risk' => 'low',
        ]);

        return back()->with('success', 'Article status updated.');
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function emitRuntime(string $event, array $payload): void
    {
        try {
            /** @var RuntimeEventBus $bus */
            $bus = app(RuntimeEventBus::class);
            $bus->emit($event, $payload);

            /** @var NajmHodaDomainEventPolicyLinkService $link */
            $link = app(NajmHodaDomainEventPolicyLinkService::class);
            $link->ingest($event, $payload);
        } catch (Throwable) {
            // no-op
        }
    }
}
