<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\Communication\CommunicationDispatcher;
use App\Services\Communication\LegacyEmailCommunicationImporter;
use App\Services\Email\EmailTemplateManagementService;
use Illuminate\Http\Request;

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
    public function update(
        Request $request,
        EmailTemplate $email,
        EmailTemplateManagementService $templates
    ) {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'category' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $templates->update($email, $validated);

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
     * Queue email using a legacy template through the canonical communication engine.
     */
    public function sendTemplate(
        Request $request,
        CommunicationDispatcher $communications,
        LegacyEmailCommunicationImporter $legacyImporter,
    ) {
        $validated = $request->validate([
            'template_id' => 'required|exists:email_templates,id',
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'required|string',
            'variables' => 'nullable|array',
        ]);

        $template = EmailTemplate::findOrFail($validated['template_id']);
        if (! $template->is_active) {
            return back()->withErrors(['template_id' => 'این قالب غیرفعال است.']);
        }

        $recipients = $this->parseRecipients($validated['recipients']);
        if ($recipients === []) {
            return back()->withErrors(['recipients' => 'لطفاً حداقل یک ایمیل معتبر وارد کنید.']);
        }

        // Non-destructively mirror legacy sender/template records before dispatch.
        $legacyImporter->import();

        $communications->dispatchExternal(
            'legacy.email-template.'.(int) $template->id,
            ['type' => 'admin.manual_template', 'id' => (string) $template->id],
            array_map(
                static fn (string $email): array => ['email' => $email, 'locale' => 'fa'],
                $recipients,
            ),
            (array) ($validated['variables'] ?? []),
            ['priority' => 2],
        );

        return redirect()->route('admin.emails.send')
            ->with('success', count($recipients).' ایمیل برای ارسال در صف قرار گرفت.');
    }

    /**
     * Queue a custom email through the canonical communication engine.
     */
    public function sendCustom(Request $request, CommunicationDispatcher $communications)
    {
        $validated = $request->validate([
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'required|string',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        $recipients = $this->parseRecipients($validated['recipients']);
        if ($recipients === []) {
            return back()->withErrors(['recipients' => 'لطفاً حداقل یک ایمیل معتبر وارد کنید.']);
        }

        $communications->dispatchExternal(
            'admin.manual_custom',
            ['type' => 'admin.manual_custom', 'id' => (string) ($request->user()?->id ?? 'admin')],
            array_map(
                static fn (string $email): array => ['email' => $email, 'locale' => 'fa'],
                $recipients,
            ),
            [
                'subject' => (string) $validated['subject'],
                'rendered_html' => (string) $validated['body'],
            ],
            ['priority' => 2],
        );

        return redirect()->route('admin.emails.send')
            ->with('success', count($recipients).' ایمیل سفارشی برای ارسال در صف قرار گرفت.');
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

    /** @param array<int,string> $raw @return array<int,string> */
    private function parseRecipients(array $raw): array
    {
        $emails = [];
        foreach ($raw as $value) {
            foreach (preg_split('/[,\n]/', (string) $value) ?: [] as $email) {
                $email = trim($email);
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $emails[] = $email;
                }
            }
        }

        return array_values(array_unique($emails));
    }
}
