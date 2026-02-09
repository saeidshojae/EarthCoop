<?php

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Blog;
use App\Models\Group;

class BlogController extends Controller
{
    public function store(Group $group, Request $request)
    {
        $inputs = $request->validate([
            'title'   => 'required|string|max:255',
            'content' => 'required|string',
            'category_id' => 'required|numeric|exists:categories,id',
            'img' => 'nullable|file|max:20480'
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
        $blog->refresh(); // برای اطمینان از بارگذاری روابط

        // Dispatch event for notifications
        event(new \App\Events\BlogCreated($blog, $group, auth()->user()));

        // award points for creating a post (configurable rule)
        try {
            $service = app(\App\Services\ReputationService::class);
            $service->applyAction(auth()->user(), 'post_created', ['blog_id' => $blog->id], $blog->id, 'groups');
        } catch (\Throwable $e) {
            // ignore reputation failures
        }

        return redirect()->back()->with('success', 'پست شما با موفقیت ارسال شد!');
    }

    public function destroy(Blog $blog)
    {
        // Check if the user is the owner of the post
        if ($blog->user_id !== auth()->id()) {
            return response()->json(['status' => 'error', 'message' => 'شما مجوز حذف این پست را ندارید.'], 403);
        }

        $blog->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'پست با موفقیت حذف شد.'
        ]);
    }

    public function update(Request $request, Blog $blog)
    {
        // Check if the user is the owner of the post
        if ($blog->user_id !== auth()->id()) {
            return response()->json(['status' => 'error', 'message' => 'شما مجوز ویرایش این پست را ندارید.'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category_id' => 'required|exists:categories,id'
        ]);

        $blog->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'پست با موفقیت ویرایش شد.'
        ]);
    }
}
