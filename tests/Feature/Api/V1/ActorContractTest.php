<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class ActorContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_discovery_returns_self_then_only_current_manager_groups(): void
    {
        $principal = $this->member('principal');
        $other = $this->member('other');
        [$token, $deviceId] = $this->nativeSession($principal);

        $managedA = $this->group('managed-a');
        $managedB = $this->group('managed-b');
        $this->membership($principal, $managedA, 3);
        $this->membership($principal, $managedB, 3);

        foreach ([0, 1, 2, 4, 5] as $role) {
            $group = $this->group('role-'.$role);
            $this->membership($principal, $group, $role);
        }

        $inactive = $this->group('inactive-manager');
        $this->membership($principal, $inactive, 3, 0);
        $expired = $this->group('expired-manager');
        $this->membership($principal, $expired, 3, 1, now()->subMinute());
        $otherManaged = $this->group('other-manager');
        $this->membership($other, $otherManaged, 3);

        $response = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/actors')
            ->assertOk()
            ->assertJsonPath('data.items.0.type', 'user')
            ->assertJsonPath('data.items.0.id', (string) $principal->id)
            ->assertJsonPath('data.items.0.permissions.can_represent', true);

        $items = collect($response->json('data.items'));
        $groupItems = $items->where('type', 'group')->values();

        $this->assertSame(
            collect([$managedA->id, $managedB->id])->sort()->map(fn ($id) => (string) $id)->values()->all(),
            $groupItems->pluck('id')->all(),
        );
        $this->assertTrue($groupItems->every(fn (array $item): bool => ($item['permissions']['can_represent'] ?? null) === true));
        $this->assertFalse($items->contains(fn (array $item): bool => in_array($item['type'] ?? null, ['system', 'organization'], true)));
        $this->assertFalse($items->contains(fn (array $item): bool => ($item['id'] ?? null) === (string) $otherManaged->id));
    }

    public function test_discovery_rechecks_live_authority_after_manager_downgrade_without_token_rotation(): void
    {
        $principal = $this->member('stale');
        [$token, $deviceId] = $this->nativeSession($principal);
        $group = $this->group('live-manager');
        $membership = $this->membership($principal, $group, 3);

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/actors')
            ->assertOk()
            ->assertJsonFragment(['type' => 'group', 'id' => (string) $group->id]);

        $membership->update(['role' => 1]);

        $response = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/actors')
            ->assertOk();

        $this->assertFalse(collect($response->json('data.items'))->contains(
            fn (array $item): bool => ($item['type'] ?? null) === 'group' && ($item['id'] ?? null) === (string) $group->id,
        ));
    }

    public function test_discovery_is_native_authenticated_and_does_not_create_global_actor_state(): void
    {
        $this->getJson('/api/v1/actors')->assertUnauthorized();

        $principal = $this->member('auth');
        [$token, $deviceId] = $this->nativeSession($principal);

        $first = $this->bearer($token, $deviceId)->getJson('/api/v1/actors')->assertOk();
        $second = $this->bearer($token, $deviceId)->getJson('/api/v1/actors')->assertOk();

        $this->assertSame($first->json('data.items'), $second->json('data.items'));
        $this->assertSame('user', $second->json('data.items.0.type'));
        $this->assertSame((string) $principal->id, $second->json('data.items.0.id'));
    }

    private function member(string $suffix): User
    {
        return User::factory()->create([
            'email' => 'm4-actor-'.$suffix.'-'.bin2hex(random_bytes(4)).'@example.test',
            'password' => Hash::make('secret-password'),
            'is_system' => false,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function group(string $suffix): Group
    {
        return Group::create([
            'name' => 'M4 actor '.$suffix,
            'group_type' => 0,
            'is_open' => 1,
        ]);
    }

    private function membership(User $user, Group $group, int $role, int $status = 1, $expired = null): GroupUser
    {
        return GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => $status,
            'expired' => $expired,
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

        return [(string) $response->json('data.token'), (string) $response->json('data.device.id')];
    }

    private function bearer(string $token, string $deviceId): self
    {
        Auth::forgetGuards();

        return $this->withToken($token)->withHeader('X-Device-ID', $deviceId);
    }
}
