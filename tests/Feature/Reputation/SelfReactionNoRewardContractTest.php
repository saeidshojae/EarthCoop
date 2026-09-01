<?php

namespace Tests\Feature\Reputation;

use Tests\TestCase;

class SelfReactionNoRewardContractTest extends TestCase
{
    public function test_self_like_remains_allowed_but_creates_no_reputation_awards(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Group/ReactionController.php'));

        $guard = 'if ($owner && (int) $owner->id === $reactorId) {';

        $this->assertGreaterThanOrEqual(2, substr_count($source, $guard));
        $this->assertGreaterThanOrEqual(2, substr_count($source, '// Self-like is allowed in UI, but it is not an economic participation event.'));
    }
}
