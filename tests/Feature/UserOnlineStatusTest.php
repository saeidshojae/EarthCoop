<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UserOnlineStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_last_seen_is_cast_to_carbon_and_recent_authenticated_user_is_online(): void
    {
        $user = User::factory()->create([
            'last_seen' => now()->subMinute(),
        ]);

        $this->actingAs($user);
        $user->refresh();

        $this->assertInstanceOf(Carbon::class, $user->last_seen);
        $this->assertTrue($user->isOnline());
    }

    public function test_user_with_old_last_seen_is_offline(): void
    {
        $user = User::factory()->create([
            'last_seen' => now()->subMinutes(10),
        ]);

        $this->actingAs($user);
        $user->refresh();

        $this->assertFalse($user->isOnline());
    }
}
