<?php

namespace App\Services\Projects;

use RuntimeException;

class ProjectScopeCutoverRenderer
{
    /**
     * Adapt the existing Najm Bahar project form to the shared canonical Location
     * picker without mutating the legacy Blade template. Exact target geography is
     * persisted as Location; formal GovernanceArea scope is resolved server-side.
     */
    public function renderCanonical(string $legacyHtml, ?int $selectedLocationId = null): string
    {
        $selectedValue = $selectedLocationId !== null ? (string) $selectedLocationId : '';

        $canonicalPicker = implode("\n", [
            '<div data-location-selector data-location-purpose="project-scope" data-location-selector-context="project-scope" data-empty-label="یک گزینه را انتخاب کنید" data-loading-label="در حال دریافت گزینه‌های مکانی..." data-error-label="دریافت گزینه‌های مکانی ممکن نشد.">',
            sprintf('<input type="hidden" name="target_location_id" value="%s" data-location-id>', e($selectedValue)),
            '<input type="hidden" value="" data-project-governance-area-id>',
            '<input type="hidden" value="" data-location-proposal-id>',
            '<div data-location-levels class="vstack gap-3"></div>',
            '<div class="small text-secondary mt-2" data-location-status aria-live="polite">محدوده هدف پروژه را از بالا به پایین انتخاب کنید؛ انتخاب این بخش اختیاری است.</div>',
            '</div>',
        ]);

        $count = 0;
        $html = preg_replace(
            '~<select\s+id="geographic_continent_select"[^>]*>.*?</select>~s',
            $canonicalPicker,
            $legacyHtml,
            1,
            $count
        );

        if (!is_string($html) || $count !== 1) {
            throw new RuntimeException('Canonical project scope cutover could not locate the legacy geography selector.');
        }

        // The shared canonical picker owns traversal while this cutover is enabled.
        // Keep the legacy fields in the DOM for rollback, but never bootstrap their
        // old fixed geography AJAX chain in canonical mode.
        $html = str_replace(
            'initializeGeographicLocation();',
            '// Canonical Location picker mode: legacy geographic bootstrap disabled.',
            $html
        );

        $html = str_replace('قاره:', 'مکان هدف پروژه:', $html);
        $html = str_replace(
            'محدوده جغرافیایی مورد نظر برای پروژه را به‌صورت تفصیلی انتخاب کنید (اختیاری). این اطلاعات برای ارسال نوتیفیکیشن هدفمند به سرمایه‌گذاران استفاده می‌شود.',
            'مکان هدف پروژه را از ساختار رسمی و سلسله‌مراتبی EarthCoop مرحله‌به‌مرحله انتخاب کنید (اختیاری). محدوده حکمرانی رسمی متناظر به‌صورت امن در سرور تعیین می‌شود.',
            $html
        );

        return $html;
    }
}
