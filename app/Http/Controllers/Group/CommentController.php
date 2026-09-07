<?php

namespace App\Http\Controllers\Group;

use App\Events\GroupFeedUpdated;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Group\StoreCommentRequest;
use App\Models\Blog;
use App\Models\GroupUser;
use App\Models\Comment;
use App\Services\GroupChat\GroupFeedService;
use Illuminate\Support\Facades\DB;

class CommentController extends Controller
{
    public function comment(Blog $blog)
    {
        $this->authorize('view', $blog);
        $comments = Comment::where('blog_id', $blog->id)->orderBy('created_at', 'asc')->get();
        $group = $blog->group;
        
        $yourRole = GroupUser::where('group_id', $blog->group_id)
            ->where('user_id', auth()->id())
            ->value('role');
            
        return view('groups.comment', compact('blog', 'comments', 'group', 'yourRole'));
    }

    public function store(StoreCommentRequest $request)
    {
        $validated = $request->validated();

        $comment = DB::transaction(function () use ($validated): Comment {
            $comment = Comment::create([
                'user_id' => auth()->id(),
                'blog_id' => $validated['blog_id'],
                'message' => $validated['message'],
                'parent_id' => $validated['parent_id'] ?? null,
            ]);
            $comment->loadMissing('blog');
            app(GroupFeedService::class)->record((int) $comment->blog->group_id, 'comment', (int) $comment->id, (int) auth()->id(), $comment->created_at);

            return $comment;
        });
        $comment->refresh(); // برای اطمینان از بارگذاری روابط

        // Dispatch event for notifications
        $blog = $comment->blog;
        $commentsCount = (int) $blog->comments()->count();
        if ($blog && $blog->group) {
            event(new \App\Events\CommentCreated($comment, $blog, $blog->group, auth()->user()));
        }

        // award points for creating a comment
        try {
            $service = app(\App\Services\ReputationService::class);
            $service->applyAction(
                auth()->user(),
                'comment_created',
                ['comment_id' => $comment->id],
                $comment->id,
                'groups',
                'comment_created:' . $comment->id . ':author:' . auth()->id()
            );
        } catch (\Throwable $e) {
            // ignore
        }

        // Load relationships for rendering
        $comment->load('user', 'reactions');

        // Render HTML for client-side injection
        $html = view('groups.partials.comment', ['item' => $comment])->render();

        if ($blog && $blog->group) {
            $this->dispatchGroupEvent(new GroupFeedUpdated((int) $blog->group_id, 'comment_created', [
                'comment_id' => (int) $comment->id,
                'blog_id' => (int) $blog->id,
                'comments_count' => $commentsCount,
                'html' => $html,
            ], (int) auth()->id()));
        }

        return response()->json([
            'status' => 'success',
            'comment' => [
                'id' => $comment->id,
                'html' => $html,
            ],
            'comments_count' => $commentsCount,
            // Keep old 'message' key for backward compatibility if needed
            'message' => [
                'id' => $comment->id,
                'message' => $comment->message,
                'created_at' => $comment->created_at->format('H:i'),
                'sender' => auth()->user()->first_name . ' ' . auth()->user()->last_name,
                'parent' => $comment->parent ? [
                    'id' => $comment->parent->id,
                    'message' => $comment->parent->message,
                    'user_name' => $comment->parent->user->first_name . ' ' . $comment->parent->user->last_name
                ] : null,
            ]
        ]);
    }

public function update(Request $request, Comment $comment)
    {
        $this->authorize('update', $comment);
        // اطمینان از مالکیت نظر
        // Authorization is centralized in CommentPolicy (owner or group moderator).

        $data = $request->validate([
            'message' => ['required','string'],
        ]);

        $comment->message = $data['message'];
        $comment->save();
        $comment->load(['user', 'reactions']);
        $blog = $comment->blog;

        $this->dispatchGroupEvent(new GroupFeedUpdated((int) $blog->group_id, 'comment_updated', [
            'comment_id' => (int) $comment->id,
            'blog_id' => (int) $blog->id,
            'comments_count' => (int) $blog->comments()->count(),
            'html' => view('groups.partials.comment', ['item' => $comment])->render(),
        ], (int) auth()->id()));

        return response()->json([
            'ok' => true,
            'message' => $comment->message,
        ]);
    }

