<?php

namespace Tests\Feature\Reputation;

use App\Models\ReputationRule;
use App\Models\User;
use App\Models\UserPointTransaction;
use App\Services\ReputationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipationEventIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_stable_event_key_cannot_award_points_twice(): void
    {
        $user = User::factory()->create();

        ReputationRule::create([
            'key' => 'idempotent_participation',
            'label' => 'Idempotent participation',
            'weight' => 7,
            'active' => true,
            'daily_cap' => null,
            'dimension' => 'participation',
            'convertible' => true,
            'repeat_policy' => 'once_per_context',
        ]);

        $service = app(ReputationService::class);
        $eventKey = 'idempotent_participation:user:' . $user->id . ':context:42';

        $first = $service->applyAction($user, 'idempotent_participation', [], 42, 'unit.event', $eventKey);
        $second = $service->applyAction($user, 'idempotent_participation', [], 42, 'unit.event', $eventKey);

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertSame(1, UserPointTransaction::where('event_key', $eventKey)->count());
        $this->assertSame(7, (int) UserPointTransaction::where('event_key', $eventKey)->sum('delta'));
    }

    public function test_different_event_keys_remain_independently_rewardable(): void
    {
        $user = User::factory()->create();

        ReputationRule::create([
            'key' => 'repeatable_participation',
            'label' => 'Repeatable participation',
            'weight' => 3,
            'active' => true,
            'daily_cap' => null,
            'dimension' => 'participation',
            'convertible' => true,
            'repeat_policy' => 'repeatable',
        ]);

        $service = app(ReputationService::class);

        $service->applyAction($user, 'repeatable_participation', [], 101, 'unit.event', 'repeatable:101');
        $service->applyAction($user, 'repeatable_participation', [], 102, 'unit.event', 'repeatable:102');

        $this->assertSame(2, UserPointTransaction::where('user_id', $user->id)
            ->where('action', 'repeatable_participation')
            ->count());
        $this->assertSame(6, (int) UserPointTransaction::where('user_id', $user->id)
            ->where('action', 'repeatable_participation')
            ->sum('delta'));
    }
}
