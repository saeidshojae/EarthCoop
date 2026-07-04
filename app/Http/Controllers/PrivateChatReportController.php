<?php

namespace App\Http\Controllers;

use App\Models\PrivateChatReport;
use App\Models\PrivateMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PrivateChatReportController extends Controller
{
    /**
     * Report a message in private chat
     */
    public function store(Request $request)
    {
        $request->validate([
            'message_id' => 'required|exists:private_messages,id',
            'reason' => 'required|in:spam,harassment,inappropriate_content,abuse,other',
            'description' => 'nullable|string|max:1000',
        ]);

        $user = Auth::user();
        $messageId = $request->message_id;
        $reason = $request->reason;

        // Get message and verify user is in conversation
        $message = PrivateMessage::with('conversation.users')
            ->findOrFail($messageId);

        $inConversation = $message->conversation->users->contains($user->id);

        if (!$inConversation) {
            return response()->json([
                'success' => false,
                'error' => 'دسترسی ندارید',
            ], 403);
        }

        // Prevent reporting own messages
        if ($message->sender_id === $user->id) {
            return response()->json([
                'success' => false,
                'error' => 'نمی‌توانید از پیام خود گزارش دهید',
            ], 422);
        }

        // Check for duplicate recent report (same user, same message, pending status)
        $existingReport = PrivateChatReport::where('private_conversation_id', $message->private_conversation_id)
            ->where('reported_message_id', $messageId)
            ->where('reporter_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if ($existingReport) {
            return response()->json([
                'success' => false,
                'error' => 'شما قبلاً یک گزارش برای این پیام ثبت کرده‌اید',
            ], 422);
        }

        // Create report
        $report = PrivateChatReport::create([
            'private_conversation_id' => $message->private_conversation_id,
            'reported_message_id' => $messageId,
            'reporter_id' => $user->id,
            'reported_user_id' => $message->sender_id,
            'reason' => $reason,
            'description' => $request->description,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'گزارش شما با موفقیت ثبت شد و توسط مدیریت بررسی خواهد شد',
            'report_id' => $report->id,
        ]);
    }

    /**
     * Get reports for admin panel
     */
    public function index(Request $request)
    {
        $this->ensureAdmin();

        $stats = $this->getStats();
        $filters = [
            'status' => $request->get('status', ''),
            'reason' => $request->get('reason', ''),
            'q' => $request->get('q', ''),
        ];

        $reports = $this->buildIndexQuery($request)
            ->paginate(20)
            ->withQueryString();

        return view('admin.private-chat-reports.index', compact('reports', 'stats', 'filters'));
    }

    /**
     * Show a specific report
     */
    public function show($id)
    {
        $this->ensureAdmin();

        $report = PrivateChatReport::with([
            'reporter',
            'reportedUser',
            'message.sender',
            'conversation.users',
            'conversation.messages.sender',
            'reviewer',
        ])->findOrFail($id);

        return view('admin.private-chat-reports.show', compact('report'));
    }

    /**
     * Admin review/update report
     */
    public function review(Request $request, $id)
    {
        $this->ensureAdmin();

        $request->validate([
            'status' => 'required|in:pending,reviewed,resolved,dismissed',
            'admin_notes' => 'nullable|string|max:2000',
        ]);

        $report = PrivateChatReport::findOrFail($id);
        $report->status = $request->status;
        $report->admin_notes = $request->admin_notes;

        if ($request->status === 'pending') {
            $report->reviewed_by = null;
            $report->reviewed_at = null;
        } else {
            $report->reviewed_by = Auth::id();
            $report->reviewed_at = now();
        }

        $report->save();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'گزارش با موفقیت بررسی شد',
                'report' => $report,
            ]);
        }

        return back()->with('success', 'گزارش با موفقیت بررسی شد');
    }

    /**
     * Delete a report
     */
    public function destroy($id)
    {
        $this->ensureAdmin();

        $report = PrivateChatReport::findOrFail($id);
        $report->delete();

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'گزارش حذف شد',
            ]);
        }

        return redirect()->route('admin.private-chat-reports')->with('success', 'گزارش حذف شد');
    }

    protected function ensureAdmin(): void
    {
        abort_unless(Auth::check() && Auth::user()->is_admin, 403);
    }

    protected function buildIndexQuery(Request $request)
    {
        $query = PrivateChatReport::query()->with([
            'reporter',
            'reportedUser',
            'message.sender',
            'conversation.users',
            'reviewer',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('reason')) {
            $query->where('reason', $request->reason);
        }

        if ($request->filled('q')) {
            $search = trim($request->q);

            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('description', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhereHas('reporter', function ($reporterQuery) use ($search) {
                        $reporterQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('reportedUser', function ($reportedUserQuery) use ($search) {
                        $reportedUserQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('message', function ($messageQuery) use ($search) {
                        $messageQuery->where('message', 'like', "%{$search}%");
                    });
            });
        }

        return $query->latest();
    }

    protected function getStats(): array
    {
        return [
            'total' => PrivateChatReport::count(),
            'pending' => PrivateChatReport::where('status', 'pending')->count(),
            'reviewed' => PrivateChatReport::where('status', 'reviewed')->count(),
            'resolved' => PrivateChatReport::where('status', 'resolved')->count(),
            'dismissed' => PrivateChatReport::where('status', 'dismissed')->count(),
        ];
    }
}