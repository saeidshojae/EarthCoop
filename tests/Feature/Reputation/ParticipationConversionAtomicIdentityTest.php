<?php

namespace Tests\Feature\Reputation;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ParticipationConversionAtomicIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversion_request_identity_is_unique_per_user_and_request_key(): void
    {
        $user = User::factory()->create();

        DB::table('user_point_conversions')->insert([
            'user_id' => $user->id,
            'request_key' => 'same-request',
            'conversion_key' => 'reputation-conversion:' . $user->id . ':same-request',
            'requested_points' => 100,
            'consumed_points' => 100,
            'amount_gol' => 1,
            'ratio' => 100,
            'status' => 'applied',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('user_point_conversions')->insert([
            'user_id' => $user->id,
            'request_key' => 'same-request',
            'conversion_key' => 'reputation-conversion:' . $user->id . ':same-request-duplicate',
            'requested_points' => 100,
            'consumed_points' => 100,
            'amount_gol' => 1,
            'ratio' => 100,
            'status' => 'applied',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_same_request_key_remains_independent_between_users(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        foreach ([$first, $second] as $user) {
            DB::table('user_point_conversions')->insert([
                'user_id' => $user->id,
                'request_key' => 'shared-request',
                'conversion_key' => 'reputation-conversion:' . $user->id . ':shared-request',
                'requested_points' => 100,
                'consumed_points' => 100,
                'amount_gol' => 1,
                'ratio' => 100,
                'status' => 'applied',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->assertSame(2, DB::table('user_point_conversions')->where('request_key', 'shared-request')->count());
    }

    public function test_consumption_rows_can_be_owned_by_one_conversion_identity(): void
    {
        $user = User::factory()->create();

        $conversionId = DB::table('user_point_conversions')->insertGetId([
            'user_id' => $user->id,
            'request_key' => 'owned-request',
            'conversion_key' => 'reputation-conversion:' . $user->id . ':owned-request',
            'requested_points' => 100,
            'consumed_points' => 0,
            'amount_gol' => 1,
            'ratio' => 100,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $columns = DB::getSchemaBuilder()->getColumnListing('user_point_consumptions');

        $this->assertContains('user_point_conversion_id', $columns);
        $this->assertDatabaseHas('user_point_conversions', [
            'id' => $conversionId,
            'user_id' => $user->id,
            'request_key' => 'owned-request',
        ]);
    }
}
