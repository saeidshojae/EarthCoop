<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Message;
use Illuminate\Http\Request;
use App\Events\MessageSent;

class MessageController extends Controller
{
    public function store(Request $request, Group $group)
    {
        // بررسی عضویت کاربر در گروه
        if (!$group->users()->where('user_id', auth()->id())->exists()) {
            return response()->json(['error' => 'شما عضو این گروه نیستید.'], 403);
        }

        $request->validate([
            'message' => 'required|string|max:255',
        ]);

        // ایجاد پیام جدید
        $message = $group->messages()->create([
            'user_id' => auth()->id(),
            'message' => $request->message,
        ]);

        // لود کردن اطلاعات کاربر برای پیام
        $message->load(['user' => function($query) {
            $query->select('id', 'first_name', 'last_name', 'avatar')
                ->selectRaw("CONCAT(first_name, ' ', last_name) as full_name");
        }]);

        // ارسال رویداد به سایر کاربران
        broadcast(new MessageSent($message))->toOthers();

        if ($request->ajax()) {
            return response()->json([
                'message' => $message,
                'user' => [
                    'id' => $message->user->id,
                    'name' => $message->user->full_name,
                    'avatar' => $message->user->avatar,
                ],
                'created_at' => $message->created_at->format('H:i'),
            ]);
        }

        return back();
    }

    public function index(Group $group)
    {
        // بررسی عضویت کاربر در گروه
        if (!$group->users()->where('user_id', auth()->id())->exists()) {
            return response()->json(['error' => 'شما عضو این گروه نیستید.'], 403);
        }

        // دریافت پیام‌ها با اطلاعات کاربر
        $messages = $group->messages()
            ->with(['user' => function($query) {
                $query->select('id', 'first_name', 'last_name', 'avatar')
                    ->selectRaw("CONCAT(first_name, ' ', last_name) as full_name");
            }])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($message) {
                return [
                    'id' => $message->id,
                    'message' => $message->message,
                    'user' => [
                        'id' => $message->user->id,
                        'name' => $message->user->full_name,
                        'avatar' => $message->user->avatar,
                    ],
                    'created_at' => $message->created_at->format('H:i'),
                    'is_current_user' => $message->user_id === auth()->id(),
                ];
            });

        return response()->json($messages);
    }
}