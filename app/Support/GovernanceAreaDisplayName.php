<?php

namespace App\Support;

use App\Models\GovernanceArea;

final class GovernanceAreaDisplayName
{
    public static function for(?GovernanceArea $area, ?string $locale = null): string
    {
        if ($area === null) {
            return '—';
        }

        $locale = trim((string) ($locale ?: app()->getLocale()));
        $localizedNames = is_array($area->localized_names) ? $area->localized_names : [];
        $keys = $locale !== '' ? [$locale] : [];
        $language = strtolower((string) strtok(str_replace('_', '-', $locale), '-'));
        if ($language !== '' && ! in_array($language, $keys, true)) {
            $keys[] = $language;
        }

        foreach ($keys as $key) {
            $name = trim((string) ($localizedNames[$key] ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        return trim((string) ($area->canonical_name ?: $area->key));
    }
}
