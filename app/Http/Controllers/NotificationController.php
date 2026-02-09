<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    // Show all notifications for the authenticated user
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = $user->notifications()->latest();
        
        // Filter by type if provided
        if ($request->has('type') && $request->type !== 'all') {
            $type = $request->type;
            // Handle category filters
            if ($type === 'group.comment') {
                $query->where(function($q) {
                    $q->whereJsonContains('data->type', 'group.comment.new')
                      ->orWhereJsonContains('data->type', 'group.comment.reply');
                });
            } elseif ($type === 'group.manager') {
                $query->where(function($q) {
                    $q->whereJsonContains('data->type', 'group.report.message')
                      ->orWhereJsonContains('data->type', 'group.chat.request');
                });
            } elseif ($type === 'group.election') {
                $query->where(function($q) {
                    $q->whereJsonContains('data->type', 'group.election.started')
                      ->orWhereJsonContains('data->type', 'group.election.finished')
                      ->orWhereJsonContains('data->type', 'group.election.elected')
                      ->orWhereJsonContains('data->type', 'group.election.accepted')
                      ->orWhereJsonContains('data->type', 'group.election.reminder');
                });
            } elseif ($type === 'chat') {
                $query->where(function($q) {
                    $q->whereJsonContains('data->type', 'chat.message')
                      ->orWhereJsonContains('data->type', 'chat.reply')
                      ->orWhereJsonContains('data->type', 'chat.mention');
                });
            } elseif ($type === 'auction') {
                $query->where(function($q) {
                    $q->whereJsonContains('data->type', 'auction.started')
                      ->orWhereJsonContains('data->type', 'auction.ended')
                      ->orWhereJsonContains('data->type', 'auction.bid')
                      ->orWhereJsonContains('data->type', 'auction.won')
                      ->orWhereJsonContains('data->type', 'auction.outbid')
                      ->orWhereJsonContains('data->type', 'auction.lost')
                      ->orWhereJsonContains('data->type', 'auction.cancelled')
                      ->orWhereJsonContains('data->type', 'auction.reminder');
                });
            } elseif ($type === 'wallet') {
                $query->where(function($q) {
                    $q->whereJsonContains('data->type', 'wallet.settled')
                      ->orWhereJsonContains('data->type', 'wallet.released')
                      ->orWhereJsonContains('data->type', 'wallet.held')
                      ->orWhereJsonContains('data->type', 'wallet.credited')
                      ->orWhereJsonContains('data->type', 'wallet.debited');
                });
            } elseif ($type === 'shares') {
                $query->where(function($q) {
                    $q->whereJsonContains('data->type', 'shares.received')
                      ->orWhereJsonContains('data->type', 'shares.gifted');
                });
            } elseif ($type === 'stock') {
                $query->whereJsonContains('data->type', 'stock.price_changed');
            } elseif ($type === 'najm-bahar') {
                $query->where(function($q) {
                    $q->whereJsonContains('data->type', 'najm-bahar.transaction')
                      ->orWhereJsonContains('data->type', 'najm-bahar.low-balance')
                      ->orWhereJsonContains('data->type', 'najm-bahar.large-transaction')
                      ->orWhereJsonContains('data->type', 'najm-bahar.scheduled-executed');
                });
            } else {
                $query->whereJsonContains('data->type', $type);
            }
        }
        
        // Filter by read status
        if ($request->has('status')) {
            if ($request->status === 'unread') {
                $query->whereNull('read_at');
            } elseif ($request->status === 'read') {
                $query->whereNotNull('read_at');
            }
        }
        
        $notifications = $query->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    // Return unread notifications count (JSON)
    public function unreadCount()
    {
        $count = Auth::user()->unreadNotifications()->count();
        return response()->json(['count' => $count]);
    }

    // Return latest notifications (JSON)
    public function latest()
    {
        $items = Auth::user()->notifications()->latest()->take(10)->get();
        return response()->json($items);
    }

    // Mark a single notification as read
    public function markAsRead(string $id)
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return back()->with('success', 'اعلان خوانده شد.');
    }

    // Mark all notifications as read
    public function markAllAsRead()
    {
        $user = Auth::user();
        $user->unreadNotifications->markAsRead();

        return back()->with('success', 'همه اعلان‌ها خوانده شد.');
    }

    // Delete a single notification
    public function destroy(string $id)
    {
        Auth::user()->notifications()->where('id', $id)->delete();
        return back()->with('success', 'اعلان حذف شد.');
    }

    // Delete all read notifications
    public function deleteAllRead()
    {
        Auth::user()->readNotifications()->delete();
        return back()->with('success', 'اعلان‌های خوانده شده حذف شد.');
    }
}
