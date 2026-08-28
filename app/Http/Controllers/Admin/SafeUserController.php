<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Modules\NajmBahar\Services\MembershipRemovalService;
use App\Services\Users\UserManagementService;
use Illuminate\Http\Request;

class SafeUserController extends UserController
{
    public function __construct(
        private readonly MembershipRemovalService $membershipRemoval,
        private readonly UserManagementService $userManagement,
    ) {
    }

    public function update(Request $request, User $user)
    {
        $requestedStatus = $request->input('status');

        // Preserve the current lifecycle state while the legacy profile updater
        // handles non-lifecycle fields. Status changes are applied only through
        // the canonical UserManagementService below.
        if ($requestedStatus !== null) {
            $currentStatus = (string) $user->status;
            if (! in_array($currentStatus, ['active', 'inactive', 'suspended'], true)) {
                $currentStatus = 'active';
            }
            $request->merge(['status' => $currentStatus]);
        }

        $response = parent::update($request, $user);

        if ($requestedStatus !== null) {
            $result = $this->userManagement->setStatus($user->fresh(), (string) $requestedStatus);
            if (! (bool) ($result['success'] ?? false)) {
                return back()->with('error', 'تغییر وضعیت این هویت مجاز نیست');
            }
        }

        return $response;
    }

    public function destroy(User $user)
    {
        $this->membershipRemoval->remove($user->id, [
            'source' => 'admin.users.destroy',
            'actor_user_id' => auth()->id(),
        ]);

        return back()->with('success', 'عضویت کاربر با حفظ دارایی‌ها و سوابق مالی خاتمه یافت');
    }

    public function updateStatus(Request $request, User $user)
    {
        $validated = $request->validate([
            'status' => 'required|in:active,inactive,suspended',
        ]);

        $result = $this->userManagement->setStatus($user, (string) $validated['status']);
        if (! (bool) ($result['success'] ?? false)) {
            return back()->with('error', 'تغییر وضعیت این هویت مجاز نیست');
        }

        return back()->with('success', 'وضعیت کاربر با موفقیت تغییر کرد');
    }

    public function bulkAction(Request $request)
    {
        $action = (string) $request->input('action');

        if (in_array($action, ['activate', 'deactivate', 'suspend'], true)) {
            $validated = $request->validate([
                'action' => 'required|in:activate,deactivate,suspend',
                'user_ids' => 'required|array',
                'user_ids.*' => 'exists:users,id',
            ]);

            $status = match ($validated['action']) {
                'activate' => 'active',
                'deactivate' => 'inactive',
                'suspend' => 'suspended',
            };

            $changed = 0;
            $protected = 0;
            foreach (User::query()->whereIn('id', $validated['user_ids'])->get() as $user) {
                $result = $this->userManagement->setStatus($user, $status);
                if ((bool) ($result['success'] ?? false)) {
                    $changed++;
                } else {
                    $protected++;
                }
            }

            $message = $changed . ' کاربر بروزرسانی شدند';
            if ($protected > 0) {
                $message .= '؛ ' . $protected . ' هویت محافظت‌شده بدون تغییر باقی ماند';
            }

            return back()->with('success', $message);
        }

        if ($action !== 'delete') {
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
