<?php

namespace App\Http\Controllers\Admin\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Http\Controllers\Controller;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationSenderIdentity;
use App\Models\CommunicationTemplate;
use App\Services\Communication\CommunicationAudienceRegistry;
use App\Services\Communication\CommunicationCampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

final class CampaignController extends Controller
{
    public function index(): View
    {
        $campaigns = CommunicationCampaign::query()
            ->with(['template', 'senderIdentity'])
            ->latest('id')
            ->paginate(25);

        return view('admin.communications.campaigns.index', compact('campaigns'));
    }

    public function create(CommunicationAudienceRegistry $audiences): View
    {
        $templates = CommunicationTemplate::query()->where('is_active', true)->orderBy('key')->get();
        $senders = CommunicationSenderIdentity::query()->where('is_active', true)->orderBy('key')->get();
        $audienceKeys = array_values(array_filter(
            ['specific.user', 'role.member', 'role.manager', 'role.inspector'],
            static fn (string $key): bool => $audiences->has($key),
        ));

        return view('admin.communications.campaigns.create', compact('templates', 'senders', 'audienceKeys'));
    }

    public function store(Request $request, CommunicationAudienceRegistry $audiences): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'audience_key' => ['required', 'string', 'max:160'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'communication_template_id' => ['required', 'integer', 'exists:communication_templates,id'],
            'communication_sender_identity_id' => ['nullable', 'integer', 'exists:communication_sender_identities,id'],
            'classification' => ['required', Rule::in(array_column(CommunicationClassification::cases(), 'value'))],
            'priority' => ['required', 'integer', 'min:1', 'max:9'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        $audienceKey = (string) $validated['audience_key'];
        if (! $audiences->has($audienceKey) || $audienceKey === 'event.user') {
            return back()->withInput()->withErrors([
                'audience_key' => 'این مخاطب برای کمپین قابل اجرا نیست.',
            ]);
        }

        $userIds = array_values(array_unique(array_map('intval', (array) ($validated['user_ids'] ?? []))));
        if ($audienceKey === 'specific.user' && $userIds === []) {
            return back()->withInput()->withErrors([
                'user_ids' => 'برای مخاطب مشخص، حداقل یک کاربر لازم است.',
            ]);
        }

        CommunicationCampaign::query()->create([
            'name' => $validated['name'],
            'status' => 'draft',
            'audience_definition' => $audienceKey === 'specific.user'
                ? ['key' => $audienceKey, 'user_ids' => $userIds]
                : ['key' => $audienceKey],
            'communication_template_id' => $validated['communication_template_id'],
            'communication_sender_identity_id' => $validated['communication_sender_identity_id'] ?? null,
            'classification' => $validated['classification'],
            'priority' => $validated['priority'],
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        return redirect()->route('admin.communications.campaigns.index')
            ->with('success', 'کمپین به‌صورت پیش‌نویس ایجاد شد.');
    }

    public function preview(
        CommunicationCampaign $campaign,
        CommunicationCampaignService $service,
    ): View {
        $counts = $service->preview($campaign);

        return view('admin.communications.campaigns.preview', compact('campaign', 'counts'));
    }

    public function confirm(
        Request $request,
        CommunicationCampaign $campaign,
        CommunicationCampaignService $service,
    ): RedirectResponse {
        $validated = $request->validate([
            'elevated_confirmed' => ['sometimes', 'boolean'],
        ]);

        try {
            $service->confirm(
                $campaign,
                $request->user(),
                (bool) ($validated['elevated_confirmed'] ?? false),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['campaign' => $exception->getMessage()]);
        }

        return redirect()->route('admin.communications.campaigns.index')
            ->with('success', 'کمپین تایید شد.');
    }

    public function pause(
        CommunicationCampaign $campaign,
        CommunicationCampaignService $service,
    ): RedirectResponse {
        $service->pause($campaign);

        return redirect()->route('admin.communications.campaigns.index')
            ->with('success', 'کمپین متوقف شد.');
    }

    public function cancel(
        CommunicationCampaign $campaign,
        CommunicationCampaignService $service,
    ): RedirectResponse {
        $service->cancel($campaign);

        return redirect()->route('admin.communications.campaigns.index')
            ->with('success', 'بخش ارسال‌نشده کمپین لغو شد؛ پیام‌های ارسال‌شده قابل بازگردانی نیستند.');
    }
}
