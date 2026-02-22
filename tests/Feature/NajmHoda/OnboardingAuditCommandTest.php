<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class OnboardingAuditCommandTest extends TestCase
{
    public function test_command_reports_ok_when_events_and_policy_exist(): void
    {
        config([
            'najm-hoda.enabled' => true,
        ]);

        $bus = new InMemoryRuntimeEventBus(300);
        $this->app->instance(RuntimeEventBus::class, $bus);

        $bus->emit('najm_hoda.input.support.service.ticket_triage.requested', ['scope' => 'support', 'risk' => 'low']);
        $bus->emit('najm_hoda.input.support.service.ticket_triage.succeeded', ['scope' => 'support', 'risk' => 'low']);
        $bus->emit('najm_hoda.input.support.service.ticket_triage.failed', ['scope' => 'support', 'risk' => 'medium']);
        $bus->emit('najm_hoda.autonomy.governance.alert.raised', ['domain' => 'support', 'scope' => 'autonomy', 'risk' => 'low']);

        $exit = Artisan::call('najm-hoda:onboarding-audit', [
            '--module' => 'support',
            '--prefix' => 'najm_hoda.input.support.service.',
            '--window' => 24,
            '--limit' => 500,
            '--fail-on-gap' => true,
        ]);

        $this->assertSame(0, $exit);
    }

    public function test_command_fails_on_gap_when_required_flag_is_set(): void
    {
        config([
            'najm-hoda.enabled' => true,
        ]);

        $bus = new InMemoryRuntimeEventBus(200);
        $this->app->instance(RuntimeEventBus::class, $bus);

        $bus->emit('najm_hoda.input.auth.service.login.succeeded', ['scope' => 'auth', 'risk' => 'low']);

        $exit = Artisan::call('najm-hoda:onboarding-audit', [
            '--module' => 'auth',
            '--prefix' => 'najm_hoda.input.auth.service.',
            '--window' => 24,
            '--limit' => 500,
            '--fail-on-gap' => true,
        ]);

        $this->assertSame(1, $exit);
    }
}

