<?php

namespace App\Http\Controllers\Group;

use App\Events\GroupFeedUpdated;
use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\Category;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BlogController extends Controller
{
    public function store(Group $group, Request $request)
    {
        $inputs = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category_id' => 'required|numeric|exists:categories,id',
            'img' => 'nullable|file|max:20480',
        ]);

        $inputs['group_id'] = $group->id;
        $inputs['user_id'] = auth()->id();

        if ($request->hasFile('img') && $request->file('img')->isValid()) {
            $file = $request->file('img');
            $name = time() . '.' . $file->getClientOriginalExtension();
            $inputs['file_type'] = $file->getMimeType();
            $file->move(public_path('images/blogs'), $name);
            $inputs['img'] = $name;
        }

        $blog = Blog::create($inputs);
        $blog->refresh();

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
        if ($blog->user_id !== auth()->id()) {
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

    public function update(Request $request, Blog $blog)
    {
        if ($blog->user_id !== auth()->id()) {
            return response()->json(['status' => 'error', 'message' => 'شما مجوز ویرایش این پست را ندارید.'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category_id' => 'required|exists:categories,id',
        ]);

        $blog->update($validated);
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

    private function dispatchGroupEvent(object $event): void
    {
        if ((bool) config('group-chat.defer_broadcasts', true)) {
            dispatch(static fn () => event($event))->afterResponse();
            return;
        }

        event($event);
    }
}

