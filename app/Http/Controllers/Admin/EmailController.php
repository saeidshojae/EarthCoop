<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class EmailController extends Controller
{
    /**
     * Display a listing of email templates
     */
    public function index()
    {
        $templates = EmailTemplate::latest()->paginate(15);
        return view('admin.emails.index', compact('templates'));
    }

    /**
     * Show the form for creating a new email template
     */
    public function create()
    {
        return view('admin.emails.create');
    }

    /**
     * Store a newly created email template
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'category' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        EmailTemplate::create($validated);

        return redirect()->route('admin.emails.index')
            ->with('success', 'قالب ایمیل با موفقیت ایجاد شد.');
    }

    /**
     * Show the form for editing an email template
     */
    public function edit(EmailTemplate $email)
    {
        return view('admin.emails.edit', compact('email'));
    }

    /**
     * Update the specified email template
     */
    public function update(Request $request, EmailTemplate $email)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'category' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $email->update($validated);

        return redirect()->route('admin.emails.index')
            ->with('success', 'قالب ایمیل با موفقیت به‌روزرسانی شد.');
    }

    /**
     * Remove the specified email template
     */
    public function destroy(EmailTemplate $email)
    {
        $email->delete();

        return redirect()->route('admin.emails.index')
            ->with('success', 'قالب ایمیل با موفقیت حذف شد.');
    }

    /**
     * Show the form for sending an email
     */
    public function showSendForm()
    {
        $templates = EmailTemplate::where('is_active', true)->get();
        $users = User::select('id', 'first_name', 'last_name', 'email')->get();
        
        return view('admin.emails.send', compact('templates', 'users'));
    }

    /**
     * Send email using a template
     */
    public function sendTemplate(Request $request)
    {
        $validated = $request->validate([
            'template_id' => 'required|exists:email_templates,id',
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'required|string',
            'variables' => 'nullable|array',
        ]);

        $template = EmailTemplate::findOrFail($validated['template_id']);
        
        if (!$template->is_active) {
            return back()->withErrors(['template_id' => 'این قالب غیرفعال است.']);
        }

        $rendered = $template->render($validated['variables'] ?? []);

        // Parse recipients (support both comma-separated and newline-separated)
        $recipientsList = [];
        foreach ($validated['recipients'] as $recipient) {
            // Split by comma or newline
            $emails = preg_split('/[,\n]/', $recipient);
            foreach ($emails as $email) {
                $email = trim($email);
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $recipientsList[] = $email;
                }
            }
        }

        if (empty($recipientsList)) {
            return back()->withErrors(['recipients' => 'لطفاً حداقل یک ایمیل معتبر وارد کنید.']);
        }

        $recipientsList = array_unique($recipientsList);

        foreach ($recipientsList as $recipient) {
            try {
                Mail::raw($rendered['body'], function ($message) use ($recipient, $rendered) {
                    $message->to($recipient)
                        ->subject($rendered['subject'])
                        ->html($rendered['body']);
                });
            } catch (\Exception $e) {
                Log::error('Failed to send email', [
                    'recipient' => $recipient,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return redirect()->route('admin.emails.send')
            ->with('success', 'ایمیل‌ها با موفقیت ارسال شدند.');
    }

    /**
     * Send a custom email (without template)
     */
    public function sendCustom(Request $request)
    {
        $validated = $request->validate([
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'required|string',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        // Parse recipients (support both comma-separated and newline-separated)
        $recipientsList = [];
        foreach ($validated['recipients'] as $recipient) {
            // Split by comma or newline
            $emails = preg_split('/[,\n]/', $recipient);
            foreach ($emails as $email) {
                $email = trim($email);
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $recipientsList[] = $email;
                }
            }
        }

        if (empty($recipientsList)) {
            return back()->withErrors(['recipients' => 'لطفاً حداقل یک ایمیل معتبر وارد کنید.']);
        }

        $recipientsList = array_unique($recipientsList);

        foreach ($recipientsList as $recipient) {
            try {
                Mail::raw($validated['body'], function ($message) use ($recipient, $validated) {
                    $message->to($recipient)
                        ->subject($validated['subject'])
                        ->html($validated['body']);
                });
            } catch (\Exception $e) {
                Log::error('Failed to send custom email', [
                    'recipient' => $recipient,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return redirect()->route('admin.emails.send')
            ->with('success', 'ایمیل‌های سفارشی با موفقیت ارسال شدند.');
    }

    /**
     * Preview email template
     */
    public function preview(Request $request, EmailTemplate $email)
    {
        $variables = $request->get('variables', []);
        $rendered = $email->render($variables);

        return response()->json([
            'subject' => $rendered['subject'],
            'body' => $rendered['body'],
        ]);
    }
}

