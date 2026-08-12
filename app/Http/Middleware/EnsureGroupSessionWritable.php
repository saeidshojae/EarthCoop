<?php

namespace App\Http\Middleware;

use App\Models\Blog;
use App\Models\Comment;
use App\Models\Group;
use App\Models\Message;
use App\Models\Poll;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EnsureGroupSessionWritable
{
    public function handle(Request $request, Closure $next)
    {
        $group = $this->resolveGroup($request);

        abort_unless($group, 404, 'گروه یافت نشد.');
        Gate::authorize('participate', $group);

        return $next($request);
    }

    private function resolveGroup(Request $request): ?Group
    {
        $route = $request->route();
        $group = $route?->parameter('group');
        if ($group instanceof Group) {
            return $group;
        }
        if (is_numeric($group)) {
            return Group::find($group);
        }

        foreach (['message' => Message::class, 'poll' => Poll::class, 'blog' => Blog::class] as $key => $class) {
            $model = $route?->parameter($key);
            if ($model instanceof $class) {
                return $model->group;
            }
        }

        $comment = $route?->parameter('comment');
        if ($comment instanceof Comment) {
            return $comment->blog?->group;
        }

        if ($request->filled('group_id')) {
            return Group::find($request->integer('group_id'));
        }
        if ($request->filled('blog_id')) {
            return Blog::find($request->integer('blog_id'))?->group;
        }

        return null;
    }
}
