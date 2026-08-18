<?php

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupUser;
use App\Services\NajmHoda\NajmHodaGroupAttentionPanelService;
use App\Services\NajmHoda\NajmHodaGroupManagementSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NajmHodaAttentionController extends Controller
{
    public function show(
        Group $group,
        NajmHodaGroupAttentionPanelService $service,
        NajmHodaGroupManagementSnapshotService $management
    ): JsonResponse {
        $role = $this->authorizeLeadership($group);

        return response()->json([
            'status' => 'success',
            'attention' => $service->snapshot($group),
            'management' => $management->snapshot($group, $role),
        ]);
    }

    public function update(Request $request, Group $group, NajmHodaGroupAttentionPanelService $service): JsonResponse
    {
        $this->authorizeLeadership($group);

        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'digest_mode' => 'required|in:immediate,daily,off',
            'preferred_time' => ['required', 'regex:/^(?:[01]\\d|2[0-3]):[0-5]\\d$/'],
            'timezone' => 'required|string|max:64',
            'due_soon_hours' => 'required|integer|min:1|max:720',
            'suppress_minutes' => 'required|integer|min:60|max:10080',
            'alert_overdue' => 'required|boolean',
            'alert_due_soon' => 'required|boolean',
            'alert_blocked' => 'required|boolean',
            'alert_urgent' => 'required|boolean',
            'alert_unassigned' => 'required|boolean',
        ]);

        $service->updatePolicy($group, $validated);

        return response()->json([
            'status' => 'success',
            'message' => 'تنظیمات پیگیری فعال نجم هدا ذخیره شد.',
            'attention' => $service->snapshot($group),
        ]);
    }

    protected function authorizeLeadership(Group $group): int
    {
        $role = (int) GroupUser::query()
            ->where('group_id', $group->id)
            ->where('user_id', auth()->id())
            ->where('status', 1)
            ->value('role');

        abort_unless(in_array($role, [2, 3], true), 403);

        return $role;
    }
}
