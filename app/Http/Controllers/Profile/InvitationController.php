<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use Illuminate\Http\Request;
use Str;

class InvitationController extends Controller
{
    public function get()
    {
        return view('invitation.index');
    }

    public function store(Request $request)
    {
        // Honeypot: اگر فیلد مخفی پر شده باشد، درخواست را نادیده می‌گیریم
        if ($request->filled('website')) {
            return redirect()->route('welcome')->with('success', __('auth_pages.invitation_request_received'));
        }

        $inputs = $request->validate([
            'email' => 'required|email|unique:invitations,email|regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
            'message' => 'nullable|string|max:500',
            'job' => 'nullable|string|max:500',
        ], [
            'email.required' => __('auth_pages.validation_email_required'),
            'email.email' => __('auth_pages.validation_email_invalid'),
            'email.unique' => __('auth_pages.validation_email_unique'),
            'email.regex' => __('auth_pages.validation_email_format'),
            'message.max' => __('auth_pages.validation_message_max'),
            'job.max' => __('auth_pages.validation_job_max'),
        ], [
            'email' => __('auth_pages.field_email'),
            'message' => __('auth_pages.invitation_referral_label'),
            'job' => __('auth_pages.invitation_profession_label'),
        ]);

        $inputs['code'] = Str::random(5);
        // 0 = pending, 1 = issued, 2 = rejected (ستون status در DB عددی است)
        $inputs['status'] = 0;

        Invitation::create($inputs);

        return redirect()->route('welcome')->with('success', __('auth_pages.invitation_request_success'));
    }
}
