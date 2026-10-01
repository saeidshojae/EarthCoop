<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Services\Communication\CommunicationDispatcher;
use App\Services\Communication\LegacyEmailCommunicationImporter;
use Illuminate\Http\Request;

class EmailController extends Controller
{
    /**
     * Legacy management pages are intentionally retired. Canonical templates
     * are the only admin-managed source of truth from this point forward.
     */
    public function index()
    {
        return $this->redirectToCanonicalTemplates();
    }

    public function create()
    {
        return $this->redirectToCanonicalTemplates();
    }

    public function store(Request $request)
    {
        return $this->redirectToCanonicalTemplates();
    }

    public function edit(EmailTemplate $email)
    {
        return $this->redirectToCanonicalTemplates();
    }

    public function update(Request $request, EmailTemplate $email)
    {
        return $this->redirectToCanonicalTemplates();
    }

    public function destroy(EmailTemplate $email)
    {
        return $this->redirectToCanonicalTemplates();
    }

    /**
     * The old manual-send page is replaced by canonical campaigns. The two
     * POST compatibility adapters below remain temporarily available so old
     * callers can still enqueue through Communication Center during migration.
     */
    public function showSendForm()
    {
        return redirect()
            ->route('admin.communications.campaigns.create')
            ->with('info', 'ارسال ایمیل از این پس از مرکز ارتباطات انجام می‌شود.');
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
     * Legacy preview remains read-only for compatibility with callers that
     * still refer to a legacy template id during the strangler migration.
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

    private function redirectToCanonicalTemplates()
    {
        return redirect()
            ->route('admin.communications.templates.index')
            ->with('info', 'مدیریت قالب‌های قدیمی بازنشسته شده است؛ نسخه‌های فعال را در مرکز ارتباطات مدیریت کنید.');
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
