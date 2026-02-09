<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Notifications\TicketCreatedNotification;
use Illuminate\Support\Facades\Notification;
use App\Services\TicketTriageService;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $user = $request->user();

        $tracking = 'TKT' . time() . strtoupper(Str::random(6));

        // Run triage to determine priority and assignee
        $triageService = app(TicketTriageService::class);
        $triage = $triageService->triage($data['subject'], $data['message']);

        $ticket = Ticket::create([
            'user_id' => $user ? $user->id : null,
            'name' => $data['name'] ?? ($user ? $user->fullName() : null),
            'email' => $data['email'] ?? ($user ? $user->email : null),
            'phone' => $data['phone'] ?? null,
            'subject' => $data['subject'],
            'message' => $data['message'],
            'status' => 'open',
            'priority' => $triage['priority'] ?? null,
            'assignee_id' => $triage['assignee_id'] ?? null,
            'tracking_code' => $tracking,
        ]);

        // Notify user if email provided (wrapped in try/catch to avoid breaking flow)
        try {
            if ($ticket->email) {
                Notification::route('mail', $ticket->email)->notify(new TicketCreatedNotification($ticket));
            }

            // Notify support team
            $supportEmail = env('SUPPORT_EMAIL', 'support@earthcoop.info');
            Notification::route('mail', $supportEmail)->notify(new TicketCreatedNotification($ticket));
        } catch (\Throwable $e) {
            // log the error but don't interrupt the user flow
            // \/\/ use logger if available
            try {
                \Log::error('ContactController notification failed: ' . $e->getMessage());
            } catch (\Throwable $_) {
                // ignore logging errors
            }
        }

        return back()->with('success', 'درخواست شما ثبت شد')->with('ticket_tracking', $tracking);
    }
}
