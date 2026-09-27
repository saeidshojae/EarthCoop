<?php

namespace Tests\Feature\Api\V1;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NajmHodaProposalAuthorizationContractTest extends TestCase
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

    public function test_protected_resource_proposal_fails_closed_for_non_owner_without_persisting_action(): void
    {
        [$stranger, $token, $deviceId] = $this->nativeSession();
        $owner = User::factory()->create();
        $ticket = Ticket::query()->create([
            'user_id' => $owner->id,
            'tracking_code' => 'TK-'.strtoupper(bin2hex(random_bytes(4))),
            'subject' => 'Foreign protected resource',
            'message' => 'Must not become a proposal for another actor.',
            'status' => 'open',
            'email' => $owner->email,
        ]);

        $this->bearer($token, $deviceId)
            ->postJson('/api/v1/najm-hoda/actions/proposals', [
                'action' => 'set_ticket_needs_review',
                'input' => ['ticket_id' => $ticket->id],
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'resource_not_accessible');

        $this->assertDatabaseMissing('najm_hoda_actions', [
            'user_id' => $stranger->id,
            'action' => 'set_ticket_needs_review',
        ]);
    }

    private function nativeSession(): array
    {
        $password = 'secret-password';
        $user = User::factory()->create([
            'email' => 'hoda-proposal-auth-'.bin2hex(random_bytes(4)).'@example.test',
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
}
