<?php

namespace App\Http\Controllers;

use App\Models\PrivateChatReport;
use App\Models\PrivateMessage;
use App\Models\PrivateConversation;
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
        $existingReport = PrivateChatReport::where('private_conversation_id', $message->conversation_id)
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
            'private_conversation_id' => $message->conversation_id,
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
        $user = Auth::user();
        
        // Only admins can view
        if (!$user->is_admin) {
            return response()->json(['error' => 'دسترسی ندارید'], 403);
        }

        $query = PrivateChatReport::with(['reporter', 'reportedUser', 'message', 'reviewer']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by reason
        if ($request->has('reason')) {
            $query->where('reason', $request->reason);
        }

        $reports = $query->latest()->paginate(20);

        return response()->json($reports);
    }

    /**
     * Show a specific report
     */
    public function show($id)
    {
        $user = Auth::user();
        
        if (!$user->is_admin) {
            return response()->json(['error' => 'دسترسی ندارید'], 403);
        }

        $report = PrivateChatReport::with(['reporter', 'reportedUser', 'message', 'reviewer'])
            ->findOrFail($id);

        return response()->json($report);
    }

    /**
     * Admin review/update report
     */
    public function review(Request $request, $id)
    {
        $user = Auth::user();
        
        if (!$user->is_admin) {
            return response()->json(['error' => 'دسترسی ندارید'], 403);
        }

        $request->validate([
            'status' => 'required|in:reviewed,resolved,dismissed',
            'admin_notes' => 'nullable|string|max:2000',
        ]);

        $report = PrivateChatReport::findOrFail($id);
        $report->status = $request->status;
        $report->admin_notes = $request->admin_notes;
        $report->reviewed_by = $user->id;
        $report->reviewed_at = now();
        $report->save();

        // If resolved, optionally warn the reported user
        if ($request->status === 'resolved') {
            // TODO: Send warning notification to reported user
        }

        return response()->json([
            'success' => true,
            'message' => 'گزارش با موفقیت بررسی شد',
            'report' => $report,
        ]);
    }

    /**
     * Delete a report
     */
    public function destroy($id)
    {
        $user = Auth::user();
        
        if (!$user->is_admin) {
            return response()->json(['error' => 'دسترسی ندارید'], 403);
        }

        $report = PrivateChatReport::findOrFail($id);
        $report->delete();

        return response()->json([
            'success' => true,
            'message' => 'گزارش حذف شد',
        ]);
    }
}