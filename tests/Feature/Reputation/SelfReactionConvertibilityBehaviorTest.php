<?php

namespace Tests\Feature\Reputation;

use App\Models\ReputationRule;
use App\Models\User;
use App\Models\UserPointTransaction;
use App\Services\ReputationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SelfReactionConvertibilityBehaviorTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_level_override_can_keep_reputation_but_force_it_non_convertible(): void
    {
        $user = User::factory()->create();
        ReputationRule::create([
            'key' => 'post_liked',
            'label' => 'post liked',
            'weight' => 1,
            'active' => true,
            'daily_cap' => null,
            'dimension' => 'participation',
            'convertible' => true,
            'repeat_policy' => 'once_per_context',
        ]);

        app(ReputationService::class)->applyAction(
            $user,
            'post_liked',
            ['self_like' => true],
            501,
            'groups',
            'post_liked:501:reactor:' . $user->id,
            false
        );

        $transaction = UserPointTransaction::where('event_key', 'post_liked:501:reactor:' . $user->id)->firstOrFail();
        $this->assertSame(1, (int) $transaction->delta);
        $this->assertFalse((bool) $transaction->convertible);
        $this->assertSame('participation', $transaction->dimension);
    }
}
