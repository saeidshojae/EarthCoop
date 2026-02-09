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
            return redirect()->route('welcome')->with('success', 'درخواست شما دریافت شد و بزودی بررسی می‌شود.');
        }

        $inputs = $request->validate([
            'email' => 'required|email|unique:invitations,email|regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
            'message' => 'nullable|string|max:500',
            'job' => 'nullable|string|max:500',
        ]);

        $inputs['code'] = Str::random(5);
        // 0 = pending, 1 = issued, 2 = rejected (ستون status در DB عددی است)
        $inputs['status'] = 0;

        Invitation::create($inputs);

        return redirect()->route('welcome')->with('success', 'درخواست کد دعوت شما با موفقیت ثبت شد. ما درخواست شما را بررسی خواهیم کرد و در صورت تأیید، کد دعوت به ایمیل شما ارسال خواهد شد.');
    }
}
