<?php

namespace App\Services\Projects;

use Illuminate\Support\Collection;
use RuntimeException;

class ProjectScopeCutoverRenderer
{
    /**
     * Adapt the existing Najm Bahar project form to the canonical GovernanceArea
     * selector without mutating the legacy Blade template. This is intentionally
     * a reversible strangler adapter: when the feature flag is off, callers render
     * the legacy view directly and this code is never involved.
     */
    public function renderCanonical(string $legacyHtml, Collection $areas, ?int $selectedAreaId = null): string
    {
        $options = ['<option value="">انتخاب کنید</option>'];

        foreach ($areas as $area) {
            $id = (int) $area->id;
            $name = e((string) $area->canonical_name);
            $selected = $selectedAreaId === $id ? ' selected' : '';
            $options[] = sprintf('<option value="%d"%s>%s</option>', $id, $selected, $name);
        }

        $canonicalSelect = implode("\n", [
            '<select id="governance_area_select" name="governance_area_id" class="w-full px-3 py-2 border border-gray-300 rounded-md" data-governance-scope-cutover="canonical">',
            implode("\n", $options),
            '</select>',
        ]);

        $count = 0;
        $html = preg_replace(
            '~<select\s+id="geographic_continent_select"[^>]*>.*?</select>~s',
            $canonicalSelect,
            $legacyHtml,
            1,
            $count
        );

        if (!is_string($html) || $count !== 1) {
            throw new RuntimeException('Canonical project scope cutover could not locate the legacy geography selector.');
        }

        // The canonical selector is not a legacy .location-select, so the delegated
        // legacy change handler will ignore it. Disable only the eager AJAX bootstrap
        // that otherwise expects #geographic_continent_select to exist.
        $html = str_replace(
            'initializeGeographicLocation();',
            '// Canonical GovernanceArea mode: legacy geographic bootstrap disabled.',
            $html
        );

        $html = str_replace('قاره:', 'محدوده حکمرانی:', $html);
        $html = str_replace(
            'محدوده جغرافیایی مورد نظر برای پروژه را به‌صورت تفصیلی انتخاب کنید (اختیاری). این اطلاعات برای ارسال نوتیفیکیشن هدفمند به سرمایه‌گذاران استفاده می‌شود.',
            'محدوده حکمرانی پروژه را از ساختار رسمی و یکپارچه EarthCoop انتخاب کنید (اختیاری).',
            $html
        );

        return $html;
    }
}
