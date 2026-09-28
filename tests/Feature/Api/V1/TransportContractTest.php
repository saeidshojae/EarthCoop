<?php

namespace Tests\Feature\Api\V1;

use App\Services\Actors\ActorBoundaryException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class TransportContractTest extends TestCase
{
    public function test_v1_route_surface_is_isolated_and_versioned(): void
    {
        $route = Route::getRoutes()->getByName('api.v1.health');

        $this->assertNotNull($route, 'The api.v1.health route must be registered.');
        $this->assertSame('api/v1/health', $route->uri());

        $this->getJson('/api/v1/health')
            ->assertOk();

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

    public function test_v1_success_response_has_request_id_locale_and_envelope(): void
    {
        $requestId = '11111111-1111-4111-8111-111111111111';

        $response = $this->withHeaders([
            'X-Request-ID' => $requestId,
            'Accept-Language' => 'en',
            'X-Timezone' => 'Asia/Tehran',
        ])->getJson('/api/v1/health');

        $response
            ->assertOk()
            ->assertHeader('X-Request-ID', $requestId)
            ->assertHeader('Content-Language', 'en')
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'status' => 'ok',
                    'api_version' => 'v1',
                ],
                'error' => null,
                'meta' => [
                    'api_version' => 'v1',
                ],
                'request_id' => $requestId,
            ]);
    }

    public function test_v1_generates_request_id_and_falls_back_to_fa_for_unsupported_locale(): void
    {
        $response = $this->withHeaders([
            'Accept-Language' => 'de-DE,de;q=0.9',
        ])->getJson('/api/v1/health');

        $response
            ->assertOk()
            ->assertHeader('Content-Language', 'fa');

        $requestId = $response->json('request_id');

        $this->assertIsString($requestId);
        $this->assertTrue(Str::isUuid($requestId));
        $response->assertHeader('X-Request-ID', $requestId);
    }

    public function test_v1_validation_exception_uses_stable_error_envelope(): void
    {
        Route::get('api/v1/_contract/validation', function () {
            throw ValidationException::withMessages([
                'name' => ['The name field is required.'],
            ]);
        });

        $this->getJson('/api/v1/_contract/validation')
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('data', null)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.retryable', false)
            ->assertJsonPath('meta.api_version', 'v1')
            ->assertJsonPath('meta.http_status', 422)
            ->assertJsonStructure([
                'error' => ['message', 'details'],
                'request_id',
            ]);
    }

    public function test_v1_actor_boundary_exception_preserves_stable_actor_code_and_envelope(): void
    {
        $requestId = '22222222-2222-4222-8222-222222222222';

        Route::get('api/v1/_contract/actor', function () {
            throw ActorBoundaryException::invalidReference();
        });

        $this->withHeaders([
            'X-Request-ID' => $requestId,
            'Accept-Language' => 'en',
        ])->getJson('/api/v1/_contract/actor')
            ->assertStatus(422)
            ->assertHeader('X-Request-ID', $requestId)
            ->assertHeader('Content-Language', 'en')
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('data', null)
            ->assertJsonPath('error.code', 'actor_reference_invalid')
            ->assertJsonPath('error.retryable', false)
            ->assertJsonPath('meta.api_version', 'v1')
            ->assertJsonPath('meta.http_status', 422)
            ->assertJsonPath('request_id', $requestId);
    }

    public function test_v1_authentication_not_found_and_server_exceptions_are_json_and_do_not_leak_details(): void
    {
        Route::get('api/v1/_contract/auth', function () {
            throw new AuthenticationException('sensitive-auth-detail');
        });

        Route::get('api/v1/_contract/missing', function () {
            throw new ModelNotFoundException();
        });

        Route::get('api/v1/_contract/server', function () {
            throw new RuntimeException('secret-server-detail');
        });

        $auth = $this->getJson('/api/v1/_contract/auth')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthenticated')
            ->assertJsonPath('meta.api_version', 'v1');

        $this->assertStringNotContainsString('sensitive-auth-detail', $auth->getContent());

        $this->getJson('/api/v1/_contract/missing')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found')
            ->assertJsonPath('meta.api_version', 'v1');

        $server = $this->getJson('/api/v1/_contract/server')
            ->assertStatus(500)
            ->assertJsonPath('error.code', 'server_error')
            ->assertJsonPath('error.retryable', true)
            ->assertJsonPath('meta.api_version', 'v1');

        $this->assertStringNotContainsString('secret-server-detail', $server->getContent());
        $this->assertStringNotContainsString(RuntimeException::class, $server->getContent());
    }
}
