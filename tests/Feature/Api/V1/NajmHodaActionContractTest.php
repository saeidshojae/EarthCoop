<?php

namespace Tests\Feature\Api\V1;

use App\Models\Conversation;
use App\Models\Ticket;
use App\Models\User;
use App\Services\NajmHoda\Runtime\NajmHodaCrossModuleCapabilityOrchestratorService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class NajmHodaActionContractTest extends TestCase
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
                'output' => ['ticket_id', 'status'],
            ],
        ]);
    }

    public function test_proposal_is_durable_and_does_not_execute(): void
    {
        [$actor, $token, $deviceId] = $this->nativeSession();
        $ticket = $this->ticketFor($actor);
        $conversation = Conversation::create([
            'user_id' => $actor->id,
            'title' => 'Lifecycle',
            'agent_type' => 'steward',
            'status' => 'active',
        ]);

        $runtime = Mockery::mock(NajmHodaCrossModuleCapabilityOrchestratorService::class);
        $runtime->shouldNotReceive('orchestrate');
        $this->app->instance(NajmHodaCrossModuleCapabilityOrchestratorService::class, $runtime);

        $response = $this->bearer($token, $deviceId)
            ->postJson('/api/v1/najm-hoda/actions/proposals', [
                'conversation_id' => $conversation->id,
                'action' => 'set_ticket_needs_review',
                'input' => ['ticket_id' => $ticket->id],
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'proposed')
            ->assertJsonPath('data.action', 'set_ticket_needs_review')
            ->assertJsonMissingPath('data.runtime_action_authority');

        $publicId = (string) $response->json('data.id');
        $this->assertNotSame('', $publicId);
        $this->assertDatabaseHas('najm_hoda_actions', [
            'public_id' => $publicId,
            'user_id' => $actor->id,
            'conversation_id' => $conversation->id,
            'status' => 'proposed',
        ]);
    }

    public function test_consent_is_owner_scoped_and_transport_idempotent(): void
    {
        [$owner, $ownerToken, $ownerDevice] = $this->nativeSession('owner');
        [$other, $otherToken, $otherDevice] = $this->nativeSession('other');
        $ticket = $this->ticketFor($owner);
        $publicId = $this->createProposal($ownerToken, $ownerDevice, $ticket->id);

        $this->bearer($otherToken, $otherDevice)
            ->withHeader('Idempotency-Key', 'consent-other-0001')
            ->postJson('/api/v1/najm-hoda/actions/'.$publicId.'/consent')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found');

        $first = $this->bearer($ownerToken, $ownerDevice)
            ->withHeader('Idempotency-Key', 'consent-owner-0001')
            ->postJson('/api/v1/najm-hoda/actions/'.$publicId.'/consent', ['client_nonce' => 'same'])
            ->assertOk()
            ->assertJsonPath('data.status', 'consented')
            ->assertJsonStructure(['data' => ['consent_evidence_id']]);

        $evidence = (string) $first->json('data.consent_evidence_id');

        $replay = $this->bearer($ownerToken, $ownerDevice)
            ->withHeader('Idempotency-Key', 'consent-owner-0001')
            ->postJson('/api/v1/najm-hoda/actions/'.$publicId.'/consent', ['client_nonce' => 'same'])
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.consent_evidence_id', $evidence);

        $this->bearer($ownerToken, $ownerDevice)
            ->withHeader('Idempotency-Key', 'consent-owner-0001')
            ->postJson('/api/v1/najm-hoda/actions/'.$publicId.'/consent', ['client_nonce' => 'changed'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'idempotency_key_reused');
    }

    public function test_apply_requires_consent(): void
    {
        [$owner, $token, $deviceId] = $this->nativeSession();
        $ticket = $this->ticketFor($owner);
        $publicId = $this->createProposal($token, $deviceId, $ticket->id);

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'apply-no-consent-0001')
            ->postJson('/api/v1/najm-hoda/actions/'.$publicId.'/apply')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'consent_required');
    }

    public function test_apply_executes_once_and_durable_evidence_survives_cache_clear(): void
    {
        [$owner, $token, $deviceId] = $this->nativeSession();
        $ticket = $this->ticketFor($owner);
        $publicId = $this->createProposal($token, $deviceId, $ticket->id);

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'consent-apply-0001')
            ->postJson('/api/v1/najm-hoda/actions/'.$publicId.'/consent')
            ->assertOk();

        $runtime = Mockery::mock(NajmHodaCrossModuleCapabilityOrchestratorService::class);
        $runtime->shouldReceive('orchestrate')
            ->once()
            ->withArgs(function (array $chain, array $goals, bool $apply, ?int $actorId) use ($owner, $ticket): bool {
                $this->assertTrue($apply);
                $this->assertSame($owner->id, $actorId);
                $this->assertSame('set_ticket_needs_review', data_get($chain, '0.action'));
                $this->assertSame($ticket->id, data_get($chain, '0.input.ticket_id'));

                return true;
            })
            ->andReturn([
                'executed' => true,
                'status' => 'completed',
                'run_id' => 'run-mobile-1',
                'steps' => [[
                    'step' => 1,
                    'action' => 'set_ticket_needs_review',
                    'status' => 'executed',
                    'reason' => '',
                ]],
                'rollback' => [],
            ]);
        $this->app->instance(NajmHodaCrossModuleCapabilityOrchestratorService::class, $runtime);

        $first = $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'apply-owner-0001')
            ->postJson('/api/v1/najm-hoda/actions/'.$publicId.'/apply')
            ->assertOk()
            ->assertJsonPath('data.status', 'applied')
            ->assertJsonPath('data.runtime_run_id', 'run-mobile-1')
            ->assertJsonStructure(['data' => ['evidence_id']]);

        $evidenceId = (string) $first->json('data.evidence_id');

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'apply-owner-0001')
            ->postJson('/api/v1/najm-hoda/actions/'.$publicId.'/apply')
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.evidence_id', $evidenceId);

        Cache::flush();

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-hoda/actions/'.$publicId.'/evidence')
            ->assertOk()
            ->assertJsonPath('data.action_id', $publicId)
            ->assertJsonPath('data.evidence_id', $evidenceId)
            ->assertJsonPath('data.status', 'applied')
            ->assertJsonPath('data.runtime_run_id', 'run-mobile-1');
    }

    public function test_resource_that_becomes_inaccessible_is_durably_blocked_without_execution(): void
    {
        [$owner, $token, $deviceId] = $this->nativeSession();
        $ticket = $this->ticketFor($owner);
        $publicId = $this->createProposal($token, $deviceId, $ticket->id);

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'consent-blocked-0001')
            ->postJson('/api/v1/najm-hoda/actions/'.$publicId.'/consent')
            ->assertOk();

        $newOwner = User::factory()->create();
        $ticket->update([
            'user_id' => $newOwner->id,
            'email' => $newOwner->email,
            'assignee_id' => null,
        ]);

        $runtime = Mockery::mock(NajmHodaCrossModuleCapabilityOrchestratorService::class);
        $runtime->shouldNotReceive('orchestrate');
        $this->app->instance(NajmHodaCrossModuleCapabilityOrchestratorService::class, $runtime);

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'apply-blocked-0001')
            ->postJson('/api/v1/najm-hoda/actions/'.$publicId.'/apply')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'resource_not_accessible');

        $this->assertDatabaseHas('najm_hoda_actions', [
            'public_id' => $publicId,
            'status' => 'blocked',
            'error_code' => 'resource_not_accessible',
        ]);
    }

    private function createProposal(string $token, string $deviceId, int $ticketId): string
    {
        return (string) $this->bearer($token, $deviceId)
            ->postJson('/api/v1/najm-hoda/actions/proposals', [
                'action' => 'set_ticket_needs_review',
                'input' => ['ticket_id' => $ticketId],
            ])
            ->assertCreated()
            ->json('data.id');
    }

    private function nativeSession(string $label = 'member'): array
    {
        $password = 'secret-password';
        $user = User::factory()->create([
            'email' => 'hoda-action-'.$label.'-'.bin2hex(random_bytes(4)).'@example.test',
            'password' => Hash::make($password),
            'is_system' => false,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $login = $this->postJson('/api/v1/auth/session', [
            'email' => $user->email,
            'password' => $password,
            'platform' => 'android',
            'app_version' => '1.0.0',
            'locale' => 'fa',
            'timezone' => 'Asia/Tehran',
            'push_capable' => false,
        ])->assertCreated();

        return [$user, (string) $login->json('data.token'), (string) $login->json('data.device.id')];
    }

    private function bearer(string $token, string $deviceId): self
    {
        Auth::forgetGuards();

        return $this->withToken($token)->withHeader('X-Device-ID', $deviceId);
    }

    private function ticketFor(User $owner): Ticket
    {
        return Ticket::query()->create([
            'user_id' => $owner->id,
            'tracking_code' => 'TK-'.strtoupper(bin2hex(random_bytes(4))),
            'subject' => 'Najm Hoda action lifecycle',
            'message' => 'Durable action test.',
            'status' => 'open',
            'email' => $owner->email,
        ]);
    }
}
