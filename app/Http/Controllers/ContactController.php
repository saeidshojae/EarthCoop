<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:10000',
            'company_website' => 'nullable|string|max:255',
        ]);

        // Honeypot: accept the request generically but do not persist spam.
        if (filled($data['company_website'] ?? null)) {
            return back()->with('success', 'پیام شما دریافت شد.');
        }

        $user = $request->user();

        ContactMessage::query()->create([
            'user_id' => $user?->id,
            'name' => $data['name'] ?? ($user ? $user->fullName() : null),
            'email' => $data['email'] ?? ($user?->email),
            'phone' => $data['phone'] ?? ($user?->phone),
            'subject' => $data['subject'],
            'message' => $data['message'],
            'status' => 'new',
            'source' => 'web_contact',
        ]);

        return back()->with('success', 'پیام شما دریافت شد و از طریق صندوق تماس بررسی خواهد شد.');
    }
}
