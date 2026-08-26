<?php

namespace App\Support;

use Illuminate\Foundation\Vite;

class EarthCoopVite extends Vite
{
    public const CSS_ENTRY = 'resources/css/vite.css';
    public const JS_ENTRY = 'resources/js/app.js';

    /**
     * Keep the app stylesheet as an explicit HTML entry immediately before the
     * JavaScript entry. This makes Vite dev and production build use the same
     * cascade position instead of injecting CSS from app.js at runtime.
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
