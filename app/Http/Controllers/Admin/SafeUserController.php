<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Modules\NajmBahar\Services\MembershipRemovalService;
use Illuminate\Http\Request;

class SafeUserController extends UserController
{
    public function __construct(
        private readonly MembershipRemovalService $membershipRemoval,
    ) {
    }

    public function destroy(User $user)
    {
        $this->membershipRemoval->remove($user->id, [
            'source' => 'admin.users.destroy',
            'actor_user_id' => auth()->id(),
        ]);

        return back()->with('success', 'عضویت کاربر با حفظ دارایی‌ها و سوابق مالی خاتمه یافت');
    }

    public function bulkAction(Request $request)
    {
        if ($request->input('action') !== 'delete') {
            return parent::bulkAction($request);
        }

        $validated = $request->validate([
            'action' => 'required|in:delete',
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        foreach (array_values(array_unique(array_map('intval', $validated['user_ids']))) as $userId) {
            $this->membershipRemoval->remove($userId, [
                'source' => 'admin.users.bulkAction',
                'actor_user_id' => auth()->id(),
            ]);
        }

        return back()->with(
            'success',
            count($validated['user_ids']) . ' عضویت با حفظ دارایی‌ها و سوابق مالی خاتمه یافت'
        );
    }
}
