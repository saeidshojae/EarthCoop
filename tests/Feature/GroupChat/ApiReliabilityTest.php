<?php

namespace Tests\Feature\GroupChat;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApiReliabilityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('group_chat_idempotency_keys')) {
            Schema::create('group_chat_idempotency_keys', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('scope', 150);
                $table->string('idempotency_key', 100);
                $table->char('request_hash', 64);
                $table->string('state', 20);
                $table->unsignedSmallInteger('response_status')->nullable();
                $table->longText('response_body')->nullable();
                $table->timestamp('expires_at');
                $table->timestamps();
                $table->unique(['user_id', 'scope', 'idempotency_key'], 'group_chat_idempotency_unique');
            });
        }

        Route::middleware(['web', 'group.chat.idempotency', 'group.chat.context'])
            ->post('/_tests/group-chat/idempotency', function () {
                $count = Cache::increment('group-chat-idempotency-test-count');

                return response()->json(['status' => 'success', 'result' => $count]);
            })->name('tests.group-chat.idempotency');

        Cache::forget('group-chat-idempotency-test-count');
    }

    public function test_success_response_has_versioned_envelope_and_request_id(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->postJson('/_tests/group-chat/idempotency', ['value' => 1], [
            'X-Request-ID' => 'request-envelope-001',
        ]);

        $response->assertOk()
            ->assertHeader('X-Request-ID', 'request-envelope-001')
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('request_id', 'request-envelope-001')
            ->assertJsonPath('meta.api_version', '2026-08-05')
            ->assertJsonPath('data.result', 1);
    }

    public function test_same_idempotency_key_replays_response_without_reexecuting_operation(): void
    {
        $user = $this->makeUser();
        $headers = ['Idempotency-Key' => 'idem-replay-0001'];

        $first = $this->actingAs($user)->postJson('/_tests/group-chat/idempotency', ['value' => 1], $headers);
        $second = $this->actingAs($user)->postJson('/_tests/group-chat/idempotency', ['value' => 1], $headers);

        $first->assertOk()->assertJsonPath('result', 1);
        $second->assertOk()->assertHeader('Idempotency-Replayed', 'true')->assertJsonPath('result', 1);
        $this->assertSame(1, (int) Cache::get('group-chat-idempotency-test-count'));
    }

    public function test_reusing_key_with_different_payload_is_rejected(): void
    {
        $user = $this->makeUser();
        $headers = ['Idempotency-Key' => 'idem-conflict-001'];

        $this->actingAs($user)->postJson('/_tests/group-chat/idempotency', ['value' => 1], $headers)->assertOk();
        $this->actingAs($user)->postJson('/_tests/group-chat/idempotency', ['value' => 2], $headers)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'conflict');
    }

    private function makeUser(): User
    {
        return User::create([
            'first_name' => 'API',
            'last_name' => 'Tester',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password123'),
        ]);
    }
}
