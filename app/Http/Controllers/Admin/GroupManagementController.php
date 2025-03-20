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
        $users = $group->users()->get();
        return view('admin.groups.manage', compact('group', 'users'));
    }

    public function update(Request $request, Group $group)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
        ]);

        $group->update($request->only('name', 'description'));

        return redirect()->route('admin.groups.manage', $group)->with('success', 'اطلاعات گروه با موفقیت به‌روزرسانی شد.');
    }

    public function updateRole(Request $request, Group $group, User $user)
    {
        $request->validate([
            'role' => 'required|in:active,observer',
        ]);

        $group->users()->updateExistingPivot($user->id, ['role' => $request->role]);

        return redirect()->route('admin.groups.manage', $group)->with('success', 'نقش کاربر با موفقیت به‌روزرسانی شد.');
    }
}