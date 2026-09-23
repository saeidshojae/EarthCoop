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

    public static function typed(Location|LocationProposal $model, ?string $locale = null): string
    {
        $name = self::for($model, $locale);
        $typeKey = $model instanceof Location
            ? $model->type?->key
            : $model->type?->key;

        $prefixes = [
            'continent' => 'قاره',
            'country' => 'کشور',
            'province' => 'استان',
            'county' => 'شهرستان',
            'section' => 'بخش',
            'rural_district' => 'دهستان',
            'city' => 'شهر',
            'village' => 'روستا',
            'urban_region' => 'منطقه',
            'neighborhood' => 'محله',
            'street' => 'خیابان',
            'alley' => 'کوچه',
            'complex' => 'مجتمع',
            'building' => 'ساختمان',
        ];

        $prefix = $prefixes[$typeKey] ?? '';
        if ($prefix === '' || $name === '') {
            return $name;
        }

        if ($typeKey === 'urban_region' && str_starts_with($name, 'منطقه شهری ')) {
            $name = 'منطقه '.trim(substr($name, strlen('منطقه شهری ')));
        }

        $aliases = match ($typeKey) {
            'province' => ['استان', 'ایالت'],
            'village' => ['روستا', 'روستای'],
            'urban_region' => ['منطقه', 'منطقه شهری'],
            default => [$prefix],
        };

        foreach ($aliases as $alias) {
            if ($name === $alias || str_starts_with($name, $alias.' ')) {
                return $name;
            }
        }

        return $prefix.' '.$name;
    }
}
