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
                // award if switched to like
                if ($type == 1) {
                    $this->awardPostUpvoteAfterResponse($user, (int) $blog->id);
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
                $this->awardPostUpvoteAfterResponse($user, (int) $blog->id);
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

        // award for comment upvote
        if ($type == 1) {
            try {
                app(\App\Services\ReputationService::class)->applyAction(auth()->user(), 'comment_upvoted', ['comment_id' => $comment->id], $comment->id, 'groups');
            } catch (\Throwable $e) {}
        }

        // Touch comment for real-time updates to other users
        $comment->touch();

        return response()->json([
            'status' => 'success',
            'likes' => $comment->reactions()->where('type', 1)->count(),
            'dislikes' => $comment->reactions()->where('type', 0)->count(),
            'id' => $comment->id,
        ]);
    }

    private function dispatchGroupEvent(object $event): void
    {
        if (! (bool) config('group-chat.enabled', true)
            || strtolower((string) config('group-chat.transport', 'auto')) === 'polling') {
            return;
        }

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
    }

    private function awardPostUpvoteAfterResponse($user, int $blogId): void
    {
        dispatch(static function () use ($user, $blogId): void {
            try {
                app(\App\Services\ReputationService::class)->applyAction(
                    $user,
                    'post_upvoted',
                    ['blog_id' => $blogId],
                    $blogId,
                    'groups'
                );
            } catch (\Throwable $exception) {
                \Illuminate\Support\Facades\Log::warning('post_reaction_reputation_failed', [
                    'blog_id' => $blogId,
                    'message' => $exception->getMessage(),
                ]);
            }
        })->afterResponse();
    }
    
}
