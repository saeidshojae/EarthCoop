<?php

namespace Tests\Feature\Api\V1;

use App\Models\Ticket;
use App\Models\User;
use App\Services\NajmHoda\Runtime\NajmHodaResourceAuthorizationService;
use App\Services\NajmHoda\Runtime\NajmHodaRuntimeAuthorityFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvalidArgumentException;
use Tests\TestCase;

class NajmHodaAuthorityContractTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'najm-hoda.runtime.autonomy.capabilities.set_ticket_needs_review' => [
                'enabled' => true,
                'version' => 1,
                'risk' => 'low',
                'mode' => 'propose',
                'human_approval_required' => false,
                'resource_authorization_required' => true,
                'required_input' => ['ticket_id'],
                'optional_input' => [],
                'output' => ['ticket_id'],
            ],
            'najm-hoda.runtime.autonomy.capabilities.protected_unknown_action' => [
                'enabled' => true,
                'version' => 1,
                'risk' => 'low',
                'mode' => 'propose',
                'human_approval_required' => false,
                'resource_authorization_required' => true,
                'required_input' => ['resource_id'],
                'optional_input' => [],
                'output' => [],
            ],
            'najm-hoda.runtime.autonomy.capabilities.disabled_action' => [
                'enabled' => false,
                'version' => 1,
                'risk' => 'low',
                'mode' => 'propose',
                'required_input' => [],
                'optional_input' => [],
                'output' => [],
            ],
        ]);
    }

    public function test_owner_can_receive_server_minted_propose_and_apply_authority(): void
    {
        $owner = User::factory()->create();
        $ticket = $this->ticketFor($owner);
        $factory = app(NajmHodaRuntimeAuthorityFactory::class);

        $propose = $factory->propose($owner, 'set_ticket_needs_review', ['ticket_id' => $ticket->id], 'mobile_api');
        $apply = $factory->apply($owner, 'set_ticket_needs_review', ['ticket_id' => $ticket->id], 'mobile_api', 'consent-evidence-1');

        $this->assertSame($owner->id, $propose->actorId);
        $this->assertFalse($propose->allowApply);
        $this->assertSame('mobile_api', $propose->source);
        $this->assertSame($owner->id, $apply->actorId);
        $this->assertTrue($apply->allowApply);
    }

    public function test_wrong_user_resource_is_denied_without_minting_apply_authority(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $ticket = $this->ticketFor($owner);

        $this->expectException(AuthorizationException::class);

        app(NajmHodaRuntimeAuthorityFactory::class)->apply(
            $stranger,
            'set_ticket_needs_review',
            ['ticket_id' => $ticket->id],
            'mobile_api',
            'consent-evidence-1',
        );
    }

    public function test_protected_action_without_explicit_resource_rule_fails_closed(): void
    {
        $actor = User::factory()->create();

        $result = app(NajmHodaResourceAuthorizationService::class)->authorize(
            $actor->id,
            'protected_unknown_action',
            ['resource_id' => 123],
        );

        $this->assertFalse($result['allowed']);
        $this->assertSame('resource_rule_missing', $result['reason']);
    }

    public function test_disabled_capability_cannot_mint_authority(): void
    {
        $actor = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        app(NajmHodaRuntimeAuthorityFactory::class)->propose($actor, 'disabled_action', [], 'mobile_api');
    }

    public function test_apply_requires_server_owned_consent_evidence_identifier(): void
    {
        $owner = User::factory()->create();
        $ticket = $this->ticketFor($owner);

        $this->expectException(InvalidArgumentException::class);
        app(NajmHodaRuntimeAuthorityFactory::class)->apply(
            $owner,
            'set_ticket_needs_review',
            ['ticket_id' => $ticket->id],
            'mobile_api',
            '',
        );
    }

    private function ticketFor(User $owner): Ticket
    {
        return Ticket::query()->create([
            'user_id' => $owner->id,
            'tracking_code' => 'TK-'.strtoupper(bin2hex(random_bytes(4))),
            'subject' => 'Najm Hoda authority test',
            'message' => 'Protected resource boundary.',
            'status' => 'open',
            'email' => $owner->email,
        ]);
    }
}
