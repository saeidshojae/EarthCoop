<?php

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Blog;
use App\Models\Comment;
use App\Models\Reaction;
class ReactionController extends Controller
{
    public function blogReact(Request $request, Blog $blog)
    {
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
                    try {
                        app(\App\Services\ReputationService::class)->applyAction(auth()->user(), 'post_upvoted', ['blog_id' => $blog->id], $blog->id, 'groups');
                    } catch (\Throwable $e) {}
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
                try {
                    app(\App\Services\ReputationService::class)->applyAction(auth()->user(), 'post_upvoted', ['blog_id' => $blog->id], $blog->id, 'groups');
                } catch (\Throwable $e) {}
            }
        }
    
        return response()->json([
            'status' => 'success',
            'likes' => $blog->reactions()->where('type', 1)->count(),
            'dislikes' => $blog->reactions()->where('type', 0)->count(),
        ]);
    }
    
    public function commentReact(Request $request, Comment $comment)
    {
        $type = $request->type === 'like' ? 1 : 0;
        $user = auth()->user();
    
        // بررسی اینکه آیا قبلاً واکنش داده
        $existing = $comment->reactions()->where('user_id', $user->id)->first();
    
        if ($existing) {
            if ($existing->type == $type) {
                // اگر همون نوع رأی قبلاً ثبت شده → حذفش کن
                $existing->delete();

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

        return response()->json([
            'status' => 'success',
            'likes' => $comment->reactions()->where('type', 1)->count(),
            'dislikes' => $comment->reactions()->where('type', 0)->count(),
            'id' => $comment->id,
        ]);
    }
    
}
