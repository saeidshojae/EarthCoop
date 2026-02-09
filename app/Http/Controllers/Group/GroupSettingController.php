<?php

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupUserSetting;
use Illuminate\Http\Request;

class GroupSettingController extends Controller
{
    /**
     * Get or create user settings for a group
     */
    public function getSettings(Group $group)
    {
        $user = auth()->user();
        
        // Check if user is member of the group
        $isMember = $group->users()->whereKey($user->id)->exists();
        if (!$isMember) {
            return response()->json(['status' => 'error', 'message' => 'شما عضو این گروه نیستید.'], 403);
        }

        $settings = GroupUserSetting::firstOrCreate(
            ['group_id' => $group->id, 'user_id' => $user->id],
            [
                'muted' => false,
                'archived' => false,
                'notification_settings' => [
                    'new_messages' => true,
                    'mentions' => true,
                    'replies' => true,
                    'reactions' => false,
                ]
            ]
        );

        return response()->json([
            'status' => 'success',
            'settings' => [
                'muted' => $settings->isMuted(),
                'archived' => $settings->archived,
                'notification_settings' => $settings->notification_settings,
                'muted_until' => $settings->muted_until?->toIso8601String(),
            ]
        ]);
    }

    /**
     * Update user settings for a group
     */
    public function updateSettings(Request $request, Group $group)
    {
        $request->validate([
            'muted' => 'nullable|boolean',
            'archived' => 'nullable|boolean',
            'muted_until' => 'nullable|date|after:now',
            'notification_settings' => 'nullable|array',
            'notification_settings.new_messages' => 'nullable|boolean',
            'notification_settings.mentions' => 'nullable|boolean',
            'notification_settings.replies' => 'nullable|boolean',
            'notification_settings.reactions' => 'nullable|boolean',
        ]);

        $user = auth()->user();
        
        // Check if user is member of the group
        $isMember = $group->users()->whereKey($user->id)->exists();
        if (!$isMember) {
            return response()->json(['status' => 'error', 'message' => 'شما عضو این گروه نیستید.'], 403);
        }

        $settings = GroupUserSetting::firstOrCreate(
            ['group_id' => $group->id, 'user_id' => $user->id]
        );

        $updateData = [];
        
        if ($request->has('muted')) {
            $updateData['muted'] = $request->muted;
            if (!$request->muted) {
                $updateData['muted_until'] = null;
            }
        }

        if ($request->has('archived')) {
            $updateData['archived'] = $request->archived;
        }

        if ($request->has('muted_until')) {
            $updateData['muted_until'] = $request->muted_until;
            if ($request->muted_until) {
                $updateData['muted'] = true;
            }
        }

        if ($request->has('notification_settings')) {
            $currentSettings = $settings->notification_settings ?? [];
            $updateData['notification_settings'] = array_merge($currentSettings, $request->notification_settings);
        }

        $settings->update($updateData);

        return response()->json([
            'status' => 'success',
            'message' => 'تنظیمات با موفقیت به‌روزرسانی شد.',
            'settings' => [
                'muted' => $settings->isMuted(),
                'archived' => $settings->archived,
                'notification_settings' => $settings->notification_settings,
                'muted_until' => $settings->muted_until?->toIso8601String(),
            ]
        ]);
    }

    /**
     * Toggle mute status
     */
    public function toggleMute(Group $group)
    {
        $user = auth()->user();
        
        $isMember = $group->users()->whereKey($user->id)->exists();
        if (!$isMember) {
            return response()->json(['status' => 'error', 'message' => 'شما عضو این گروه نیستید.'], 403);
        }

        $settings = GroupUserSetting::firstOrCreate(
            ['group_id' => $group->id, 'user_id' => $user->id]
        );

        // Toggle mute status
        $newMutedStatus = !$settings->muted;
        $settings->update([
            'muted' => $newMutedStatus,
            'muted_until' => null
        ]);

        // Reload to get updated value
        $settings->refresh();

        return response()->json([
            'status' => 'success',
            'message' => $settings->muted ? 'گروه بی‌صدا شد.' : 'صدا بازگردانده شد.',
            'muted' => $settings->muted
        ]);
    }

    /**
     * Toggle archive status
     */
    public function toggleArchive(Group $group)
    {
        $user = auth()->user();
        
        $isMember = $group->users()->whereKey($user->id)->exists();
        if (!$isMember) {
            return response()->json(['status' => 'error', 'message' => 'شما عضو این گروه نیستید.'], 403);
        }

        $settings = GroupUserSetting::firstOrCreate(
            ['group_id' => $group->id, 'user_id' => $user->id]
        );

        // Toggle archive status
        $newArchivedStatus = !$settings->archived;
        $settings->update([
            'archived' => $newArchivedStatus
        ]);

        // Reload to get updated value
        $settings->refresh();

        return response()->json([
            'status' => 'success',
            'message' => $settings->archived ? 'گروه بایگانی شد.' : 'گروه از بایگانی خارج شد.',
            'archived' => $settings->archived
        ]);
    }
}

