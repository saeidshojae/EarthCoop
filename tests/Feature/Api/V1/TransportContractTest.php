<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TransportContractTest extends TestCase
{
    public function test_v1_route_surface_is_isolated_and_versioned(): void
    {
        $route = Route::getRoutes()->getByName('api.v1.health');

        $this->assertNotNull($route, 'The api.v1.health route must be registered.');
        $this->assertSame('api/v1/health', $route->uri());

        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'api_version' => 'v1',
            ]);

        $legacyApiUser = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($candidate) => $candidate->uri() === 'api/user');

        $this->assertNotNull($legacyApiUser, 'The existing legacy /api/user route must remain registered.');

        $loginRoute = Route::getRoutes()->getByName('login');
        $this->assertNotNull($loginRoute, 'The existing web login route must remain registered.');
        $this->assertSame('login', $loginRoute->uri());

        $this->assertNull(
            collect(Route::getRoutes()->getRoutes())
                ->first(fn ($candidate) => $candidate->uri() === 'api/v1/login'),
            'M1 must not move the existing Product/UX login route into /api/v1.'
        );
    }
}
