<?php

namespace Tests\Feature\Home;

use Tests\TestCase;

class HomeDashboardViewContractTest extends TestCase
{
    public function test_home_consumes_the_dashboard_read_model_without_querying_domain_services_in_blade(): void
    {
        $view = file_get_contents(resource_path('views/home.blade.php'));
        $polish = file_get_contents(resource_path('views/components/home-shell-polish.blade.php'));
        $presentation = $view . "\n" . $polish;

        $this->assertStringContainsString('$homeDashboard[\'journey\']', $view);
        $this->assertStringContainsString('$homeDashboard[\'today\']', $view);
        $this->assertStringContainsString('$homeDashboard[\'next_action\']', $view);

        $this->assertStringNotContainsString('AccountService::class', $presentation);
        $this->assertStringNotContainsString('MembershipParticipationEligibilityService::class', $presentation);
        $this->assertStringNotContainsString('CurrentElectionCenterService::class', $presentation);
        $this->assertDoesNotMatchRegularExpression('/Poll::(?:query|where)/', $presentation);
    }

    public function test_home_exposes_membership_fee_state_hoda_intro_and_only_positive_today_signals(): void
    {
        $view = file_get_contents(resource_path('views/home.blade.php'));
        $polish = file_get_contents(resource_path('views/components/home-shell-polish.blade.php'));
        $presentation = $view . "\n" . $polish;

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

        $this->assertStringContainsString('membership_fee_due', $polish);
        $this->assertStringContainsString('حق عضویت دوره جاری', $polish);
        $this->assertStringContainsString('data-home-hoda-intro', $polish);
        $this->assertStringContainsString("route('najm-hoda.about')", $polish);
        $this->assertStringContainsString('آشنایی با نجم هدا', $polish);
        $this->assertStringContainsString("Number.parseInt(item.querySelector('strong')", $polish);
        $this->assertStringContainsString('if (!Number.isFinite(value) || value <= 0) item.hidden = true;', $polish);

        $this->assertStringContainsString('data-home-today-zero-state', $view);
        $this->assertStringContainsString('data-home-next-action', $view);
        $this->assertMatchesRegularExpression('/route\(\s*\$nextAction\[\'route\'\]\s*\)/', $view);
        $this->assertStringContainsString('next_action.fragment', $polish);
        $this->assertStringContainsString('$nextAction[\'label\']', $view);
        $this->assertStringContainsString('$nextAction[\'description\']', $view);
    }
}
