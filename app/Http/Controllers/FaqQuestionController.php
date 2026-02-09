<?php

namespace App\Http\Controllers;

use App\Models\FaqQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FaqQuestionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'question' => ['required', 'string'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_email' => ['nullable', 'email', 'max:255'],
        ]);

        $faqQuestion = new FaqQuestion($validated);
        if ($request->user()) {
            $faqQuestion->user_id = $request->user()->id;
            $fullName = trim(($request->user()->first_name ?? '') . ' ' . ($request->user()->last_name ?? ''));
            $faqQuestion->contact_name = $faqQuestion->contact_name ?? ($fullName !== '' ? $fullName : $request->user()->name ?? null);
            $faqQuestion->contact_email = $faqQuestion->contact_email ?? $request->user()->email;
        }

        $faqQuestion->status = 'new';
        $faqQuestion->save();

        return back()->with('success', 'سوال شما با موفقیت ثبت شد. تیم ما به زودی پاسخ می‌دهد.');
    }
}

