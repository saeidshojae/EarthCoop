<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\NajmHodaInteractionBoundaryService;
use App\Services\NajmHoda\Runtime\NajmHodaCapabilityRegistry;
use Mockery;
use Tests\TestCase;

class InteractionBoundaryTest extends TestCase
{
    public function test_plain_chat_defaults_to_answer_mode(): void
    {
        $registry = Mockery::mock(NajmHodaCapabilityRegistry::class);
        $registry->shouldNotReceive('contract');

        $service = new NajmHodaInteractionBoundaryService($registry);
        $result = $service->classify('چطور یک گروه بسازم؟');

        $this->assertSame('answer', $result['mode']);
        $this->assertSame('no_explicit_action_request', $result['reason']);
    }

    public function test_unknown_explicit_action_is_blocked(): void
    {
        $registry = Mockery::mock(NajmHodaCapabilityRegistry::class);
        $registry->shouldReceive('contract')->once()->with('delete_everything')->andReturn(null);

        $service = new NajmHodaInteractionBoundaryService($registry);
        $result = $service->classify('این کار را انجام بده', [
            'requested_action' => 'delete_everything',
        ]);

        $this->assertSame('blocked_action', $result['mode']);
        $this->assertSame('unknown_action_contract', $result['reason']);
    }

    public function test_registered_action_is_routed_to_action_boundary_without_execution(): void
    {
        $registry = Mockery::mock(NajmHodaCapabilityRegistry::class);
        $registry->shouldReceive('contract')->once()->with('set_ticket_needs_review')->andReturn([
            'enabled' => true,
            'risk' => 'low',
            'mode' => 'propose',
        ]);

        $service = new NajmHodaInteractionBoundaryService($registry);
        $result = $service->classify('این تیکت را برای بررسی علامت بزن', [
            'requested_action' => 'set_ticket_needs_review',
            'action_input' => ['ticket_id' => 42],
        ]);

        $this->assertSame('action', $result['mode']);
        $this->assertSame('set_ticket_needs_review', $result['action']);
        $this->assertSame(['ticket_id' => 42], $result['input']);
        $this->assertSame('propose', $result['default_mode']);
    }
}
