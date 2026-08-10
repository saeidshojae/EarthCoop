<?php

namespace App\Http\Controllers\Group;

use App\Events\GroupFeedUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Group\StorePostRequest;
use App\Http\Requests\Group\UpdatePostRequest;
use App\Models\Blog;
use App\Models\Category;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use App\Services\GroupChat\HtmlSanitizer;
use App\Services\GroupChat\GroupFeedService;
use Illuminate\Support\Facades\DB;

class BlogController extends Controller
{
    public function store(Group $group, StorePostRequest $request, HtmlSanitizer $sanitizer)
    {
        $inputs = $request->validated();
        $inputs['content'] = $sanitizer->sanitize($inputs['content']);

        $inputs['group_id'] = $group->id;
        $inputs['user_id'] = auth()->id();

        if ($request->hasFile('img') && $request->file('img')->isValid()) {
            $file = $request->file('img');
            $inputs['file_type'] = $file->getMimeType();
            $inputs['img'] = $file->storeAs(
                'group-chat/posts/' . $group->id,
                (string) \Illuminate\Support\Str::uuid() . '.' . $file->extension(),
                'local'
            );
        }

        $blog = DB::transaction(function () use ($inputs, $group): Blog {
            $blog = Blog::create($inputs);
            app(GroupFeedService::class)->record((int) $group->id, 'post', (int) $blog->id, (int) auth()->id(), $blog->created_at);

            return $blog->refresh();
        });

        $this->dispatchGroupEvent(new \App\Events\BlogCreated($blog, $group, auth()->user()));

        try {
            $service = app(\App\Services\ReputationService::class);
            $service->applyAction(auth()->user(), 'post_created', ['blog_id' => $blog->id], $blog->id, 'groups');
        } catch (\Throwable $e) {
            // ignore reputation failures
        }

        $blog->load(['user', 'category', 'comments', 'reactions']);
        $payload = [
            'post_id' => (int) $blog->id,
            'html' => view('groups.partials.post', [
                'item' => $blog,
                'group' => $group,
                'userVote' => null,
                'categories' => Category::all(),
            ])->render(),
        ];

        $this->dispatchGroupEvent(new GroupFeedUpdated((int) $group->id, 'post_created', $payload, (int) auth()->id()));

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'پست با موفقیت ارسال شد.',
                'post' => [
                    'id' => (int) $blog->id,
                    'html' => $payload['html'],
                ],
            ]);
        }

        return redirect()->back()->with('success', 'پست شما با موفقیت ارسال شد');
    }

    public function destroy(Blog $blog)
    {
        $this->authorize('delete', $blog);

        if (false && $blog->user_id !== auth()->id()) {
            return response()->json(['status' => 'error', 'message' => 'شما مجوز حذف این پست را ندارید.'], 403);
        }

        $groupId = (int) $blog->group_id;
        $blogId = (int) $blog->id;
        $blog->delete();

        // Cache deleted post ID so polling can detect it (10 min TTL)
        $cacheKey = 'group.' . $groupId . '.deleted_post_ids';
        $existing = Cache::get($cacheKey, []);
        $existing[] = $blogId;
        Cache::put($cacheKey, array_values(array_unique(array_slice($existing, -200))), 600);

        $this->dispatchGroupEvent(new GroupFeedUpdated($groupId, 'post_deleted', [
            'post_id' => $blogId,
        ], (int) auth()->id()));

        return response()->json([
            'status' => 'success',
            'message' => 'پست با موفقیت حذف شد.',
        ]);
    }

    public function update(UpdatePostRequest $request, Blog $blog, HtmlSanitizer $sanitizer)
    {
        $this->authorize('update', $blog);

        if (false && $blog->user_id !== auth()->id()) {
            return response()->json(['status' => 'error', 'message' => 'شما مجوز ویرایش این پست را ندارید.'], 403);
        }

        $validated = $request->validated();
        $submittedContent = (string) $validated['content'];
        if ($submittedContent === strip_tags($submittedContent)) {
            $submittedContent = str_replace(["\r\n", "\r"], "\n", $submittedContent);
            $submittedContent = str_replace("\n", '<br>', e($submittedContent));
        }
        $validated['content'] = $sanitizer->sanitize($submittedContent);

        $blog->update($validated);
        $blog->forceFill(['edited_at' => now()])->save();
        $blog->refresh();
        $blog->load(['user', 'category', 'comments', 'reactions']);

        $categories = Category::all();
        $renderedHtml = view('groups.partials.post', [
            'item' => $blog,
            'group' => $blog->group,
            'userVote' => null,
            'categories' => $categories,
        ])->render();

        $this->dispatchGroupEvent(new GroupFeedUpdated((int) $blog->group_id, 'post_updated', [
            'post_id' => (int) $blog->id,
            'html' => $renderedHtml,
        ], (int) auth()->id()));

        return response()->json([
            'status' => 'success',
            'message' => 'پست با موفقیت ویرایش شد.',
            'post' => [
                'id' => (int) $blog->id,
                'html' => $renderedHtml,
            ],
        ]);
    }

    /**
     * Mark blog post as read by current user
     */
    public function markAsRead(Blog $blog)
    {
        $this->authorize('view', $blog);
        $user = auth()->user();
        
        // Don't mark own posts as read
        if ($blog->user_id === $user->id) {
            return response()->json(['status' => 'ignored']);
        }

        // Mark as read
        $blog->markAsRead($user->id);

        $this->dispatchGroupEvent(new GroupFeedUpdated((int) $blog->group_id, 'post_read', [
            'post_id' => (int) $blog->id,
            'read_count' => (int) $blog->read_count,
        ], (int) $user->id));

        return response()->json([
            'status' => 'success',
            'read_count' => $blog->read_count,
        ]);
    }

    public function media(Blog $blog)
    {
        $this->authorize('view', $blog);

        abort_if(empty($blog->img) || ! str_contains($blog->img, '/'), 404);
        abort_unless(Storage::disk('local')->exists($blog->img), 404);

        return Storage::disk('local')->response($blog->img, null, [
            'Content-Type' => $blog->file_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline; filename="post-media-' . $blog->id . '"',
        ]);
    }

    private function dispatchGroupEvent(object $event): void
    {
        if (! (bool) config('group-chat.enabled', true)) {
            return;
        }

        if (strtolower((string) config('group-chat.transport', 'auto')) === 'polling') {
            return;
        }

        if ((bool) config('group-chat.defer_broadcasts', true)) {
            dispatch(static function () use ($event): void {
                try {
                    event($event);
                } catch (\Throwable $exception) {
                    \Illuminate\Support\Facades\Log::warning('group_chat_broadcast_failed', [
                        'event' => get_class($event),
                        'message' => $exception->getMessage(),
                    ]);
                }
            })->afterResponse();
            return;
        }

        try {
            event($event);
        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Log::warning('group_chat_broadcast_failed', [
                'event' => get_class($event),
                'message' => $exception->getMessage(),
            ]);
        }
    }
}

