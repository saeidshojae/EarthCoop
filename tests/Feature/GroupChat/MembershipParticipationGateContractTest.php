<?php

namespace Tests\Feature\GroupChat;

use Tests\TestCase;

class MembershipParticipationGateContractTest extends TestCase
{
    public function test_group_chat_runtime_exposes_membership_participation_and_loads_dedicated_gate_script(): void
    {
        $runtime = file_get_contents(resource_path('views/groups/partials/chat_runtime.blade.php'));

        $this->assertStringContainsString('MembershipParticipationEligibilityService', $runtime);
        $this->assertStringContainsString('membershipParticipation', $runtime);
        $this->assertStringContainsString("route('najm-bahar.agreement')", $runtime);
        $this->assertStringContainsString("route('najm-bahar.dashboard')", $runtime);
        $this->assertStringContainsString('membership-participation-gate.js', $runtime);
    }

    public function test_message_store_route_has_membership_participation_middleware_without_changing_controller(): void
    {
        $routes = file_get_contents(base_path('routes/membership-participation.php'));
        $provider = file_get_contents(app_path('Providers/RouteServiceProvider.php'));
        $middleware = file_get_contents(app_path('Http/Middleware/EnsureMembershipParticipation.php'));

        $this->assertStringContainsString("Route::post('/messages/send'", $routes);
        $this->assertStringContainsString('MessageController::class', $routes);
        $this->assertStringContainsString('EnsureMembershipParticipation::class', $routes);
        $this->assertStringContainsString("name('groups.messages.store')", $routes);
        $this->assertStringContainsString("routes/membership-participation.php", $provider);
        $this->assertStringContainsString('MembershipParticipationEligibilityService', $middleware);
        $this->assertStringContainsString('403', $middleware);
    }

    public function test_gate_script_replaces_only_chat_form_for_noneligible_members(): void
    {
        $script = file_get_contents(public_path('js/membership-participation-gate.js'));

        $this->assertStringContainsString("document.getElementById('chatForm')", $script);
        $this->assertStringContainsString('no_najm_bahar_account', $script);
        $this->assertStringContainsString('membership_fee_due', $script);
        $this->assertStringContainsString('agreementUrl', $script);
        $this->assertStringContainsString('dashboardUrl', $script);
        $this->assertStringNotContainsString('chat-box', $script);
    }
}
