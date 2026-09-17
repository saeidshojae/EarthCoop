<?php

namespace App\Support;

use App\Models\Location;
use App\Models\LocationProposal;

final class LocationDisplayName
{
    public static function for(Location|LocationProposal $model, ?string $locale = null): string
    {
        $locale = trim((string) ($locale ?: app()->getLocale()));
        $localizedNames = is_array($model->localized_names) ? $model->localized_names : [];

        $localeKeys = [];
        if ($locale !== '') {
            $localeKeys[] = $locale;
            $language = strtolower((string) strtok(str_replace('_', '-', $locale), '-'));
            if ($language !== '' && ! in_array($language, $localeKeys, true)) {
                $localeKeys[] = $language;
            }
        }

        foreach ($localeKeys as $key) {
            $localized = trim((string) ($localizedNames[$key] ?? ''));
            if ($localized !== '') {
                return $localized;
            }
        }

        $canonical = trim((string) $model->canonical_name);
        if ($canonical !== '') {
            return $canonical;
        }

        if ($model instanceof Location) {
            return trim((string) $model->name);
        }

        return '';
    }
}
