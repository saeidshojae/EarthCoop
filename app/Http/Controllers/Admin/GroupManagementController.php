<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;

class GroupManagementController extends Controller
{
    public function index(Request $request)
    {
        $query = Group::query();

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
        }

        $groups = $query->get();

        $generalGroups = $groups->filter(function($group) {
            return strtolower(trim($group->group_type)) === 'general';
        });

        $specializedGroups = $groups->filter(function($group) {
            return strtolower(trim($group->group_type)) === 'specialized';
        });

        $exclusiveGroups = $groups->filter(function($group) {
            return strtolower(trim($group->group_type)) === 'exclusive';
        });

        return view('admin.groups.manage_groups', compact('generalGroups', 'specializedGroups', 'exclusiveGroups'));
    }

    public function manage(Group $group)
    {
        // با withPivot('role') در relationship، pivot خودکار لود می‌شود
        $users = $group->users()->get();
        $blogs = \App\Models\Blog::where('group_id', $group->id)->with('category')->get();
        return view('admin.groups.manage', compact('group', 'users', 'blogs'));
    }

    public function updateRole(Request $request, Group $group, User $user)
    {
        $request->validate([
            'role' => 'required|integer|in:0,1,2,3,4',
        ]);

        $group->users()->updateExistingPivot($user->id, ['role' => (int) $request->role]);

        return redirect()->route('admin.groups.manage', $group)->with('success', 'نقش کاربر با موفقیت به‌روزرسانی شد.');
    }
}