<?php

namespace Tests\Feature\Home;

use Tests\TestCase;

class HomeDashboardViewContractTest extends TestCase
{
    public function test_home_consumes_the_dashboard_read_model_without_querying_domain_services_in_blade(): void
    {
        $view = file_get_contents(resource_path('views/home.blade.php'));

        $this->assertStringContainsString('$homeDashboard[\'journey\']', $view);
        $this->assertStringContainsString('$homeDashboard[\'today\']', $view);
        $this->assertStringContainsString('$homeDashboard[\'next_action\']', $view);

        $this->assertStringNotContainsString('AccountService::class', $view);
        $this->assertStringNotContainsString('CurrentElectionCenterService::class', $view);
        $this->assertDoesNotMatchRegularExpression('/Poll::(?:query|where)/', $view);
    }

    public function test_home_exposes_state_aware_journey_today_zero_state_and_next_action_contracts(): void
    {
        $view = file_get_contents(resource_path('views/home.blade.php'));

        foreach ([
            "['residence']",
            "['najm_bahar']",
            "['groups']",
            "['invitation']",
            "['unread_notifications']",
            "['election_action_required']",
            "['poll_action_required']",
            "['pending_location_groups']",
        ] as $needle) {
            $this->assertStringContainsString($needle, $view);
        }

        $this->assertStringContainsString('data-home-today', $view);
        $this->assertStringContainsString('data-home-today-zero-state', $view);
        $this->assertStringContainsString('data-home-next-action', $view);
        $this->assertMatchesRegularExpression('/route\(\s*\$nextAction\[\'route\'\]\s*\)/', $view);
        $this->assertStringContainsString('$nextAction[\'label\']', $view);
        $this->assertStringContainsString('$nextAction[\'description\']', $view);
    }
}
