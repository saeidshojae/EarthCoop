<?php

namespace App\Http\Controllers\Group;

use App\Events\GroupFeedUpdated;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Blog;
use App\Models\Comment;
use App\Models\Reaction;
class ReactionController extends Controller
{
    public function blogReact(Request $request, Blog $blog)
    {
        $this->authorize('view', $blog);
        $request->validate(['type' => 'required|in:0,1']);
        $user = auth()->user();
        $type = $request->type;
    
        // بررسی اینکه آیا همین نوع ری‌اکشن قبلاً ثبت شده
        $existing = Reaction::where([
            'user_id' => $user->id,
            'blog_id' => $blog->id,
        ])->first();
    
        if ($existing) {
            if ($existing->type == $type) {
                // اگه دوباره روی همون کلیک شده، حذفش کن (toggle off)
                $existing->delete();
            } else {
                // اگه نوعش فرق داره، آپدیت کن
                $existing->update(['type' => $type]);
                if ($type == 1) {
                    $this->awardPostLikeAfterResponse($blog, $user);
                }
            }
        } else {
            // ری‌اکشن جدید
            Reaction::create([
                'user_id' => $user->id,
                'blog_id' => $blog->id,
                'type' => $type,
            ]);

            if ($type == 1) {
                $this->awardPostLikeAfterResponse($blog, $user);
            }
        }
    
        $likes = $blog->reactions()->where('type', 1)->count();
        $dislikes = $blog->reactions()->where('type', 0)->count();

        // لمس کردن blog برای اینکه سایر کاربران از طریق postsFeed آپدیت را دریافت کنند
        $blog->touch();

        $this->dispatchGroupEvent(new GroupFeedUpdated((int) $blog->group_id, 'post_reaction', [
            'post_id' => (int) $blog->id,
            'likes' => (int) $likes,
            'dislikes' => (int) $dislikes,
        ], (int) auth()->id()));

        return response()->json([
            'status' => 'success',
            'likes' => $likes,
            'dislikes' => $dislikes,
            'user_reaction' => $blog->reactions()->where('user_id', $user->id)->value('type'),
        ]);
    }
    
    public function commentReact(Request $request, Comment $comment)
    {
        $this->authorize('view', $comment);
        $request->validate(['type' => 'required|in:like,dislike']);
        $type = $request->type === 'like' ? 1 : 0;
        $user = auth()->user();
    
        // بررسی اینکه آیا قبلاً واکنش داده
        $existing = $comment->reactions()->where('user_id', $user->id)->first();
    
        if ($existing) {
            if ($existing->type == $type) {
                // اگر همون نوع رأی قبلاً ثبت شده → حذفش کن
                $existing->delete();
                // Touch comment for real-time updates
                $comment->touch();

                $this->publishCommentReaction($comment, $user->id);

                return response()->json([
                    'status' => 'removed',
                    'likes' => $comment->reactions()->where('type', 1)->count(),
                    'dislikes' => $comment->reactions()->where('type', 0)->count(),
                    'id' => $comment->id,
                ]);
            } else {
                // اگر رأی نوع دیگه‌ای بود → حذف قبلی
                $existing->delete();
            }
        }

        // ایجاد رأی جدید
        Reaction::create([
            'comment_id' => $comment->id,
            'user_id' => $user->id,
            'type' => $type,
            'react_type' => 1
        ]);

        if ($type == 1) {
            $this->awardCommentLike($comment, $user);
        }

        // Touch comment for real-time updates to other users
        $comment->touch();
        $this->publishCommentReaction($comment, $user->id);

        return response()->json([
            'status' => 'success',
            'likes' => $comment->reactions()->where('type', 1)->count(),
            'dislikes' => $comment->reactions()->where('type', 0)->count(),
            'id' => $comment->id,
        ]);
    }

    private function dispatchGroupEvent(object $event): void
    {
        app(\App\Services\GroupChat\GroupEventPublisher::class)->publish($event);
    }

    private function publishCommentReaction(Comment $comment, int $actorId): void
    {
        $comment->load(['user', 'reactions', 'blog']);
        $this->dispatchGroupEvent(new GroupFeedUpdated((int) $comment->blog->group_id, 'comment_reaction', [
            'comment_id' => (int) $comment->id,
            'blog_id' => (int) $comment->blog_id,
            'comments_count' => (int) $comment->blog->comments()->count(),
            'likes' => (int) $comment->reactions->where('type', 1)->count(),
            'dislikes' => (int) $comment->reactions->where('type', 0)->count(),
            'html' => view('groups.partials.comment', ['item' => $comment])->render(),
        ], $actorId));
    }

    private function awardPostLikeAfterResponse(Blog $blog, $reactor): void
    {
        $blogId = (int) $blog->id;
        $reactorId = (int) $reactor->id;
        $owner = $blog->user;
        $selfLike = $owner && (int) $owner->id === $reactorId;

        dispatch(static function () use ($reactor, $owner, $blogId, $reactorId, $selfLike): void {
            try {
                $reputation = app(\App\Services\ReputationService::class);
                $reputation->applyAction(
                    $reactor,
                    'post_liked',
                    ['blog_id' => $blogId, 'reactor_id' => $reactorId, 'self_like' => (bool) $selfLike],
                    $blogId,
                    'groups',
                    'post_liked:' . $blogId . ':reactor:' . $reactorId,
                    $selfLike ? false : null
                );
                if ($owner) {
                    $reputation->applyAction(
                        $owner,
                        'post_upvoted',
                        ['blog_id' => $blogId, 'reactor_id' => $reactorId, 'self_like' => (bool) $selfLike],
                        $blogId,
                        'groups',
                        'post_upvoted:' . $blogId . ':reactor:' . $reactorId,
                        $selfLike ? false : null
                    );
                }
            } catch (\Throwable $exception) {
                \Illuminate\Support\Facades\Log::warning('post_reaction_reputation_failed', [
                    'blog_id' => $blogId,
                    'reactor_id' => $reactorId,
                    'message' => $exception->getMessage(),
                ]);
            }
        })->afterResponse();
    }

    private function awardCommentLike(Comment $comment, $reactor): void
    {
        $reactorId = (int) $reactor->id;
        $owner = $comment->user;
        $selfLike = $owner && (int) $owner->id === $reactorId;

        try {
            $reputation = app(\App\Services\ReputationService::class);
            $reputation->applyAction(
                $reactor,
                'comment_liked',
                ['comment_id' => $comment->id, 'reactor_id' => $reactorId, 'self_like' => (bool) $selfLike],
                $comment->id,
                'groups',
                'comment_liked:' . $comment->id . ':reactor:' . $reactorId,
                $selfLike ? false : null
            );
            if ($owner) {
                $reputation->applyAction(
                    $owner,
                    'comment_upvoted',
                    ['comment_id' => $comment->id, 'reactor_id' => $reactorId, 'self_like' => (bool) $selfLike],
                    $comment->id,
                    'groups',
                    'comment_upvoted:' . $comment->id . ':reactor:' . $reactorId,
                    $selfLike ? false : null
                );
            }
        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Log::warning('comment_reaction_reputation_failed', [
                'comment_id' => $comment->id,
                'reactor_id' => $reactorId,
                'message' => $exception->getMessage(),
            ]);
        }
    }
    
}
