<?php

namespace App\Support;

use Illuminate\Foundation\Vite;

class EarthCoopVite extends Vite
{
    public const CSS_ENTRY = 'resources/css/vite.css';
    public const JS_ENTRY = 'resources/js/app.js';
    public const GROUP_CHAT_ENTRY = 'resources/js/group-chat-page.js';

    private ?bool $earthCoopHotState = null;

    /**
     * Keep local development from getting stuck on a stale public/hot file after
     * the Vite dev server is stopped. Only loopback hot URLs are actively probed;
     * non-loopback hot URLs retain Laravel's native behaviour unchanged.
     */
    public function isRunningHot()
    {
        if (! parent::isRunningHot()) {
            return false;
        }

        if ($this->earthCoopHotState !== null) {
            return $this->earthCoopHotState;
        }

        $hotFile = $this->hotFile();
        $hotUrl = trim((string) @file_get_contents($hotFile));
        $parts = $hotUrl !== '' ? parse_url($hotUrl) : false;
        $host = is_array($parts) ? ($parts['host'] ?? null) : null;
        $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? 'http')) : 'http';
        $port = is_array($parts) && isset($parts['port'])
            ? (int) $parts['port']
            : ($scheme === 'https' ? 443 : 80);

        if (! is_string($host) || $host === '' || $port <= 0) {
            @unlink($hotFile);
            return $this->earthCoopHotState = false;
        }

        $normalizedHost = strtolower(trim($host, '[]'));
        $loopbackHosts = ['localhost', '127.0.0.1', '::1'];
        if (! in_array($normalizedHost, $loopbackHosts, true)) {
            return $this->earthCoopHotState = true;
        }

        $errno = 0;
        $error = '';
        $socketHost = $normalizedHost === '::1' ? '[::1]' : $normalizedHost;
        $socket = @fsockopen($socketHost, $port, $errno, $error, 0.15);

        if (is_resource($socket)) {
            fclose($socket);
            return $this->earthCoopHotState = true;
        }

        @unlink($hotFile);
        return $this->earthCoopHotState = false;
    }

    /**
     * Keep the app stylesheet as an explicit HTML entry immediately before the
     * JavaScript entry. This makes Vite dev and production build use the same
     * cascade position instead of injecting CSS from app.js at runtime.
     *
     * Group chat receives its own page entry after the shared runtime. Keeping
     * this decision in the resolver avoids duplicating Vite directives across
     * large Blade views and guarantees the same order in dev and build modes.
     *
     * @param  string|array<int, string>  $entrypoints
     * @return array<int, string>
     */
    public function normalizeEntrypoints($entrypoints): array
    {
        $entries = is_array($entrypoints) ? array_values($entrypoints) : [$entrypoints];

        if (! in_array(self::JS_ENTRY, $entries, true)) {
            return $entries;
        }

        $entries = array_values(array_filter(
            $entries,
            static fn ($entry): bool => $entry !== self::CSS_ENTRY
        ));

        $jsIndex = array_search(self::JS_ENTRY, $entries, true);
        array_splice($entries, $jsIndex === false ? 0 : $jsIndex, 0, [self::CSS_ENTRY]);

        if (app()->bound('request') && request()->is('groups/chat/*')) {
            $entries[] = self::GROUP_CHAT_ENTRY;
        }

        return array_values(array_unique($entries));
    }

    /**
     * Generate Vite tags with the canonical EarthCoop CSS/JS order.
     *
     * @param  string|array<int, string>  $entrypoints
     * @param  string|null  $buildDirectory
     * @return \Illuminate\Support\HtmlString
     */
    public function __invoke($entrypoints, $buildDirectory = null)
    {
        return parent::__invoke($this->normalizeEntrypoints($entrypoints), $buildDirectory);
    }
}
