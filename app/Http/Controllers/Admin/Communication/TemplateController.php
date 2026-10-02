<?php

namespace App\Http\Controllers\Admin\Communication;

use App\Http\Controllers\Controller;
use App\Models\CommunicationSenderIdentity;
use App\Models\CommunicationTemplate;
use App\Models\CommunicationTemplateVersion;
use App\Services\Communication\CommunicationTemplateRenderer;
use App\Services\Communication\CommunicationTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

final class TemplateController extends Controller
{
    public function index(): View
    {
        $templates = CommunicationTemplate::query()
            ->with(['versions' => fn ($query) => $query->orderByDesc('version')])
            ->orderBy('key')
            ->paginate(25);

        return view('admin.communications.templates.index', compact('templates'));
    }

    public function show(CommunicationTemplate $template): View
    {
        $template->load([
            'versions' => fn ($query) => $query->with('senderIdentity')->orderByDesc('version'),
        ]);
        $senders = CommunicationSenderIdentity::query()->where('is_active', true)->orderBy('key')->get();

        return view('admin.communications.templates.show', compact('template', 'senders'));
    }

    public function publish(
        Request $request,
        CommunicationTemplate $template,
        CommunicationTemplateService $templates,
    ): RedirectResponse|JsonResponse {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'max:16'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'variables_schema' => ['required'],
            'communication_sender_identity_id' => ['nullable', 'integer', 'exists:communication_sender_identities,id'],
        ]);

        try {
            $schema = $this->parseSchema($validated['variables_schema']);
            $sender = $this->resolveSender($template, (string) $validated['locale'], $validated['communication_sender_identity_id'] ?? null);
            $actorId = $request->user()?->id;

            $templates->publish(
                $template,
                (string) $validated['locale'],
                (string) $validated['subject'],
                (string) $validated['body'],
                $schema,
                $sender,
                $actorId,
                $actorId,
            );
        } catch (InvalidArgumentException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'error' => $exception->getMessage()], 422);
            }

            return back()->withInput()->withErrors(['variables_schema' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.communications.templates.show', $template)
            ->with('success', 'نسخه جدید قالب منتشر شد.');
    }

    public function preview(
        Request $request,
        CommunicationTemplate $template,
        CommunicationTemplateRenderer $renderer,
    ): JsonResponse {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'max:16'],
            'context' => ['nullable', 'array'],
        ]);

        $version = CommunicationTemplateVersion::query()
            ->where('communication_template_id', $template->id)
            ->where('locale', $validated['locale'])
            ->whereNotNull('published_at')
            ->orderByDesc('version')
            ->first();

        if (! $version) {
            return response()->json(['ok' => false, 'error' => 'نسخه منتشرشده‌ای برای این زبان وجود ندارد.'], 404);
        }

        try {
            $rendered = $renderer->render($version, (array) ($validated['context'] ?? []));
        } catch (InvalidArgumentException $exception) {
            return response()->json(['ok' => false, 'error' => $exception->getMessage()], 422);
        }

        return response()->json(['ok' => true] + $rendered);
    }

    /** @return array<string,array<string,mixed>> */
    private function parseSchema(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('Schema متغیرها باید JSON معتبر باشد.');
        }

        $decoded = json_decode($value, true);
        if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Schema متغیرها باید JSON معتبر باشد.');
        }

        foreach ($decoded as $key => $definition) {
            if (! is_string($key) || ! is_array($definition)) {
                throw new InvalidArgumentException('ساختار Schema متغیرها معتبر نیست.');
            }
        }

        return $decoded;
    }

    private function resolveSender(CommunicationTemplate $template, string $locale, mixed $explicitId): ?CommunicationSenderIdentity
    {
        if ($explicitId !== null) {
            return CommunicationSenderIdentity::query()->findOrFail((int) $explicitId);
        }

        $latest = CommunicationTemplateVersion::query()
            ->where('communication_template_id', $template->id)
            ->where('locale', $locale)
            ->whereNotNull('published_at')
            ->orderByDesc('version')
            ->first();

        return $latest?->senderIdentity;
    }
}
