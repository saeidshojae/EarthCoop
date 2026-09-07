<?php

namespace Tests\Feature\NajmHoda;

use App\Models\ExperienceField;
use App\Models\Neighborhood;
use App\Models\User;
use App\Observers\NajmHoda\FounderReferenceDataObserver;
use App\Observers\NajmHoda\FounderUserObserver;
use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Tests\TestCase;

class FounderOperationsInstrumentationTest extends TestCase
{
    public function test_new_human_user_emits_founder_event_without_personal_contact_data(): void
    {
        $bus = new InMemoryRuntimeEventBus(100);
        $this->app->instance(RuntimeEventBus::class, $bus);

        $user = new User([
            'email' => 'member@example.test',
            'phone' => '09120000000',
            'status' => 1,
            'is_system' => false,
            'occupational_status' => 0,
            'experience_status' => 0,
        ]);
        $user->id = 123;

        (new FounderUserObserver())->created($user);

        $events = $bus->recent('najm_hoda.input.founder.user.created', 1);

        $this->assertNotEmpty($events);
        $this->assertSame(123, (int) data_get($events[0], 'payload.user_id'));
        $this->assertSame('users', (string) data_get($events[0], 'payload.category'));
        $this->assertArrayNotHasKey('email', data_get($events[0], 'payload', []));
        $this->assertArrayNotHasKey('phone', data_get($events[0], 'payload', []));
    }

    public function test_system_identity_does_not_emit_founder_user_event(): void
    {
        $bus = new InMemoryRuntimeEventBus(100);
        $this->app->instance(RuntimeEventBus::class, $bus);

        $user = new User([
            'status' => 1,
            'is_system' => true,
        ]);
        $user->id = 999;

        (new FounderUserObserver())->created($user);

        $this->assertSame([], $bus->recent('najm_hoda.input.founder.user.created', 10));
    }

    public function test_pending_experience_emits_founder_approval_event(): void
    {
        $bus = new InMemoryRuntimeEventBus(100);
        $this->app->instance(RuntimeEventBus::class, $bus);

        $field = new ExperienceField([
            'name' => 'Data Science',
            'status' => 0,
        ]);
        $field->id = 42;

        (new FounderReferenceDataObserver())->created($field);

        $events = $bus->recent('najm_hoda.input.founder.reference.pending', 1);

        $this->assertNotEmpty($events);
        $this->assertSame('experience', (string) data_get($events[0], 'payload.reference_type'));
        $this->assertSame('reference_approval', (string) data_get($events[0], 'payload.category'));
        $this->assertTrue((bool) data_get($events[0], 'payload.action_required'));
    }

    public function test_pending_neighborhood_emits_location_approval_event(): void
    {
        $bus = new InMemoryRuntimeEventBus(100);
        $this->app->instance(RuntimeEventBus::class, $bus);

        $neighborhood = new Neighborhood([
            'name' => 'Test Neighborhood',
            'status' => 0,
        ]);
        $neighborhood->id = 77;

        (new FounderReferenceDataObserver())->created($neighborhood);

        $events = $bus->recent('najm_hoda.input.founder.reference.pending', 1);

        $this->assertNotEmpty($events);
        $this->assertSame('neighborhood', (string) data_get($events[0], 'payload.reference_type'));
        $this->assertSame('location_approval', (string) data_get($events[0], 'payload.category'));
    }
}
