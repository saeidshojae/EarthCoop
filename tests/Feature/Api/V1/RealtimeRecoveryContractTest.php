<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealtimeRecoveryContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'sync', 'broadcasting.default' => 'null']);
    }

    public function test_notification_cursor_recovers_after_gap_without_duplication_when_new_rows_arrive(): void
    {
        $user = $this->member('cursor');
        [$token, $deviceId] = $this->nativeSession($user);

        $user->notifyNow(new GenericNotification('one', 'one'));
        $firstId = (string) $user->notifications()->latest()->value('id');
        usleep(1000);
        $user->notifyNow(new GenericNotification('two', 'two'));
        usleep(1000);
        $user->notifyNow(new GenericNotification('three', 'three'));

        $first = $this->freshBearer($token, $deviceId)
            ->getJson('/api/v1/notifications?page[limit]=2')
            ->assertOk()
            ->assertJsonPath('meta.pagination.has_more', true);

        $cursor = $first->json('meta.pagination.next_cursor');
        $firstPageIds = collect($first->json('data'))->pluck('id')->all();
        $this->assertCount(2, $firstPageIds);
        $this->assertNotEmpty($cursor);

        usleep(1000);
        $user->notifyNow(new GenericNotification('four', 'four'));

        $second = $this->freshBearer($token, $deviceId)
            ->getJson('/api/v1/notifications?page[limit]=2&page[cursor]='.urlencode((string) $cursor))
            ->assertOk()
            ->assertJsonPath('meta.pagination.has_more', false);

        $secondPageIds = collect($second->json('data'))->pluck('id')->all();
        $this->assertSame([$firstId], $secondPageIds);
        $this->assertSame([], array_values(array_intersect($firstPageIds, $secondPageIds)));
    }

    public function test_invalid_notification_cursor_returns_stable_422_error(): void
    {
        $user = $this->member('bad-cursor');
        [$token, $deviceId] = $this->nativeSession($user);

        $this->freshBearer($token, $deviceId)
            ->getJson('/api/v1/notifications?page[cursor]=not-a-valid-cursor')
            ->assertStatus(422);
    }

    public function test_broadcast_payload_has_stable_event_identity_and_recovery_metadata(): void
    {
        $notification = new GenericNotification('Title', 'Body', '/home', 'info');
        $notification->id = '11111111-1111-4111-8111-111111111111';

        $payload = $notification->toBroadcast($this->member('broadcast'))->data;

        $this->assertSame('11111111-1111-4111-8111-111111111111', $payload['event_id']);
        $this->assertSame('notifications', $payload['stream']);
        $this->assertSame('notification.created', $payload['event_type']);
        $this->assertNotEmpty($payload['occurred_at']);
        $this->assertNotEmpty($payload['cursor']);
    }

    private function member(string $suffix): User
    {
        return User::factory()->create([
            'email' => 'm5-realtime-'.$suffix.'-'.bin2hex(random_bytes(4)).'@example.test',
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
