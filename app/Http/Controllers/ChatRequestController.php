<?php

namespace App\Http\Controllers;

use App\Models\ChatRequest;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatRequestController extends Controller
{

    public function send(Request $request, User $user)
    {
        $input = $request->validate([
            'description' => 'required',
            'request_to_group' => 'nullable|numeric'
        ]);
        $request_to_group = isset($input['request_to_group']) ? $input['request_to_group'] : null;
        
        $currentUser = auth()->user();
        
        // Check if a request already exists
        $existingRequest = ChatRequest::where(function ($query) use ($user, $currentUser) {
            $query->where('sender_id', $currentUser->id)
                ->where('receiver_id', $user->id);
        })->orWhere(function ($query) use ($user, $currentUser) {
            $query->where('sender_id', $user->id)
                ->where('receiver_id', $currentUser->id);
        })->first();

        if ($existingRequest) {
            return back()->with('error', 'درخواست چت قبلا ارسال شده است');
        }

        // Create new chat request 
        $chatRequest = ChatRequest::create([
            'sender_id' => $currentUser->id,
            'receiver_id' => $user->id,
            'message' => $input['description'],
            'status' => 'pending',
            'request_to_group' => $request_to_group,
        ]);

        // اگر درخواست برای گروه است، به مدیران گروه اعلان بده
        if ($request_to_group) {
            $group = Group::find($request_to_group);
            if ($group) {
                event(new \App\Events\ChatRequestToGroup($chatRequest, $group, $currentUser));
            }
        }

        return back()->with('success', 'درخواست چت ارسال شد');
    }

    public function accept(ChatRequest $chatRequest)
    {
        $currentUser = auth()->user();
        
        if ($chatRequest->receiver_id !== $currentUser->id) {
            return back()->with('error', 'Unauthorized');
        }

        $chatRequest->update(['status' => 'accepted']);

        // Create a private group for the two users
        $group = Group::create([
            'name' => "گفتگوی خصوصی شما و {$chatRequest->sender->fullName()}",
            'is_private' => true,
            'description' => 'Private chat between ' . $currentUser->fullName() . ' and ' . $chatRequest->sender->fullName(),
            'group_type' => '5',
            'location_level' => '10',
        ]);

        // Add both users to the group
        $group->users()->attach([
            $currentUser->id => ['role' => 1],
            $chatRequest->sender_id => ['role' => 1]
        ]);

        // Update chat request with group_id
        $chatRequest->update(['group_id' => $group->id]);

        return redirect()->route('groups.chat', $group->id);
    }

    public function reject(ChatRequest $chatRequest)
    {
        $currentUser = auth()->user();
        
        if ($chatRequest->receiver_id !== $currentUser->id) {
            return back()->with('error', 'Unauthorized');
        }

        $chatRequest->update(['status' => 'rejected']);

        return back()->with('success', 'درخواست چت رد شد');
    }

    public function pending()
    {
        $currentUser = request()->user();
        
        $pendingRequests = ChatRequest::where('receiver_id', $currentUser->id)
            ->where('status', 'pending')
            ->with('sender')
            ->get();

        return response()->json($pendingRequests);
    }
} 