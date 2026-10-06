<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Throwable;

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
            '_contact_started_at' => 'required|string|max:2048',
        ]);

        // Honeypot and minimum form-age checks fail silently so bots do not
        // receive a useful signal about which anti-abuse check rejected them.
        if (
            filled($data['company_website'] ?? null)
            || ! $this->hasHumanFormAge((string) $data['_contact_started_at'])
        ) {
            return back()->with('success', 'پیام شما دریافت شد.');
        }

        $user = $request->user();

        ContactMessage::query()->create([
            'user_id' => $user?->id,
            'name' => filled($data['name'] ?? null) ? $data['name'] : ($user ? $user->fullName() : null),
            'email' => filled($data['email'] ?? null) ? $data['email'] : ($user?->email),
            'phone' => filled($data['phone'] ?? null) ? $data['phone'] : ($user?->phone),
            'subject' => $data['subject'],
            'message' => $data['message'],
            'status' => 'new',
            'source' => 'web_contact',
        ]);

        return back()->with('success', 'پیام شما دریافت شد و از طریق صندوق تماس بررسی خواهد شد.');
    }

    private function hasHumanFormAge(string $token): bool
    {
        try {
            $startedAt = (int) Crypt::decryptString($token);
        } catch (Throwable) {
            return false;
        }

        $age = now()->timestamp - $startedAt;

        return $age >= 2 && $age <= 7200;
    }

}
