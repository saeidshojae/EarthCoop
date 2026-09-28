<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Services\NotificationService;
use App\Support\Notifications\NotificationLink;
use App\Support\Notifications\NotificationLinkRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

class NotificationDeliveryContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'sync', 'broadcasting.default' => 'null']);
    }

    public function test_typed_link_is_stored_and_projected_without_breaking_legacy_url(): void
    {
        $user = $this->member('typed-link');
        [$token, $deviceId] = $this->nativeSession($user);

        app(NotificationService::class)->notifyUser(
            $user,
            'Project updated',
            'Open the project for details.',
            '/projects/123',
            'project.status_changed',
            ['source' => 'm5'],
            new NotificationLink(1, 'project.detail', ['project_id' => '123'], 'https://earthcoop.ir/projects/123'),
        );

        $stored = $user->notifications()->latest()->firstOrFail();
        $this->assertSame('/projects/123', $stored->data['url']);
        $this->assertSame('project.detail', $stored->data['link']['route']);
        $this->assertSame('123', $stored->data['link']['params']['project_id']);

        $this->freshBearer($token, $deviceId)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.url', '/projects/123')
            ->assertJsonPath('data.0.link.route', 'project.detail')
            ->assertJsonPath('data.0.link.params.project_id', '123');
    }

    public function test_existing_legacy_url_notification_remains_supported(): void
    {
        $user = $this->member('legacy-url');

        app(NotificationService::class)->notifyUser(
            $user,
            'Legacy',
            'Still works',
            '/home',
            'info',
            ['legacy' => true],
        );

        $stored = $user->notifications()->latest()->firstOrFail();
        $this->assertSame('/home', $stored->data['url']);
        $this->assertArrayNotHasKey('link', $stored->data);
    }

    public function test_registry_rejects_unrecognized_routes_and_keeps_params_as_data(): void
    {
        $registry = app(NotificationLinkRegistry::class);

        $safe = new NotificationLink(1, 'group.thread', [
            'group_id' => '10',
            'item_id' => '../../admin',
        ], 'https://earthcoop.ir/groups/10');
        $registry->validate($safe);
        $this->assertSame('../../admin', $safe->params['item_id']);

        $this->expectException(InvalidArgumentException::class);
        $registry->validate(new NotificationLink(1, 'arbitrary.controller.action', [], null));
    }

    private function member(string $suffix): User
    {
        return User::factory()->create([
            'email' => 'm5-notification-'.$suffix.'-'.bin2hex(random_bytes(4)).'@example.test',
            'password' => Hash::make('secret-password'),
            'is_system' => false,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function nativeSession(User $user): array
    {
        $response = $this->postJson('/api/v1/auth/session', [
            'email' => $user->email,
            'password' => 'secret-password',
            'platform' => 'android',
            'app_version' => '1.0.0',
            'locale' => 'fa',
            'timezone' => 'Asia/Tehran',
            'push_capable' => true,
        ])->assertCreated();

        return [$response->json('data.token'), $response->json('data.device.id')];
    }

    private function freshBearer(string $token, string $deviceId): self
    {
        Auth::forgetGuards();

        return $this->withToken($token)->withHeader('X-Device-ID', $deviceId);
    }
}
