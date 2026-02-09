<?php

namespace App\Http\Controllers\User;

use App\Events\SupportChatMessageSent;
use App\Http\Controllers\Controller;
use App\Models\SupportChat;
use App\Models\SupportChatMessage;
use App\Services\SupportChatAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SupportChatController extends Controller
{
    protected SupportChatAssignmentService $assignmentService;

    public function __construct(SupportChatAssignmentService $assignmentService)
    {
        $this->middleware('auth');
        $this->assignmentService = $assignmentService;
    }

    /**
     * نمایش صفحه چت یا ایجاد چت جدید
     */
    public function index()
    {
        $chat = SupportChat::where('user_id', auth()->id())
            ->whereIn('status', ['waiting', 'active'])
            ->latest()
            ->first();

        if (!$chat) {
            $chat = SupportChat::create([
                'user_id' => auth()->id(),
                'status' => 'waiting',
                'priority' => 'normal',
            ]);

            // تلاش برای اختصاص خودکار به پشتیبان
            $this->assignmentService->assignToAvailableAgent($chat);
        }

        $chat->load(['messages.user', 'agent']);

        return view('user.support-chat.index', compact('chat'));
    }

    /**
     * ارسال پیام در چت
     */
    public function sendMessage(Request $request, SupportChat $chat)
    {
        // بررسی دسترسی
        if ($chat->user_id != auth()->id()) {
            return response()->json(['error' => 'دسترسی مجاز نیست'], 403);
        }

        $validated = $request->validate([
            'message' => 'required|string|min:1|max:5000',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,pdf,doc,docx,txt,zip,rar',
        ]);

        $attachments = [];
        
        // آپلود فایل‌های ضمیمه
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('support-chat/attachments', 'public');
                $attachments[] = [
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ];
            }
        }

        // ایجاد پیام
        $message = SupportChatMessage::create([
            'support_chat_id' => $chat->id,
            'user_id' => auth()->id(),
            'type' => 'user',
            'message' => $validated['message'],
            'attachments' => !empty($attachments) ? $attachments : null,
        ]);

        // به‌روزرسانی چت
        $chat->update([
            'status' => $chat->status === 'waiting' ? 'active' : $chat->status,
            'last_activity_at' => now(),
            'subject' => $chat->subject ?? Str::limit($validated['message'], 50),
        ]);

        // اگر چت بدون پشتیبان است، تلاش برای اختصاص
        if (!$chat->agent_id && $chat->status === 'waiting') {
            $this->assignmentService->assignToAvailableAgent($chat);
            $chat->refresh();
        }

        $message->load('user');
        $chat->refresh();

        // Broadcast event برای real-time
        event(new SupportChatMessageSent($message, $chat, auth()->user()));

        return response()->json([
            'success' => true,
            'message' => $message,
            'chat' => $chat->fresh(['agent']),
        ]);
    }

    /**
     * دریافت پیام‌های چت
     */
    public function getMessages(SupportChat $chat)
    {
        // بررسی دسترسی
        if ($chat->user_id != auth()->id()) {
            return response()->json(['error' => 'دسترسی مجاز نیست'], 403);
        }

        $messages = $chat->messages()->with('user')->get();

        // علامت‌گذاری پیام‌ها به عنوان خوانده شده
        $chat->markAsRead();

        return response()->json([
            'success' => true,
            'messages' => $messages,
            'chat' => $chat->fresh(['agent']),
        ]);
    }

    /**
     * تبدیل چت به تیکت
     */
    public function convertToTicket(Request $request, SupportChat $chat)
    {
        // بررسی دسترسی
        if ($chat->user_id != auth()->id()) {
            return response()->json(['error' => 'دسترسی مجاز نیست'], 403);
        }

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'category' => 'nullable|string',
            'priority' => 'nullable|in:low,normal,high',
        ]);

        $messages = $chat->messages()->orderBy('created_at', 'asc')->get();
        $transcript = '';
        
        foreach ($messages as $msg) {
            $sender = $msg->type === 'user' ? 'کاربر' : ($msg->user ? $msg->user->fullName() : 'پشتیبان');
            $transcript .= "**{$sender}** ({$msg->created_at->format('Y/m/d H:i')}):\n{$msg->message}\n\n";
        }

        // ایجاد تیکت
        $ticketService = app(\App\Services\NajmHodaIntegrationService::class);
        $ticket = $ticketService->handleEscalation([
            'conversation_id' => 'chat-' . $chat->id,
            'transcript' => $transcript,
            'user_email' => auth()->user()->email,
            'user_id' => auth()->id(),
            'reason' => $validated['subject'],
        ]);

        // به‌روزرسانی تیکت با اطلاعات اضافی
        $ticket->update([
            'category' => $validated['category'] ?? 'general',
            'priority' => $validated['priority'] ?? $chat->priority,
        ]);

        // اتصال چت به تیکت
        $chat->update([
            'ticket_id' => $ticket->id,
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'چت با موفقیت به تیکت تبدیل شد',
            'ticket_id' => $ticket->id,
            'tracking_code' => $ticket->tracking_code,
        ]);
    }

    /**
     * بستن چت
     */
    public function close(SupportChat $chat)
    {
        // بررسی دسترسی
        if ($chat->user_id != auth()->id()) {
            return response()->json(['error' => 'دسترسی مجاز نیست'], 403);
        }

        $chat->update([
            'status' => 'closed',
            'resolved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'چت با موفقیت بسته شد',
        ]);
    }
}
