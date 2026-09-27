<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Services\NajmHoda\Api\NajmHodaCapabilityQueryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NajmHodaCapabilityContractTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'najm-hoda.runtime.autonomy.capabilities.run_ops_monitor' => [
                'enabled' => true,
                'version' => 2,
                'risk' => 'low',
                'mode' => 'propose',
                'human_approval_required' => false,
                'required_input' => ['health_status'],
                'optional_input' => ['error_rate_percent'],
                'output' => ['plan_ref'],
            ],
            'najm-hoda.runtime.autonomy.capabilities.disabled_test_action' => [
                'enabled' => false,
                'version' => 1,
                'risk' => 'medium',
                'mode' => 'propose',
                'human_approval_required' => true,
                'required_input' => [],
                'optional_input' => [],
                'output' => [],
            ],
        ]);
    }

    public function test_query_service_projects_registry_contract_without_authority_internals(): void
    {
        $contract = app(NajmHodaCapabilityQueryService::class)->get('run_ops_monitor');

        $this->assertSame('run_ops_monitor', $contract['action']);
        $this->assertSame(2, $contract['version']);
        $this->assertSame('propose', $contract['default_mode']);
        $this->assertSame(['health_status'], $contract['required_input']);
        $this->assertArrayNotHasKey('runtime_action_authority', $contract);
        $this->assertArrayNotHasKey('executor', $contract);
    }

    public function test_v1_capability_list_and_show_expose_stable_machine_schema(): void
    {
        [, $token, $deviceId] = $this->nativeSession();

        $list = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-hoda/capabilities')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $actions = collect($list->json('data'))->pluck('action');
        $this->assertTrue($actions->contains('run_ops_monitor'));
        $this->assertTrue($actions->contains('disabled_test_action'));

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-hoda/capabilities/run_ops_monitor')
            ->assertOk()
            ->assertJsonPath('data.action', 'run_ops_monitor')
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.risk', 'low')
            ->assertJsonPath('data.default_mode', 'propose')
            ->assertJsonPath('data.enabled', true)
            ->assertJsonMissingPath('data.runtime_action_authority');
    }

    public function test_unknown_action_is_not_found_and_disabled_action_remains_non_executable(): void
    {
        [, $token, $deviceId] = $this->nativeSession();

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-hoda/capabilities/unknown_action')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found');

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-hoda/capabilities/disabled_test_action')
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.human_approval_required', true);
    }

    private function nativeSession(): array
    {
        $password = 'secret-password';
        $user = User::factory()->create([
            'email' => 'hoda-cap-'.bin2hex(random_bytes(4)).'@example.test',
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
