<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GroupSyncEvent;
use App\Models\RealtimeSetting;
use App\Services\GroupChat\RealtimeSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class RealtimeSettingsController extends Controller
{
    public function index(RealtimeSettingsService $runtime)
    {
        $this->authorizeManagement();
        abort_unless(Schema::hasTable('realtime_settings'), 503, 'Realtime settings migration has not been applied.');
        $settings = RealtimeSetting::query()->firstOrCreate([], [
            'transport' => config('group-chat.transport', 'polling'),
            'fallback_to_polling' => config('group-chat.fallback_to_polling', true),
            'polling_interval_ms' => config('group-chat.polling_interval_ms', 1800),
        ]);
        $runtime->reset();
        $effective = $runtime->effective();
        $health = [
            'journal_available' => Schema::hasTable('group_sync_events'),
            'journal_events' => Schema::hasTable('group_sync_events') ? GroupSyncEvent::count() : 0,
            'latest_event_at' => Schema::hasTable('group_sync_events') ? GroupSyncEvent::max('occurred_at') : null,
            'credentials_complete' => $effective['transport'] === 'polling'
                || (filled($effective['app_id']) && filled($effective['app_key']) && filled($effective['app_secret'])),
        ];

        return view('admin.system-settings.realtime.index', compact('settings', 'effective', 'health'));
    }

    public function update(Request $request, RealtimeSettingsService $runtime)
    {
        $this->authorizeManagement();
        $settings = RealtimeSetting::query()->firstOrCreate([]);
        $validated = $request->validate([
            'transport' => ['required', Rule::in(['polling', 'auto', 'websocket'])],
            'provider' => ['required', Rule::in(['reverb', 'soketi', 'pusher'])],
            'app_id' => ['nullable', 'string', 'max:190'],
            'app_key' => ['nullable', 'string', 'max:190'],
            'app_secret' => ['nullable', 'string', 'max:500'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'scheme' => ['required', Rule::in(['http', 'https'])],
            'cluster' => ['nullable', 'string', 'max:30'],
            'polling_interval_ms' => ['required', 'integer', 'min:1000', 'max:10000'],
        ]);
        $validated['enabled'] = $request->boolean('enabled');
        $validated['fallback_to_polling'] = $request->boolean('fallback_to_polling');
        $validated['use_env_credentials'] = $request->boolean('use_env_credentials');
        $validated['updated_by'] = auth()->id();

        if (! $validated['use_env_credentials'] && $validated['transport'] !== 'polling') {
            if (blank($validated['app_id']) || blank($validated['app_key'])) {
                return back()->withErrors(['app_key' => 'App ID and App Key are required when environment credentials are disabled.'])->withInput();
            }
            if (blank($validated['app_secret'] ?? null) && blank($settings->app_secret)) {
                return back()->withErrors(['app_secret' => 'App Secret is required the first time credentials are saved.'])->withInput();
            }
            if (in_array($validated['provider'], ['reverb', 'soketi'], true) && blank($validated['host'])) {
                return back()->withErrors(['host' => 'Host is required for Reverb and Soketi.'])->withInput();
            }
        }

        if ($validated['use_env_credentials']) {
            unset(
                $validated['app_id'],
                $validated['app_key'],
                $validated['app_secret'],
                $validated['host'],
                $validated['port'],
                $validated['scheme'],
                $validated['cluster']
            );
        } elseif (blank($validated['app_secret'] ?? null)) {
            unset($validated['app_secret']);
        }
        $settings->fill($validated);
        $settings->last_test_status = null;
        $settings->last_test_message = null;
        $settings->last_tested_at = null;
        $settings->save();
        $runtime->reset();

        return redirect()->route('admin.system-settings.realtime.index')
            ->with('success', 'Realtime settings were saved and will apply to newly loaded pages.');
    }

    public function test(RealtimeSettingsService $runtime)
    {
        $this->authorizeManagement();
        $settings = RealtimeSetting::query()->firstOrCreate([]);
        $runtime->reset();
        try {
            $result = $runtime->testConnection();
            $settings->forceFill([
                'last_test_status' => 'success',
                'last_test_message' => $result['message'],
                'last_tested_at' => now(),
            ])->save();

            return back()->with('success', $result['message']);
        } catch (\Throwable $exception) {
            report($exception);
            $message = mb_substr($exception->getMessage(), 0, 1000);
            $secret = (string) ($runtime->effective()['app_secret'] ?? '');
            if ($secret !== '') {
                $message = str_replace($secret, '[redacted]', $message);
            }
            $settings->forceFill([
                'last_test_status' => 'failed',
                'last_test_message' => $message,
                'last_tested_at' => now(),
            ])->save();

            return back()->with('error', 'Realtime connection test failed: ' . $message);
        }
    }

    private function authorizeManagement(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->is_admin || $user->hasRole('super-admin')), 403);
    }
}
