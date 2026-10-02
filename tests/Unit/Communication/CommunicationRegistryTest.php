<?php

namespace Tests\Unit\Communication;

use App\Services\Communication\CommunicationAudienceRegistry;
use App\Services\Communication\CommunicationConditionRegistry;
use App\Services\Communication\CommunicationEventRegistry;
use InvalidArgumentException;
use Tests\TestCase;

class CommunicationRegistryTest extends TestCase
{
    public function test_initial_event_registry_accepts_only_registered_events(): void
    {
        $registry = app(CommunicationEventRegistry::class);

        $definition = $registry->get('registration.completed');
        $this->assertSame('registration.completed', $definition['key']);
        $this->assertSame(['user_id', 'completed_at', 'locale'], $definition['required_payload']);

        $this->expectException(InvalidArgumentException::class);
        $registry->get('php.eval.anything');
    }

    public function test_initial_audience_registry_accepts_only_registered_resolvers(): void
    {
        $registry = app(CommunicationAudienceRegistry::class);

        $this->assertSame('event.user', $registry->get('event.user')['key']);
        $this->assertSame('specific.user', $registry->get('specific.user')['key']);
        $this->assertSame('role.manager', $registry->get('role.manager')['key']);
        $this->assertSame('role.inspector', $registry->get('role.inspector')['key']);

        $this->expectException(InvalidArgumentException::class);
        $registry->get('raw.sql');
    }

    public function test_initial_condition_registry_rejects_free_executable_expressions(): void
    {
        $registry = app(CommunicationConditionRegistry::class);

        $this->assertSame('user.registration_complete', $registry->get('user.registration_complete')['key']);
        $this->assertSame('user.email_verified', $registry->get('user.email_verified')['key']);

        $this->expectException(InvalidArgumentException::class);
        $registry->get('return shell_exec("id");');
    }
}
