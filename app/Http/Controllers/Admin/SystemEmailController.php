<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemEmail;
use Illuminate\Http\Request;

class SystemEmailController extends Controller
{
    /**
     * Display a listing of system emails
     */
    public function index()
    {
        $emails = SystemEmail::latest()->paginate(15);
        return view('admin.emails.system-emails.index', compact('emails'));
    }

    /**
     * Show the form for creating a new system email
     */
    public function create()
    {
        return view('admin.emails.system-emails.create');
    }

    /**
     * Store a newly created system email
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:system_emails,name',
            'email' => 'required|email|unique:system_emails,email',
            'display_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        $email = SystemEmail::create($validated);

        // If this is set as default, unset others
        if ($validated['is_default'] ?? false) {
            $email->setAsDefault();
        }

        return redirect()->route('admin.system-emails.index')
            ->with('success', 'ایمیل سیستم با موفقیت ایجاد شد.');
    }

    /**
     * Show the form for editing a system email
     */
    public function edit(SystemEmail $systemEmail)
    {
        return view('admin.emails.system-emails.edit', compact('systemEmail'));
    }

    /**
     * Update the specified system email
     */
    public function update(Request $request, SystemEmail $systemEmail)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:system_emails,name,' . $systemEmail->id,
            'email' => 'required|email|unique:system_emails,email,' . $systemEmail->id,
            'display_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        $systemEmail->update($validated);

        // If this is set as default, unset others
        if ($validated['is_default'] ?? false) {
            $systemEmail->setAsDefault();
        }

        return redirect()->route('admin.system-emails.index')
            ->with('success', 'ایمیل سیستم با موفقیت به‌روزرسانی شد.');
    }

    /**
     * Remove the specified system email
     */
    public function destroy(SystemEmail $systemEmail)
    {
        // Prevent deletion of default email
        if ($systemEmail->is_default) {
            return back()->withErrors(['error' => 'نمی‌توانید ایمیل پیش‌فرض را حذف کنید. ابتدا یک ایمیل دیگر را به عنوان پیش‌فرض تنظیم کنید.']);
        }

        $systemEmail->delete();

        return redirect()->route('admin.system-emails.index')
            ->with('success', 'ایمیل سیستم با موفقیت حذف شد.');
    }

    /**
     * Set as default email
     */
    public function setDefault(SystemEmail $systemEmail)
    {
        $systemEmail->setAsDefault();

        return redirect()->route('admin.system-emails.index')
            ->with('success', 'ایمیل به عنوان پیش‌فرض تنظیم شد.');
    }
}

