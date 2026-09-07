<?php

namespace Tests\Feature\Reputation;

use Tests\TestCase;

class ReputationUserSurfacesContractTest extends TestCase
{
    public function test_public_profile_uses_only_public_safe_summary_and_persian_dimension_labels(): void
    {
        $service = file_get_contents(app_path('Services/ParticipationPointSummaryService.php'));
        $view = file_get_contents(resource_path('views/profile/profile-member.blade.php'));

        // The legacy profile controller is intentionally left untouched; the small
        // wrapper calls the canonical public-safe service boundary and inherits the
        // original profile view without duplicating its UI.
        $this->assertStringContainsString('ParticipationPointSummaryService', $view);
        $this->assertStringContainsString('publicReputationSummary', $service);
        $this->assertStringContainsString('publicReputationSummary', $view);
        $this->assertStringContainsString("@extends('profile.profile-member-base')", $view);

        $this->assertStringContainsString('اعتبار و مشارکت', $view);
        $this->assertStringContainsString('مشارکت', $view);
        $this->assertStringContainsString('اعتمادپذیری', $view);
        $this->assertStringContainsString('تخصص', $view);
        $this->assertStringContainsString('اعتماد مدنی', $view);

        $this->assertStringNotContainsString('remaining_convertible_points', $view);
        $this->assertStringNotContainsString('ledger_consumed_points', $view);
        $this->assertStringNotContainsString('legacy_cashed_points', $view);
        $this->assertStringNotContainsString('convertible_awarded_points', $view);
        $this->assertStringNotContainsString('قابل نقد', $view);
        $this->assertStringNotContainsString('نقدشده', $view);
    }

    public function test_my_points_tab_uses_canonical_summary_and_exposes_private_conversion_action_in_persian(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Profile/HistoryController.php'));
        $view = file_get_contents(resource_path('views/history/index.blade.php'));

        $this->assertStringContainsString('ParticipationPointSummaryService', $controller);
        $this->assertStringContainsString('pointSummary', $controller);
        $this->assertStringContainsString('reputationBreakdown', $controller);
        $this->assertStringContainsString("@extends('history.index-base')", $view);

        $this->assertStringContainsString('اعتبار و مشارکت', $view);
        $this->assertStringContainsString('امتیاز مشارکت قابل تبدیل', $view);
        $this->assertStringContainsString('کسب‌شده قابل تبدیل', $view);
        $this->assertStringContainsString('تبدیل‌شده', $view);
        $this->assertStringContainsString('امتیاز قابل استفاده', $view);
        $this->assertStringContainsString('تبدیل به بهار', $view);
        $this->assertStringContainsString("route('reputation.conversion.convert')", $view);

        $this->assertStringNotContainsString('participation_reversal_points', $view);
        $this->assertStringNotContainsString('legacy_cashed_points', $view);
    }

    public function test_summary_service_defines_public_dimension_breakdown_with_legacy_bucket(): void
    {
        $service = file_get_contents(app_path('Services/ParticipationPointSummaryService.php'));

        $this->assertStringContainsString('publicReputationSummary', $service);
        $this->assertStringContainsString('reputationBreakdown', $service);
        $this->assertStringContainsString("'participation'", $service);
        $this->assertStringContainsString("'reliability'", $service);
        $this->assertStringContainsString("'expertise'", $service);
        $this->assertStringContainsString("'civic_trust'", $service);
        $this->assertStringContainsString("'legacy_other'", $service);
    }
}
