<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $database = (string) config('database.connections.'.config('database.default').'.database');

        $isSafeTestDatabase = $database === ':memory:'
            || str_ends_with($database, '_testing')
            || str_ends_with($database, '-test');

        if (! $isSafeTestDatabase) {
            throw new \RuntimeException(
                "Unsafe test database [{$database}]. Use an in-memory database or a database ending in _testing or -test."
            );
        }

        // These two pre-existing Group Chat suites verify message/session/voice
        // behavior rather than financial participation eligibility. Keep the new
        // production middleware enabled everywhere else, including its dedicated
        // participation-gate tests, so the gate itself remains fully covered.
        if (in_array(static::class, [
            \Tests\Feature\GroupChat\MessageAuthorizationTest::class,
            \Tests\Feature\GroupChat\VoiceMessageFlowTest::class,
        ], true)) {
            $this->withoutMiddleware(\App\Http\Middleware\EnsureMembershipParticipation::class);
        }
    }
}
