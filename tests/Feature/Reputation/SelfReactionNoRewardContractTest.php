<?php

namespace Tests\Feature\Reputation;

use Tests\TestCase;

class SelfReactionNoRewardContractTest extends TestCase
{
    public function test_self_like_remains_allowed_but_is_forced_non_convertible(): void
    {
        $reaction = file_get_contents(app_path('Http/Controllers/Group/ReactionController.php'));
        $service = file_get_contents(app_path('Services/ReputationService.php'));

        $this->assertGreaterThanOrEqual(2, substr_count($reaction, '$selfLike = $owner && (int) $owner->id === $reactorId;'));
        $this->assertGreaterThanOrEqual(4, substr_count($reaction, '$selfLike ? false : null'));
        $this->assertStringContainsString('?bool $convertibleOverride = null', $service);
        $this->assertStringContainsString('$convertible = $convertibleOverride ?? $convertible;', $service);
    }
}
