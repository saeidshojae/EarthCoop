<?php

namespace App\Services\GroupChat;

use App\Models\RealtimeSetting;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class RealtimeSettingsService
{
    private ?array $resolved = null;

    public function effective(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $row = Schema::hasTable('realtime_settings') ? RealtimeSetting::query()->first() : null;
        $useEnv = ! $row || $row->use_env_credentials;
        $envConnection = (array) config('broadcasting.connections.pusher', []);
        $envOptions = (array) ($envConnection['options'] ?? []);
        $transport = strtolower((string) ($row?->transport ?: config('group-chat.transport', 'auto')));
        $provider = strtolower((string) ($row?->provider ?: 'reverb'));
        $cluster = (string) ($useEnv ? ($envOptions['cluster'] ?? 'mt1') : ($row->cluster ?: 'mt1'));
        $host = $useEnv ? ($envOptions['host'] ?? null) : $row->host;
        if ($provider === 'pusher' && blank($host)) {
            $host = 'api-' . $cluster . '.pusher.com';
        }

        return $this->resolved = [
            'enabled' => (bool) ($row?->enabled ?? config('group-chat.enabled', true)),
            'transport' => in_array($transport, ['polling', 'auto', 'websocket'], true) ? $transport : 'polling',
            'provider' => in_array($provider, ['reverb', 'soketi', 'pusher'], true) ? $provider : 'reverb',
            'fallback_to_polling' => (bool) ($row?->fallback_to_polling ?? config('group-chat.fallback_to_polling', true)),
            'use_env_credentials' => $useEnv,
            'app_id' => (string) ($useEnv ? ($envConnection['app_id'] ?? '') : ($row->app_id ?? '')),
            'app_key' => (string) ($useEnv ? ($envConnection['key'] ?? '') : ($row->app_key ?? '')),
            'app_secret' => (string) ($useEnv ? ($envConnection['secret'] ?? '') : ($row->app_secret ?? '')),
            'host' => $host ? (string) $host : null,
            'port' => (int) ($useEnv ? ($envOptions['port'] ?? 443) : ($row->port ?: 443)),
            'scheme' => strtolower((string) ($useEnv ? ($envOptions['scheme'] ?? 'https') : ($row->scheme ?: 'https'))),
            'cluster' => $cluster,
            'polling_interval_ms' => min(10000, max(1000, (int) ($row?->polling_interval_ms ?? config('group-chat.polling_interval_ms', 1800)))),
        ];
    }

    public function publicConfig(): array
    {
        $settings = $this->effective();

        return [
            'enabled' => $settings['enabled'],
            'transport' => $settings['transport'],
            'provider' => $settings['provider'],
            'fallbackToPolling' => $settings['fallback_to_polling'],
            'key' => $settings['transport'] === 'polling' ? null : $settings['app_key'],
            'host' => $settings['provider'] === 'pusher' ? null : $settings['host'],
            'port' => $settings['port'],
            'scheme' => $settings['scheme'],
            'cluster' => $settings['cluster'],
            'pollingIntervalMs' => $settings['polling_interval_ms'],
        ];
    }

    public function applyBroadcastingConfig(): array
    {
        $settings = $this->effective();
        if (! $settings['enabled'] || $settings['transport'] === 'polling') {
            Config::set('broadcasting.default', 'null');
            return $settings;
        }

        Config::set('broadcasting.default', 'pusher');
        Config::set('broadcasting.connections.pusher.key', $settings['app_key']);
        Config::set('broadcasting.connections.pusher.secret', $settings['app_secret']);
        Config::set('broadcasting.connections.pusher.app_id', $settings['app_id']);
        Config::set('broadcasting.connections.pusher.options.host', $settings['host']);
        Config::set('broadcasting.connections.pusher.options.port', $settings['port']);
        Config::set('broadcasting.connections.pusher.options.scheme', $settings['scheme']);
        Config::set('broadcasting.connections.pusher.options.encrypted', $settings['scheme'] === 'https');
        Config::set('broadcasting.connections.pusher.options.useTLS', $settings['scheme'] === 'https');
        Config::set('broadcasting.connections.pusher.options.cluster', $settings['cluster']);
        Broadcast::purge('pusher');

        return $settings;
    }

    public function reset(): void
    {
        $this->resolved = null;
    }

    public function testConnection(): array
    {
        $settings = $this->applyBroadcastingConfig();
        if (! $settings['enabled']) {
            throw new \RuntimeException('Realtime is disabled.');
        }
        if ($settings['transport'] === 'polling') {
            return ['status' => 'success', 'message' => 'Polling is active and does not require a WebSocket server.'];
        }
        if (! $settings['app_id'] || ! $settings['app_key'] || ! $settings['app_secret']) {
            throw new \RuntimeException('App ID, key and secret are required for the selected provider.');
        }

        Broadcast::connection('pusher')->broadcast(
            ['realtime-health'],
            'realtime.health',
            ['tested_at' => now()->toIso8601String()]
        );

        return ['status' => 'success', 'message' => 'The realtime provider accepted the test event.'];
    }
}
