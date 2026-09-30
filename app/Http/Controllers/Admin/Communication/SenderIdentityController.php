<?php

namespace App\Http\Controllers\Admin\Communication;

use App\Http\Controllers\Controller;
use App\Models\CommunicationSenderIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class SenderIdentityController extends Controller
{
    public function index(): View
    {
        $senders = CommunicationSenderIdentity::query()
            ->withCount(['templateVersions', 'rules', 'campaigns'])
            ->orderBy('key')
            ->paginate(25);

        return view('admin.communications.senders.index', compact('senders'));
    }

    public function create(): View
    {
        return view('admin.communications.senders.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateSender($request);
        CommunicationSenderIdentity::query()->create($validated);

        return redirect()->route('admin.communications.senders.index')
            ->with('success', 'هویت فرستنده ایجاد شد.');
    }

    public function edit(CommunicationSenderIdentity $sender): View
    {
        return view('admin.communications.senders.edit', compact('sender'));
    }

    public function update(Request $request, CommunicationSenderIdentity $sender): RedirectResponse
    {
        $validated = $this->validateSender($request, $sender);
        $sender->update($validated);

        return redirect()->route('admin.communications.senders.index')
            ->with('success', 'هویت فرستنده به‌روزرسانی شد.');
    }

    public function destroy(Request $request, CommunicationSenderIdentity $sender): RedirectResponse|JsonResponse
    {
        $inUse = $sender->templateVersions()->exists()
            || $sender->rules()->exists()
            || $sender->campaigns()->exists();

        if ($inUse) {
            $message = 'این هویت فرستنده در حال استفاده است و قابل حذف نیست.';
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'error' => $message], 409);
            }

            return back()->with('error', $message);
        }

        $sender->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('admin.communications.senders.index')
            ->with('success', 'هویت فرستنده حذف شد.');
    }

    /** @return array<string,mixed> */
    private function validateSender(Request $request, ?CommunicationSenderIdentity $sender = null): array
    {
        return $request->validate([
            'key' => ['required', 'string', 'max:120', Rule::unique('communication_sender_identities', 'key')->ignore($sender?->id)],
            'email' => ['required', 'email', 'max:255'],
            'display_name' => ['required', 'string', 'max:255'],
            'reply_to' => ['nullable', 'email', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'system_identity_key' => ['nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
    }
}
