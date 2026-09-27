<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IdempotencyContractTest extends TestCase
{
    private bool $createdIdempotencyTable = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('api_v1_idempotency_keys')) {
            Schema::create('api_v1_idempotency_keys', function (Blueprint $table) {
                $table->id();
                $table->string('actor_key', 128);
                $table->string('scope', 180);
                $table->string('idempotency_key', 100);
                $table->string('request_hash', 64);
                $table->string('state', 20);
                $table->unsignedSmallInteger('response_status')->nullable();
                $table->longText('response_body')->nullable();
                $table->timestamps();
                $table->timestamp('expires_at')->nullable();
                $table->unique(['actor_key', 'scope', 'idempotency_key'], 'api_v1_idem_actor_scope_key_unique');
            });
            $this->createdIdempotencyTable = true;
        }

        app()->instance('api-v1-test-counter', (object) ['count' => 0]);

        Route::post('api/v1/_contract/idempotency', function () {
            $counter = app('api-v1-test-counter');
            $counter->count++;

            return response()->json([
                'mutation_count' => $counter->count,
                'value' => request('value'),
            ], 201);
        })->middleware(['api.v1.context', 'api.v1.envelope', 'api.v1.idempotency'])
            ->name('api.v1.contract.idempotency');

        Route::post('api/v1/_contract/idempotency-server-error', function () {
            $counter = app('api-v1-test-counter');
            $counter->count++;

            return response()->json(['attempt' => $counter->count], 500);
        })->middleware(['api.v1.context', 'api.v1.envelope', 'api.v1.idempotency'])
            ->name('api.v1.contract.idempotency-server-error');

        $user = new User();
        $user->id = 123;
        $this->actingAs($user);
    }

    protected function tearDown(): void
    {
        if ($this->createdIdempotencyTable) {
            Schema::dropIfExists('api_v1_idempotency_keys');
        } elseif (Schema::hasTable('api_v1_idempotency_keys')) {
            DB::table('api_v1_idempotency_keys')
                ->where('actor_key', 'user:123')
                ->whereIn('scope', [
                    'api.v1.contract.idempotency',
                    'api.v1.contract.idempotency-server-error',
                ])
                ->delete();
        }

        parent::tearDown();
    }

    public function test_exact_replay_returns_original_response_without_second_mutation(): void
    {
        $headers = ['Idempotency-Key' => 'idem-replay-0001'];

        $first = $this->withHeaders($headers)->postJson('/api/v1/_contract/idempotency', ['value' => 'alpha']);
        $first->assertCreated()->assertJsonPath('data.mutation_count', 1);

        $second = $this->withHeaders($headers)->postJson('/api/v1/_contract/idempotency', ['value' => 'alpha']);
        $second
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.mutation_count', 1);

        $this->assertSame(1, app('api-v1-test-counter')->count);
    }

    public function test_reusing_key_with_changed_payload_is_rejected_without_second_mutation(): void
    {
        $headers = ['Idempotency-Key' => 'idem-conflict-0001'];

        $this->withHeaders($headers)
            ->postJson('/api/v1/_contract/idempotency', ['value' => 'alpha'])
            ->assertCreated();

        $this->withHeaders($headers)
            ->postJson('/api/v1/_contract/idempotency', ['value' => 'beta'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'idempotency_key_reused')
            ->assertJsonPath('error.retryable', false);

        $this->assertSame(1, app('api-v1-test-counter')->count);
    }

    public function test_processing_duplicate_returns_retry_after_and_does_not_mutate(): void
    {
        $payload = ['value' => 'alpha'];
        $requestHash = hash('sha256', json_encode([$payload, []], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        DB::table('api_v1_idempotency_keys')->insert([
            'actor_key' => 'user:123',
            'scope' => 'api.v1.contract.idempotency',
            'idempotency_key' => 'idem-processing-0001',
            'request_hash' => $requestHash,
            'state' => 'processing',
            'created_at' => now(),
            'updated_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        $this->withHeaders(['Idempotency-Key' => 'idem-processing-0001'])
            ->postJson('/api/v1/_contract/idempotency', $payload)
            ->assertStatus(409)
            ->assertHeader('Retry-After', '1')
            ->assertJsonPath('error.code', 'request_in_progress')
            ->assertJsonPath('error.retryable', true);

        $this->assertSame(0, app('api-v1-test-counter')->count);
    }

    public function test_server_error_releases_key_for_later_retry(): void
    {
        $headers = ['Idempotency-Key' => 'idem-server-0001'];

        $this->withHeaders($headers)
            ->postJson('/api/v1/_contract/idempotency-server-error', ['value' => 'alpha'])
            ->assertStatus(500);

        $this->withHeaders($headers)
            ->postJson('/api/v1/_contract/idempotency-server-error', ['value' => 'alpha'])
            ->assertStatus(500);

        $this->assertSame(2, app('api-v1-test-counter')->count);
        $this->assertSame(0, DB::table('api_v1_idempotency_keys')->count());
    }

    public function test_malformed_idempotency_key_uses_v1_validation_error(): void
    {
        $this->withHeaders(['Idempotency-Key' => 'bad key'])
            ->postJson('/api/v1/_contract/idempotency', ['value' => 'alpha'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('meta.api_version', 'v1');

        $this->assertSame(0, app('api-v1-test-counter')->count);
    }
}
