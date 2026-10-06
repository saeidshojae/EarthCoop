<?php

namespace App\Http\Controllers\Admin\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Http\Controllers\Controller;
use App\Models\CommunicationRule;
use App\Models\CommunicationSenderIdentity;
use App\Models\CommunicationTemplate;
use App\Services\Communication\CommunicationAudienceRegistry;
use App\Services\Communication\CommunicationConditionRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class AutomationController extends Controller
{
    public function index(): View
    {
        $rules = CommunicationRule::query()
            ->with(['template', 'senderIdentity', 'schedule'])
            ->orderBy('key')
            ->paginate(25);

        return view('admin.communications.automations.index', compact('rules'));
    }

    public function create(): View
    {
        $templates = CommunicationTemplate::query()->where('is_active', true)->orderBy('key')->get();
        $senders = CommunicationSenderIdentity::query()->where('is_active', true)->orderBy('key')->get();
        $audienceKeys = ['event.user', 'specific.user', 'role.member', 'role.manager', 'role.inspector'];
        $conditionKeys = ['user.registration_complete', 'user.email_verified'];

        return view('admin.communications.automations.create', compact(
            'templates',
            'senders',
            'audienceKeys',
            'conditionKeys',
        ));
    }

    public function store(
        Request $request,
        CommunicationAudienceRegistry $audiences,
        CommunicationConditionRegistry $conditions,
    ): RedirectResponse|JsonResponse {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:160', 'unique:communication_rules,key'],
            'name' => ['required', 'string', 'max:255'],
            'trigger_type' => ['required', Rule::in(['event', 'scheduled', 'conditional'])],
            'event_key' => ['nullable', 'string', 'max:160'],
            'audience_key' => ['required', 'string', 'max:160'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'condition_key' => ['nullable', 'string', 'max:160'],
            'communication_template_id' => ['required', 'integer', 'exists:communication_templates,id'],
            'communication_sender_identity_id' => ['nullable', 'integer', 'exists:communication_sender_identities,id'],
            'classification' => ['required', Rule::in(array_column(CommunicationClassification::cases(), 'value'))],
            'priority' => ['required', 'integer', 'min:1', 'max:9'],
            'delay_seconds' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'frequency' => ['nullable', Rule::in(['hourly', 'daily', 'weekly'])],
            'schedule_definition' => ['nullable', 'array'],
            'schedule_definition.interval' => ['nullable', 'integer', 'min:1'],
            'timezone' => ['nullable', 'timezone'],
        ]);

        if (! $audiences->has((string) $validated['audience_key'])) {
            return $this->invalid($request, 'audience_key', 'مخاطب انتخاب‌شده در رجیستری ارتباطات ثبت نشده است.');
        }

        $triggerType = (string) $validated['trigger_type'];
        if ($triggerType === 'event' && trim((string) ($validated['event_key'] ?? '')) === '') {
            return $this->invalid($request, 'event_key', 'برای قاعده رویدادمحور، کلید رویداد الزامی است.');
        }

        if ($triggerType === 'conditional') {
            $conditionKey = trim((string) ($validated['condition_key'] ?? ''));
            if ($conditionKey === '' || ! $conditions->has($conditionKey)) {
                return $this->invalid($request, 'condition_key', 'شرط انتخاب‌شده در رجیستری ارتباطات ثبت نشده است.');
            }
        }

        if ($triggerType === 'scheduled') {
            if (! isset($validated['frequency'])) {
                return $this->invalid($request, 'frequency', 'تناوب زمان‌بندی الزامی است.');
            }
            if (! isset($validated['schedule_definition']['interval'])) {
                return $this->invalid($request, 'schedule_definition.interval', 'فاصله زمان‌بندی الزامی است.');
            }
        }

        $audienceKey = (string) $validated['audience_key'];
        $userIds = array_values(array_filter(
            array_unique(array_map('intval', (array) ($validated['user_ids'] ?? []))),
            static fn (int $id): bool => $id > 0,
        ));
        if ($audienceKey === 'specific.user' && $userIds === []) {
            return $this->invalid($request, 'user_ids', 'برای مخاطب مشخص، حداقل یک کاربر لازم است.');
        }

        $actorId = $request->user()?->id;

        DB::transaction(function () use ($validated, $triggerType, $actorId, $audienceKey, $userIds): void {
            $conditionDefinition = null;
            if ($triggerType === 'conditional') {
                $conditionDefinition = ['key' => (string) $validated['condition_key']];
            }

            $audienceDefinition = ['key' => $audienceKey];
            if ($audienceKey === 'specific.user') {
                $audienceDefinition['user_ids'] = $userIds;
            }

            $rule = CommunicationRule::query()->create([
                'key' => $validated['key'],
                'name' => $validated['name'],
                'trigger_type' => $triggerType,
                'event_key' => $triggerType === 'event' ? $validated['event_key'] : null,
                'condition_definition' => $conditionDefinition,
                'audience_definition' => $audienceDefinition,
                'communication_template_id' => $validated['communication_template_id'],
                'communication_sender_identity_id' => $validated['communication_sender_identity_id'] ?? null,
                'classification' => $validated['classification'],
                'priority' => $validated['priority'],
                'delay_seconds' => $validated['delay_seconds'] ?? 0,
                'is_active' => (bool) ($validated['is_active'] ?? false),
                'created_by' => $actorId,
                'approved_by' => $actorId,
            ]);

            if ($triggerType === 'scheduled') {
                $interval = (int) $validated['schedule_definition']['interval'];
                $frequency = (string) $validated['frequency'];
                $timezone = (string) ($validated['timezone'] ?? 'Asia/Tehran');
                $now = CarbonImmutable::now($timezone);

                $nextRunAt = match ($frequency) {
                    'hourly' => $now->addHours($interval),
                    'daily' => $now->addDays($interval),
                    'weekly' => $now->addWeeks($interval),
                };

                $rule->schedule()->create([
                    'frequency' => $frequency,
                    'schedule_definition' => ['interval' => $interval],
                    'timezone' => $timezone,
                    'timezone_mode' => 'explicit',
                    'next_run_at' => $nextRunAt->utc(),
                ]);
            }
        });

        return redirect()->route('admin.communications.automations.index')
            ->with('success', 'قاعده ارتباطی ایجاد شد.');
    }

    public function deactivate(CommunicationRule $rule): RedirectResponse
    {
        if ($rule->is_active) {
            $rule->forceFill(['is_active' => false])->save();
        }

        return redirect()->route('admin.communications.automations.index')
            ->with('success', 'قاعده ارتباطی غیرفعال شد.');
    }

    private function invalid(Request $request, string $field, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'errors' => [$field => [$message]]], 422);
        }

        return back()->withInput()->withErrors([$field => $message]);
    }
}
