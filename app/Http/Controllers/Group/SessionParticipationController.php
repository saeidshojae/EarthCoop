<?php

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupSessionParticipationRequest;
use App\Models\GroupUser;
use App\Notifications\GroupSessionParticipationRequested;
use App\Events\GroupFeedUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SessionParticipationController extends Controller
{
    public function state(Group $group)
    {
        $this->authorize('view', $group);

        $canManage = auth()->user()->can('manageSession', $group);

        return response()->json([
            'status' => 'success',
            'session_open' => (bool) $group->is_open,
            'can_participate' => auth()->user()->can('participate', $group),
            'pending_requests_count' => $canManage
                ? GroupSessionParticipationRequest::where('group_id', $group->id)->where('status', 'pending')->count()
                : 0,
        ]);
    }

    public function store(Request $request, Group $group)
    {
        $this->authorize('view', $group);
        abort_if((bool) $group->is_open, 422, 'نشست فعال است و نیازی به درخواست مشارکت نیست.');
        abort_if(auth()->user()->can('participate', $group), 422, 'شما هم‌اکنون مجوز مشارکت دارید.');

        $validated = $request->validate(['message' => 'nullable|string|max:300']);
        $existing = GroupSessionParticipationRequest::where('group_id', $group->id)
            ->where('user_id', auth()->id())->first();
        $isNewRequest = ! $existing || $existing->status !== 'pending';
        $participationRequest = GroupSessionParticipationRequest::updateOrCreate(
            ['group_id' => $group->id, 'user_id' => auth()->id()],
            ['status' => 'pending', 'message' => $validated['message'] ?? null, 'resolved_by' => null, 'resolved_at' => null]
        );

        if ($isNewRequest) {
            $requester = auth()->user();
            $moderators = $group->users()->wherePivot('status', 1)->wherePivotIn('role', [2, 3])->get();
            $moderators->each->notify(new GroupSessionParticipationRequested($participationRequest, $group, $requester));

            event(new GroupFeedUpdated((int) $group->id, 'session_participation_requested', [
                'request_id' => (int) $participationRequest->id,
                'requester_id' => (int) $requester->id,
                'requester_name' => trim(($requester->first_name ?? '') . ' ' . ($requester->last_name ?? '')) ?: 'یکی از اعضا',
                'message' => $participationRequest->message,
                'pending_count' => GroupSessionParticipationRequest::where('group_id', $group->id)->where('status', 'pending')->count(),
            ], (int) $requester->id));
        }

        return response()->json([
            'status' => 'success',
            'message' => 'درخواست مشارکت شما برای مدیران و بازرسان ارسال شد.',
            'request' => ['id' => $participationRequest->id, 'status' => $participationRequest->status],
            'already_pending' => ! $isNewRequest,
        ]);
    }

    public function index(Group $group)
    {
        $this->authorize('manageSession', $group);

        $members = GroupUser::query()
            ->where('group_id', $group->id)->where('status', 1)
            ->whereNotIn('role', [2, 3])->with('user:id,first_name,last_name,email,avatar')
            ->get()->map(fn (GroupUser $membership) => [
                'id' => (int) $membership->user_id,
                'name' => trim(($membership->user?->first_name ?? '') . ' ' . ($membership->user?->last_name ?? '')) ?: 'عضو گروه',
                'email' => $membership->user?->email,
                'role' => (int) $membership->role,
                'allowed' => (bool) $membership->session_write_allowed,
            ])->values();

        $requests = GroupSessionParticipationRequest::query()
            ->where('group_id', $group->id)->where('status', 'pending')
            ->with('user:id,first_name,last_name,email,avatar')->latest()->get()
            ->map(fn ($item) => [
                'id' => (int) $item->id,
                'user_id' => (int) $item->user_id,
                'name' => trim(($item->user?->first_name ?? '') . ' ' . ($item->user?->last_name ?? '')) ?: 'عضو گروه',
                'message' => $item->message,
                'requested_at' => optional($item->created_at)->diffForHumans(),
            ])->values();

        return response()->json(['status' => 'success', 'requests' => $requests, 'members' => $members]);
    }

    public function bulkUpdate(Request $request, Group $group)
    {
        $this->authorize('manageSession', $group);
        $validated = $request->validate([
            'user_ids' => 'required|array|min:1|max:200',
            'user_ids.*' => 'integer|distinct',
            'action' => 'required|in:grant,revoke,reject',
        ]);

        $memberships = GroupUser::query()->where('group_id', $group->id)->where('status', 1)
            ->whereIn('user_id', $validated['user_ids'])->whereNotIn('role', [2, 3])->get();
        abort_if($memberships->isEmpty(), 422, 'هیچ عضو معتبری انتخاب نشده است.');

        DB::transaction(function () use ($memberships, $validated, $group) {
            $grant = $validated['action'] === 'grant';
            if ($validated['action'] !== 'reject') {
                GroupUser::whereKey($memberships->pluck('id'))->update(['session_write_allowed' => $grant]);
            }
            GroupSessionParticipationRequest::where('group_id', $group->id)
                ->whereIn('user_id', $memberships->pluck('user_id'))->where('status', 'pending')
                ->update([
                    'status' => $grant ? 'approved' : 'rejected',
                    'resolved_by' => auth()->id(), 'resolved_at' => now(), 'updated_at' => now(),
                ]);
        });

        event(new GroupFeedUpdated((int) $group->id, 'session_participation_resolved', [
            'user_ids' => $memberships->pluck('user_id')->map(fn ($id) => (int) $id)->values()->all(),
            'action' => $validated['action'],
            'pending_count' => GroupSessionParticipationRequest::where('group_id', $group->id)->where('status', 'pending')->count(),
        ], (int) auth()->id()));

        return response()->json([
            'status' => 'success',
            'message' => match ($validated['action']) {
                'grant' => 'مجوز مشارکت برای اعضای انتخاب‌شده فعال شد.',
                'revoke' => 'مجوز مشارکت اعضای انتخاب‌شده لغو شد.',
                default => 'درخواست‌های انتخاب‌شده رد شدند.',
            },
        ]);
    }
}
