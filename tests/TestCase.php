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
    }
}
