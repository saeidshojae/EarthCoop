<?php

namespace App\Http\Controllers\Profile;

use App\Enums\Communication\CommunicationClassification;
use App\Http\Controllers\Controller;
use App\Models\CommunicationPreference;
use App\Models\CommunicationRule;
use App\Models\CommunicationTemplate;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class CommunicationPreferenceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $templates = CommunicationTemplate::query()
            ->where('is_active', true)
            ->orderBy('classification')
            ->orderBy('key')
            ->get();

        $preferences = CommunicationPreference::query()
            ->where('user_id', $user->id)
            ->where('channel', 'email')
            ->get()
            ->keyBy('topic_key');

        $cadences = CommunicationRule::query()
            ->with('schedule')
            ->where('trigger_type', 'scheduled')
            ->where('is_active', true)
            ->whereIn('communication_template_id', $templates->pluck('id'))
            ->get()
            ->filter(fn (CommunicationRule $rule): bool => $rule->schedule !== null)
            ->mapWithKeys(fn (CommunicationRule $rule): array => [
                $rule->template->key => $rule->schedule->frequency,
            ]);

        return view('profile.communication-preferences', compact('templates', 'preferences', 'cadences'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $input = $request->input('preferences', []);
        if (! is_array($input)) {
            throw ValidationException::withMessages([
                'preferences' => 'ساختار ترجیحات نامعتبر است.',
            ]);
        }

        $templates = CommunicationTemplate::query()
            ->where('is_active', true)
            ->whereIn('key', array_keys($input))
            ->get()
            ->keyBy('key');

        foreach ($input as $topicKey => $preference) {
            $topicKey = (string) $topicKey;
            $preference = (string) $preference;
            $template = $templates->get($topicKey);

            if (! $template) {
                throw ValidationException::withMessages([
                    'preferences.'.$topicKey => 'موضوع ارتباطی ثبت‌شده نیست.',
                ]);
            }

            if (! in_array($preference, ['on', 'off'], true)) {
                throw ValidationException::withMessages([
                    'preferences.'.$topicKey => 'مقدار ترجیح نامعتبر است.',
                ]);
            }

            if ($template->classification === CommunicationClassification::Required) {
                if ($preference === 'off') {
                    throw ValidationException::withMessages([
                        'preferences.'.$topicKey => 'ارتباطات ضروری قابل غیرفعال‌سازی نیستند.',
                    ]);
                }

                // Required policy is canonical and never depends on a suppressive preference row.
                CommunicationPreference::query()
                    ->where('user_id', $user->id)
                    ->where('topic_key', $topicKey)
                    ->where('channel', 'email')
                    ->delete();
                continue;
            }

            CommunicationPreference::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'topic_key' => $topicKey,
                    'channel' => 'email',
                ],
                ['preference' => $preference],
            );
        }

        return redirect()->route('profile.communication-preferences')
            ->with('success', 'ترجیحات ارتباطی ذخیره شد.');
    }

    public function unsubscribe(Request $request, User $user): View
    {
        $topicKey = trim((string) $request->query('topic', ''));
        $template = CommunicationTemplate::query()
            ->where('key', $topicKey)
            ->where('is_active', true)
            ->firstOrFail();

        if ($template->classification !== CommunicationClassification::Optional) {
            abort(422, 'این نوع ارتباط از طریق لینک لغو دریافت قابل غیرفعال‌سازی نیست.');
        }

        CommunicationPreference::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'topic_key' => $template->key,
                'channel' => 'email',
            ],
            ['preference' => 'off'],
        );

        return view('profile.communication-unsubscribed', [
            'topicKey' => $template->key,
        ]);
    }
}
