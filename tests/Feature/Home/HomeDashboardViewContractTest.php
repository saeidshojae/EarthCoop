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
        $this->assertStringNotContainsString('MembershipParticipationEligibilityService::class', $view);
        $this->assertStringNotContainsString('CurrentElectionCenterService::class', $view);
        $this->assertDoesNotMatchRegularExpression('/Poll::(?:query|where)/', $view);
    }

    public function test_home_exposes_membership_fee_state_hoda_intro_and_only_positive_today_signals(): void
    {
        $view = file_get_contents(resource_path('views/home.blade.php'));

        foreach ([
            "['residence']",
            "['najm_bahar']",
            "['membership_fee_paid']",
            "['groups']",
            "['invitation']",
            "['unread_notifications']",
            "['election_action_required']",
            "['poll_action_required']",
            "['pending_location_groups']",
        ] as $needle) {
            $this->assertStringContainsString($needle, $view);
        }

        $this->assertStringContainsString('حق عضویت دوره جاری', $view);
        $this->assertStringContainsString('data-home-hoda-intro', $view);
        $this->assertStringContainsString("route('najm-hoda.about')", $view);
        $this->assertStringContainsString('آشنایی با نجم هدا', $view);

        $this->assertMatchesRegularExpression('/@if\s*\(\s*\(int\)\s*\$today\[\'unread_notifications\'\]\s*>\s*0\s*\)/', $view);
        $this->assertMatchesRegularExpression('/@if\s*\(\s*\(int\)\s*\$today\[\'election_action_required\'\]\s*>\s*0\s*\)/', $view);
        $this->assertMatchesRegularExpression('/@if\s*\(\s*\(int\)\s*\$today\[\'poll_action_required\'\]\s*>\s*0\s*\)/', $view);
        $this->assertMatchesRegularExpression('/@if\s*\(\s*\(int\)\s*\$today\[\'pending_location_groups\'\]\s*>\s*0\s*\)/', $view);

        $this->assertStringContainsString('data-home-today-zero-state', $view);
        $this->assertStringContainsString('data-home-next-action', $view);
        $this->assertMatchesRegularExpression('/route\(\s*\$nextAction\[\'route\'\]\s*\)/', $view);
        $this->assertStringContainsString('$nextAction[\'fragment\']', $view);
        $this->assertStringContainsString('$nextAction[\'label\']', $view);
        $this->assertStringContainsString('$nextAction[\'description\']', $view);
    }
}
