<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Blog;
use App\Models\GroupUser;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function index(Request $request){
        $groupsQuery = Group::with(['experience', 'specialty', 'ageGroup']);

        // فیلتر user
        if($request->has('user') && $request->user){
            $groupUserIds = GroupUser::where('user_id', $request->user)->pluck('group_id')->toArray();
            $groupsQuery->whereIn('id', $groupUserIds);
        }

        // فیلتر level
        if($request->has('level') && $request->level){
            $groupsQuery->where('location_level', $request->level);
        }

        // فیلتر sort
        if($request->has('sort') && $request->sort){
            switch($request->sort){
                case 'experience':
                    $groupsQuery->whereNotNull('experience_id');
                    break;
                case 'job':
                    $groupsQuery->whereNotNull('specialty_id');
                    break;
                case 'age':
                    $groupsQuery->whereNotNull('age_group_id');
                    break;
                case 'gender':
                    $groupsQuery->whereNotNull('gender');
                    break;
                case 'total':
                    $groupsQuery->whereNull('experience_id')
                                ->whereNull('specialty_id')
                                ->whereNull('age_group_id')
                                ->whereNull('gender');
                    break;
            }
        }

        $groups = $groupsQuery->orderBy('created_at', 'desc')->get();

        return view('admin.groups.index', compact('groups'));
    }

    public function update(Request $request, Group $group){
        $inputs = $request->validate([
            'name' => 'required|string',
            'description' => 'nullable',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($request->hasFile('avatar')) {
            $avatar = $request->file('avatar');
            $filename = time() . '.' . $avatar->getClientOriginalExtension();
            $avatar->move(public_path('uploads/groups/avatars'), $filename);
            $inputs['avatar'] = 'uploads/groups/avatars/' . $filename;
        }

        $group->update($inputs);
        return back()->with('success', 'اطلاعات گروه با موفقیت به‌روزرسانی شد');
    }
    
    public function postUpdate(Request $request, Blog $blog){
        $inputs = $request->validate([
            'title'   => 'required|string|max:255',
            'content' => 'required|string',
            'category_id' => 'nullable|numeric|exists:categories,id',
            'img' => 'nullable|file|max:20480'
        ]);
    
        if($inputs['category_id'] == null){
            unset($inputs['category_id']);
        }

        if ($request->hasFile('img') && $request->file('img')->isValid()) {
            $file = $request->file('img');
            $name = time() . '.' . $file->getClientOriginalExtension();
            $inputs['file_type'] = $file->getMimeType();
            $file->move(public_path('images/blogs'), $name);
            $inputs['img'] = $name;
        }else{
            unset($inputs['img']);
        }

        $blog->update($inputs);

        return redirect()->back()->with('success', 'پست شما با موفقیت ارسال شد!');
    }
    
    public function delete(Group $group){
        $group->delete();
        return back()->with('success', 'گروه با موفقیت حذف شد');
    }
    
    public function postDelete(Blog $blog){
        $blog->delete();
        return back()->with('success', 'پست شما با موفقیت حذف شد!');;
    }
    
    
}
