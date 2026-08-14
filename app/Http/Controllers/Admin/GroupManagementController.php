<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\TemporaryGroupRoleService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    public function manage(Group $group, TemporaryGroupRoleService $roleService)
    {
        $roleService->restoreExpiredForGroup($group->id);
        // با withPivot('role') در relationship، pivot خودکار لود می‌شود
        $users = $group->users()->get();
        $blogs = \App\Models\Blog::where('group_id', $group->id)->with('category')->get();
        return view('admin.groups.manage', compact('group', 'users', 'blogs'));
    }

    public function updateRole(Request $request, Group $group, User $user, TemporaryGroupRoleService $roleService)
    {
        $validated = $request->validate([
            'role' => 'required|integer|in:0,1,2,3,4',
            'duration_unit' => ['required', Rule::in(['day', 'month', 'unlimited'])],
            'duration_value' => ['nullable', 'integer', 'min:1', 'max:31', 'required_unless:duration_unit,unlimited'],
        ]);
        abort_if($validated['duration_unit'] === 'month' && (int) $validated['duration_value'] > 12, 422, 'مدت ماهانه حداکثر ۱۲ ماه است.');

        $membership = GroupUser::query()
            ->where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->firstOrFail();
        $roleService->apply($membership, (int) $validated['role'], $this->roleExpiry($validated), $request->user(), 'system_admin');

        return redirect()->route('admin.groups.manage', $group)->with('success', 'نقش کاربر با موفقیت به‌روزرسانی شد.');
    }

    private function roleExpiry(array $validated): ?\Carbon\CarbonInterface
    {
        if ($validated['duration_unit'] === 'unlimited') {
            return null;
        }

        $value = (int) $validated['duration_value'];

        return $validated['duration_unit'] === 'month'
            ? now()->addMonthsNoOverflow($value)
            : now()->addDays($value);
    }
}
