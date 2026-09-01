<?php

namespace Tests\Feature\Reputation;

use App\Models\ReputationRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyElectionRuleDeprecationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_candidate_and_generic_election_participation_rules_are_preserved_but_deactivated(): void
    {
        foreach (['election_candidate', 'election_participated'] as $key) {
            ReputationRule::create([
                'key' => $key,
                'label' => $key,
                'weight' => 10,
                'active' => true,
                'dimension' => 'participation',
                'convertible' => true,
                'repeat_policy' => 'repeatable',
            ]);
        }

        $migration = require database_path('migrations/2026_09_01_150500_deprecate_legacy_election_reputation_rules.php');
        $migration->up();

        foreach (['election_candidate', 'election_participated'] as $key) {
            $rule = ReputationRule::where('key', $key)->first();

            $this->assertNotNull($rule, 'Legacy audit row must be preserved.');
            $this->assertFalse($rule->active);
            $this->assertFalse($rule->convertible);
            $this->assertStringContainsString('منسوخ', (string) $rule->description);
            $this->assertNull(config('reputation.weights.' . $key));
        }
    }
}