    public function destroy(Comment $comment)
    {
        $this->authorize('delete', $comment);
        // Authorization is centralized in CommentPolicy (owner or group moderator).

        $blog = $comment->blog;
        $blogId = $comment->blog_id;
        $commentId = $comment->id;

        $comment->delete();

        // Cache deleted comment ID for other users to detect via polling
        $cacheKey = "blog.{$blogId}.deleted_comment_ids";
        $existing = \Illuminate\Support\Facades\Cache::get($cacheKey, []);
        if (!in_array($commentId, $existing)) {
            $existing[] = $commentId;
            \Illuminate\Support\Facades\Cache::put($cacheKey, $existing, 600); // 10 minutes TTL
        }

        $this->dispatchGroupEvent(new GroupFeedUpdated((int) $blog->group_id, 'comment_deleted', [
            'comment_id' => (int) $commentId,
            'blog_id' => (int) $blogId,
            'comments_count' => (int) $blog->comments()->count(),
        ], (int) auth()->id()));

        return response()->json(['ok' => true]);
    }


    public function commentAPI(Blog $blog){
        $this->authorize('view', $blog);
        $comments = Comment::where('blog_id', $blog->id)->orderBy('created_at', 'asc')->get();
        return view('partials.comments', compact('blog', 'comments'))->render();
    }

    /**
     * Polling endpoint for new and updated comments
     */
    public function commentsFeed(Blog $blog, Request $request)
    {
        $this->authorize('view', $blog);
        $afterId = (int) $request->query('after_id', 0);
        $limit = min(max((int) $request->query('limit', 20), 1), 100);

        // New comments (id > afterId)
        $query = Comment::where('blog_id', $blog->id)
            ->with(['user', 'reactions'])
            ->orderBy('id', 'asc');

        if ($afterId > 0) {
            $query->where('id', '>', $afterId);
        } else {
            $query->latest('id')->take($limit);
        }

        $comments = $query->get();
        if ($afterId === 0) {
            $comments = $comments->sortBy('id')->values();
        }

        $payload = $comments->map(function ($comment) {
            return [
                'id' => (int) $comment->id,
                'html' => view('groups.partials.comment', ['item' => $comment])->render(),
            ];
        })->values();

        // Updated comments (reactions changed in last 90s)
        $updatedComments = [];
        if ($afterId > 0) {
            $updatedComments = Comment::where('blog_id', $blog->id)
                ->with(['user', 'reactions'])
                ->where('id', '<=', $afterId)
                ->where('updated_at', '>=', now()->subSeconds(90))
                ->whereColumn('updated_at', '!=', 'created_at')
                ->get()
                ->map(function ($comment) {
                    return [
                        'id' => (int) $comment->id,
                        'html' => view('groups.partials.comment', ['item' => $comment])->render(),
                    ];
                })->values()->all();
        }

        // Fetch cached deleted comment IDs for this blog
        $deletedCommentIds = array_values(\Illuminate\Support\Facades\Cache::get('blog.' . $blog->id . '.deleted_comment_ids', []));

        return response()->json([
            'status' => 'success',
            'comments' => $payload,
            'comments_count' => (int) $blog->comments()->count(),
            'latest_comment_id' => (int) ($comments->last()->id ?? $afterId),
            'updated_comments' => $updatedComments,
            'deleted_comment_ids' => $deletedCommentIds,
        ]);
    }

    private function dispatchGroupEvent(object $event): void
    {
        app(\App\Services\GroupChat\GroupEventPublisher::class)->publish($event);
    }
}
