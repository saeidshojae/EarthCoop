<?php

namespace Tests\Feature\Reputation;

use Tests\TestCase;

class ParticipationCreditRegulationContractTest extends TestCase
{
    public function test_regulation_surface_is_wired_to_live_runtime_policy_sources(): void
    {
        $routes = file_get_contents(base_path('routes/participation-credit-regulation.php'));
        $provider = file_get_contents(app_path('Providers/RouteServiceProvider.php'));
        $controller = file_get_contents(app_path('Http/Controllers/ParticipationCreditRegulationController.php'));
        $service = file_get_contents(app_path('Services/ParticipationCreditRegulationService.php'));
        $view = file_get_contents(resource_path('views/participation/credit-regulation.blade.php'));

        $this->assertStringContainsString("name('participation.credit-regulation')", $routes);
        $this->assertStringContainsString("routes/participation-credit-regulation.php", $provider);
        $this->assertStringContainsString('ParticipationCreditRegulationService', $controller);
        $this->assertStringContainsString('ReputationRule::query()', $service);
        $this->assertStringContainsString("config('reputation.weights'", $service);
        $this->assertStringContainsString("config('reputation.tiers'", $service);
        $this->assertStringContainsString('MonetaryPolicyService', $service);
        $this->assertStringContainsString("'reputation_to_gol_ratio'", $service);
        $this->assertStringContainsString("'reputation_conversion_enabled'", $service);

        $this->assertStringContainsString('نظام‌نامه اعتبارات مشارکت', $view);
        $this->assertStringContainsString('policySnapshot', $view);
        $this->assertStringContainsString('actionRules', $view);
        $this->assertStringContainsString('tiers', $view);
        $this->assertStringContainsString('dimensions', $view);
        $this->assertStringContainsString('conversion', $view);
    }

    public function test_regulation_blade_does_not_duplicate_known_default_numeric_policy_values(): void
    {
        $view = file_get_contents(resource_path('views/participation/credit-regulation.blade.php'));

        foreach (['1000', '5000'] as $knownTierThreshold) {
            $this->assertStringNotContainsString($knownTierThreshold, $view);
        }

        $this->assertStringNotContainsString('هر 100 امتیاز', $view);
        $this->assertStringNotContainsString('100 امتیاز = 1 گل', $view);
    }

    public function test_my_points_surface_links_to_the_regulation(): void
    {
        $historyView = file_get_contents(resource_path('views/history/index.blade.php'));

        $this->assertStringContainsString("route('participation.credit-regulation')", $historyView);
        $this->assertStringContainsString('نظام‌نامه اعتبارات مشارکت', $historyView);
    }
}
