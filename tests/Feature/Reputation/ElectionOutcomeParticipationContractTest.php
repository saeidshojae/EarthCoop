<?php

namespace Tests\Feature\Reputation;

use Tests\TestCase;

class ElectionOutcomeParticipationContractTest extends TestCase
{
    public function test_direct_election_appointments_are_the_only_outcome_reward_source(): void
    {
        $listener = file_get_contents(app_path('Listeners/AwardElectionAppointmentParticipation.php'));
        $provider = file_get_contents(app_path('Providers/EventServiceProvider.php'));

        $this->assertStringContainsString("\$appointment->appointment_kind !== 'direct'", $listener);
        $this->assertStringContainsString("'elected_' . \$position", $listener);
        $this->assertStringContainsString("':user:' . \$user->id . ':level:' . \$group->location_level", $listener);
        $this->assertStringContainsString('AwardElectionAppointmentParticipation::class', $provider);
    }

    public function test_election_outcome_rules_are_admin_convertible_but_not_convertible_by_default(): void
    {
        foreach (['elected_manager', 'elected_inspector'] as $action) {
            $this->assertSame('participation', config("reputation.policy_defaults.{$action}.dimension"));
            $this->assertFalse(config("reputation.policy_defaults.{$action}.convertible"));
            $this->assertSame('once_per_context', config("reputation.policy_defaults.{$action}.repeat_policy"));
        }
    }
}
