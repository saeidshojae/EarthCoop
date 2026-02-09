<?php

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Blog;
use App\Models\GroupUser;
use App\Models\Comment;

class CommentController extends Controller
{
    public function comment(Blog $blog)
    {
        $comments = Comment::where('blog_id', $blog->id)->orderBy('created_at', 'asc')->get();
        $group = $blog->group;
        
        $yourRole = GroupUser::where('group_id', $blog->group_id)
            ->where('user_id', auth()->id())
            ->value('role');
            
        return view('groups.comment', compact('blog', 'comments', 'group', 'yourRole'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'blog_id' => 'required|exists:blogs,id',
            'parent_id' => 'nullable|numeric|exists:comments,id',
            'message' => 'required|string|max:2000',
        ]);

        $comment = Comment::create([
            'user_id' => auth()->id(),
            'blog_id' => $request->blog_id,
            'message' => $request->message,
            'parent_id' => $request->parent_id,
        ]);
        $comment->refresh(); // برای اطمینان از بارگذاری روابط

        // Dispatch event for notifications
        $blog = $comment->blog;
        if ($blog && $blog->group) {
            event(new \App\Events\CommentCreated($comment, $blog, $blog->group, auth()->user()));
        }

        // award points for creating a comment
        try {
            $service = app(\App\Services\ReputationService::class);
            $service->applyAction(auth()->user(), 'comment_created', ['comment_id' => $comment->id], $comment->id, 'groups');
        } catch (\Throwable $e) {
            // ignore
        }

        return response()->json([
            'status' => 'success',
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
        // اطمینان از مالکیت نظر
        abort_if($comment->user_id !== auth()->id(), 403);

        $data = $request->validate([
            'message' => ['required','string'],
        ]);

        $comment->message = $data['message'];
        $comment->save();

        return response()->json([
            'ok' => true,
            'message' => $comment->message,
        ]);
    }

    public function destroy(Comment $comment)
    {
        abort_if($comment->user_id !== auth()->id(), 403);

        $comment->delete();

        return response()->json(['ok' => true]);
    }


    public function commentAPI(Blog $blog){
        $comments = Comment::where('blog_id', $blog->id)->orderBy('created_at', 'asc')->get();
        return view('partials.comments', compact('blog', 'comments'))->render();
    }
}
