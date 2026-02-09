<?php

namespace App\Http\Controllers\Admin;

use App\Events\SupportChatMessageSent;
use App\Http\Controllers\Controller;
use App\Models\SupportChat;
use App\Models\SupportChatMessage;
use App\Services\SupportChatAssignmentService;
use App\Services\NajmHodaIntegrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SupportChatController extends Controller
{
    protected SupportChatAssignmentService $assignmentService;

    public function __construct(SupportChatAssignmentService $assignmentService)
    {
        $this->middleware('auth');
        $this->middleware('admin');
        $this->assignmentService = $assignmentService;
    }

    /**
     * لیست چت‌های پشتیبانی
     */
    public function index(Request $request)
    {
        $query = SupportChat::with(['user', 'agent', 'ticket']);

        // فیلترها
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->agent_id);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('q')) {
            $term = $request->q;
            $query->where(function($query) use ($term) {
                $query->where('subject', 'like', "%{$term}%")
                      ->orWhereHas('user', function($userQuery) use ($term) {
                          $userQuery->where('name', 'like', "%{$term}%")
                                    ->orWhere('email', 'like', "%{$term}%");
                      });
            });
        }

        $chats = $query->latest()->paginate(20);

        // آمار
        $stats = [
            'waiting' => SupportChat::where('status', 'waiting')->count(),
            'active' => SupportChat::where('status', 'active')->count(),
            'resolved' => SupportChat::where('status', 'resolved')->count(),
            'closed' => SupportChat::where('status', 'closed')->count(),
            'my_active' => SupportChat::where('agent_id', auth()->id())
                ->where('status', 'active')
                ->count(),
        ];

        // لیست پشتیبان‌ها
        $agents = \App\Models\User::where('is_admin', 1)
            ->orWhereHas('roles', function($query) {
                $query->where('name', 'support_agent');
            })
            ->get();

        return view('admin.support-chat.index', compact('chats', 'stats', 'agents'));
    }

    /**
     * نمایش یک چت
     */
    public function show(SupportChat $chat)
    {
        $chat->load(['messages.user', 'user', 'agent', 'ticket']);

        // علامت‌گذاری پیام‌ها به عنوان خوانده شده
        if ($chat->agent_id == auth()->id()) {
            $chat->markAsRead();
        }

        // لیست پشتیبان‌ها برای dropdown
        $agents = \App\Models\User::where('is_admin', 1)
            ->orWhereHas('roles', function($query) {
                $query->where('name', 'support_agent');
            })
            ->get();

        return view('admin.support-chat.show', compact('chat', 'agents'));
    }

    /**
     * ارسال پیام توسط پشتیبان
     */
    public function sendMessage(Request $request, SupportChat $chat)
    {
        // بررسی دسترسی
        if ($chat->agent_id != auth()->id() && !auth()->user()->is_admin) {
            return response()->json(['error' => 'دسترسی مجاز نیست'], 403);
        }

        $validated = $request->validate([
            'message' => 'required|string|min:1|max:5000',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,pdf,doc,docx,txt,zip,rar',
        ]);

        // اگر چت هنوز پشتیبان ندارد، اختصاص بده
        if (!$chat->agent_id) {
            $chat->update([
                'agent_id' => auth()->id(),
                'status' => 'active',
            ]);
        }

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
            'type' => 'agent',
            'message' => $validated['message'],
            'attachments' => !empty($attachments) ? $attachments : null,
            'read_at' => now(), // پیام پشتیبان خودش خوانده محسوب می‌شود
        ]);

        // به‌روزرسانی چت
        $chat->update([
            'status' => 'active',
            'last_activity_at' => now(),
        ]);

        $message->load('user');
        $chat->refresh();

        // Broadcast event برای real-time
        event(new SupportChatMessageSent($message, $chat, auth()->user()));

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    /**
     * اختصاص چت به پشتیبان
     */
    public function assign(Request $request, SupportChat $chat)
    {
        $validated = $request->validate([
            'agent_id' => 'required|exists:users,id',
        ]);

        $agent = \App\Models\User::findOrFail($validated['agent_id']);

        if (!$this->assignmentService->isSupportAgent($agent)) {
            return back()->with('error', 'کاربر انتخاب شده پشتیبان نیست');
        }

        $this->assignmentService->assignToAgent($chat, $agent);

        return back()->with('success', 'چت با موفقیت به پشتیبان اختصاص یافت');
    }

    /**
     * تبدیل چت به تیکت
     */
    public function convertToTicket(Request $request, SupportChat $chat)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'category' => 'nullable|string',
            'priority' => 'nullable|in:low,normal,high',
        ]);

        $messages = $chat->messages()->orderBy('created_at', 'asc')->get();
        $transcript = '';
        
        foreach ($messages as $msg) {
            $sender = $msg->type === 'user' ? ($msg->user ? $msg->user->fullName() : 'کاربر') : ($msg->user ? $msg->user->fullName() : 'پشتیبان');
            $transcript .= "**{$sender}** ({$msg->created_at->format('Y/m/d H:i')}):\n{$msg->message}\n\n";
        }

        // ایجاد تیکت
        $ticketService = app(NajmHodaIntegrationService::class);
        $ticket = $ticketService->handleEscalation([
            'conversation_id' => 'chat-' . $chat->id,
            'transcript' => $transcript,
            'user_email' => $chat->user->email,
            'user_id' => $chat->user_id,
            'reason' => $validated['subject'],
        ]);

        // به‌روزرسانی تیکت با اطلاعات اضافی
        $ticket->update([
            'category' => $validated['category'] ?? 'general',
            'priority' => $validated['priority'] ?? $chat->priority,
            'assignee_id' => $chat->agent_id,
        ]);

        // اتصال چت به تیکت
        $chat->update([
            'ticket_id' => $ticket->id,
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        return back()->with('success', "چت با موفقیت به تیکت تبدیل شد. کد پیگیری: {$ticket->tracking_code}");
    }

    /**
     * بستن چت
     */
    public function close(SupportChat $chat)
    {
        $chat->update([
            'status' => 'closed',
            'resolved_at' => now(),
        ]);

        return back()->with('success', 'چت با موفقیت بسته شد');
    }

    /**
     * توزیع خودکار چت‌های در انتظار
     */
    public function autoAssign()
    {
        $assigned = $this->assignmentService->autoAssignWaitingChats();

        return back()->with('success', "{$assigned} چت با موفقیت اختصاص یافت");
    }
}
