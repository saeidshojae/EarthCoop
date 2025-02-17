<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class VerifyController extends Controller
{
    public function verify(Request $request)
    {
        $request->validate([
            'email_or_phone' => 'required|string|max:255',
            'terms' => 'accepted',
        ]);

        // ذخیره اطلاعات در سشن
        Session::put('email_or_phone', $request->email_or_phone);

        // ارسال کد تایید به ایمیل یا شماره تلفن (این بخش باید پیاده‌سازی شود)

        return redirect()->route('register.step1');
    }
}