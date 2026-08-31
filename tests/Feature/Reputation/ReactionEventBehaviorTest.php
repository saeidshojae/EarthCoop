<?php

namespace Tests\Feature\Reputation;

use App\Models\ReputationRule;
use App\Models\User;
use App\Models\UserPointTransaction;
use App\Services\ReputationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReactionEventBehaviorTest extends TestCase
{
    use RefreshDatabase;

    public function test_like_unlike_like_cannot_reward_the_same_reactor_or_owner_event_twice(): void
    {
        $reactor = User::factory()->create();
        $owner = User::factory()->create();
        $postId = 501;

        $this->createRule('post_liked', 1);
        $this->createRule('post_upvoted', 5);

        $service = app(ReputationService::class);
        $reactorKey = 'post_liked:' . $postId . ':reactor:' . $reactor->id;
        $ownerKey = 'post_upvoted:' . $postId . ':reactor:' . $reactor->id;

        // First like.
        $service->applyAction($reactor, 'post_liked', [], $postId, 'groups', $reactorKey);
        $service->applyAction($owner, 'post_upvoted', [], $postId, 'groups', $ownerKey);

        // Unlike creates no positive reputation event. Liking the same post again must reuse
        // the same stable business-event keys and therefore cannot award a second time.
        $service->applyAction($reactor, 'post_liked', [], $postId, 'groups', $reactorKey);
        $service->applyAction($owner, 'post_upvoted', [], $postId, 'groups', $ownerKey);

        $this->assertSame(1, UserPointTransaction::where('event_key', $reactorKey)->count());
        $this->assertSame(1, UserPointTransaction::where('event_key', $ownerKey)->count());
        $this->assertSame(1, (int) UserPointTransaction::where('event_key', $reactorKey)->sum('delta'));
        $this->assertSame(5, (int) UserPointTransaction::where('event_key', $ownerKey)->sum('delta'));
    }

    public function test_distinct_reactors_on_the_same_content_remain_independent_reward_events(): void
    {
        $reactorA = User::factory()->create();
        $reactorB = User::factory()->create();
        $owner = User::factory()->create();
        $postId = 502;

        $this->createRule('post_liked', 1);
        $this->createRule('post_upvoted', 5);

        $service = app(ReputationService::class);

        foreach ([$reactorA, $reactorB] as $reactor) {
            $service->applyAction(
                $reactor,
                'post_liked',
                [],
                $postId,
                'groups',
                'post_liked:' . $postId . ':reactor:' . $reactor->id
            );
            $service->applyAction(
                $owner,
                'post_upvoted',
                [],
                $postId,
                'groups',
                'post_upvoted:' . $postId . ':reactor:' . $reactor->id
            );
        }

        $this->assertSame(2, UserPointTransaction::where('action', 'post_liked')->count());
        $this->assertSame(2, UserPointTransaction::where('user_id', $owner->id)->where('action', 'post_upvoted')->count());
        $this->assertSame(10, (int) UserPointTransaction::where('user_id', $owner->id)->where('action', 'post_upvoted')->sum('delta'));
    }

    private function createRule(string $key, int $weight): void
    {
        ReputationRule::create([
            'key' => $key,
            'label' => $key,
            'weight' => $weight,
            'active' => true,
            'daily_cap' => null,
            'dimension' => 'participation',
            'convertible' => true,
            'repeat_policy' => 'once_per_context',
        ]);
    }
}
