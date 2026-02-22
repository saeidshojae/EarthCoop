<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\NajmHoda\Runtime\NajmHodaDomainEventPolicyLinkService;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class PageController extends Controller
{
    public function index(Request $request)
    {
        $query = Page::query();

        if ($request->filled('status')) {
            if ($request->status === 'published') {
                $query->where('is_published', true);
            } elseif ($request->status === 'draft') {
                $query->where('is_published', false);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $pages = $query->latest()->get();

        return view('admin.pages.index', compact('pages'));
    }

    public function create()
    {
        return view('admin.pages.create');
    }

    public function store(Request $request)
    {
        $this->emitRuntime('najm_hoda.input.content.service.page.store.requested', [
            'actor_id' => auth()->id() !== null ? (int) auth()->id() : null,
            'scope' => 'content',
            'risk' => 'low',
        ]);

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

        unset($validated['title_fa'], $validated['title_en'], $validated['title_ar']);
        unset($validated['content_fa'], $validated['content_en'], $validated['content_ar']);

        try {
            $page = Page::create($validated);
        } catch (Throwable $e) {
            $this->emitRuntime('najm_hoda.input.content.service.page.store.failed', [
                'actor_id' => auth()->id() !== null ? (int) auth()->id() : null,
                'error' => $e->getMessage(),
                'scope' => 'content',
                'risk' => 'medium',
            ]);

            throw $e;
        }

        $this->emitRuntime('najm_hoda.input.content.service.page.store.succeeded', [
            'page_id' => (int) $page->id,
            'actor_id' => auth()->id() !== null ? (int) auth()->id() : null,
            'scope' => 'content',
            'risk' => 'low',
        ]);

        return redirect()->route('admin.pages.index')->with('success', 'Page created successfully.');
    }

    public function show($id)
    {
        //
    }

    public function edit(Page $page)
    {
        return view('admin.pages.edit', compact('page'));
    }

    public function update(Request $request, Page $page)
    {
        $this->emitRuntime('najm_hoda.input.content.service.page.update.requested', [
            'page_id' => (int) $page->id,
            'actor_id' => auth()->id() !== null ? (int) auth()->id() : null,
            'scope' => 'content',
            'risk' => 'low',
        ]);

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
        $validated['show_in_header'] = $request->has('show_in_header');
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

        unset($validated['title_fa'], $validated['title_en'], $validated['title_ar']);
        unset($validated['content_fa'], $validated['content_en'], $validated['content_ar']);

        try {
            $page->update($validated);
        } catch (Throwable $e) {
            $this->emitRuntime('najm_hoda.input.content.service.page.update.failed', [
                'page_id' => (int) $page->id,
                'actor_id' => auth()->id() !== null ? (int) auth()->id() : null,
                'error' => $e->getMessage(),
                'scope' => 'content',
                'risk' => 'medium',
            ]);

            throw $e;
        }

        $this->emitRuntime('najm_hoda.input.content.service.page.update.succeeded', [
            'page_id' => (int) $page->id,
            'actor_id' => auth()->id() !== null ? (int) auth()->id() : null,
            'scope' => 'content',
            'risk' => 'low',
        ]);

        return redirect()->route('admin.pages.index')->with('success', 'Page updated successfully.');
    }

    public function destroy(Page $page)
    {
        $this->emitRuntime('najm_hoda.input.content.service.page.delete.requested', [
            'page_id' => (int) $page->id,
            'actor_id' => auth()->id() !== null ? (int) auth()->id() : null,
            'scope' => 'content',
            'risk' => 'medium',
        ]);

        try {
            $page->delete();
        } catch (Throwable $e) {
            $this->emitRuntime('najm_hoda.input.content.service.page.delete.failed', [
                'page_id' => (int) $page->id,
                'actor_id' => auth()->id() !== null ? (int) auth()->id() : null,
                'error' => $e->getMessage(),
                'scope' => 'content',
                'risk' => 'high',
            ]);

            throw $e;
        }

        $this->emitRuntime('najm_hoda.input.content.service.page.delete.succeeded', [
            'page_id' => (int) $page->id,
            'actor_id' => auth()->id() !== null ? (int) auth()->id() : null,
            'scope' => 'content',
            'risk' => 'medium',
        ]);

        return redirect()->route('admin.pages.index')->with('success', 'Page deleted successfully.');
    }

    public function upload(Request $request)
    {
        $this->emitRuntime('najm_hoda.input.content.service.page.upload.requested', [
            'actor_id' => auth()->id() !== null ? (int) auth()->id() : null,
            'scope' => 'content',
            'risk' => 'low',
        ]);

        if ($request->hasFile('upload')) {
            $file = $request->file('upload');
            $filename = str_replace(' ', '_', time() . '_' . $file->getClientOriginalName());
            $file->move(public_path('uploads'), $filename);
            $url = url('uploads/' . $filename);

            $this->emitRuntime('najm_hoda.input.content.service.page.upload.succeeded', [
                'file_name' => $filename,
                'actor_id' => auth()->id() !== null ? (int) auth()->id() : null,
                'scope' => 'content',
                'risk' => 'low',
            ]);

            return response()->json([
                'uploaded' => 1,
                'fileName' => $filename,
                'url' => $url,
            ]);
        }

        $this->emitRuntime('najm_hoda.input.content.service.page.upload.rejected', [
            'actor_id' => auth()->id() !== null ? (int) auth()->id() : null,
            'reason' => 'no_upload_file',
            'scope' => 'content',
            'risk' => 'medium',
        ]);

        return response()->json([
            'uploaded' => 0,
            'error' => [
                'message' => 'No file uploaded.',
            ],
        ]);
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
