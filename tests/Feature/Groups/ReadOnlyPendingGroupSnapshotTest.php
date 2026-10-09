<?php

namespace Tests\Feature\Groups;

use App\Models\LocationScopedGroupRequest;
use App\Models\User;
use App\Services\Groups\PendingLocationGroupRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReadOnlyPendingGroupSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_group_snapshot_reads_only_open_requests_for_target_user(): void
    {
        $target = User::factory()->create();
        $other = User::factory()->create();

        foreach ([
            [$target->id, 'pending_location'],
            [$target->id, 'ready_to_materialize'],
            [$target->id, 'cancelled'],
            [$other->id, 'pending_location'],
        ] as [$userId, $status]) {
            LocationScopedGroupRequest::query()->create([
                'requester_user_id' => $userId,
                'scope_kind' => 'official_system',
                'dimension_key' => 'public',
                'dimension_value_key' => 'public',
                'status' => $status,
                'metadata' => ['type_key' => 'neighborhood'],
            ]);
        }

        $before = LocationScopedGroupRequest::query()->orderBy('id')->get()->toArray();
        $requests = app(PendingLocationGroupRequestService::class)->readOpenForUser($target);
        $after = LocationScopedGroupRequest::query()->orderBy('id')->get()->toArray();

        $this->assertCount(2, $requests);
        $this->assertSame(['pending_location', 'ready_to_materialize'], $requests->pluck('status')->all());
        $this->assertSame($before, $after, 'Viewing another profile must never mutate pending requests.');
    }
    public function test_nine_pending_base_group_requests_are_preserved_without_mutation(): void
    {
        $user = User::factory()->create();

        foreach ([
            'public' => 1,
            'profession' => 3,
            'specialty' => 3,
            'age' => 1,
            'gender' => 1,
        ] as $dimension => $count) {
            for ($i = 1; $i <= $count; $i++) {
                LocationScopedGroupRequest::query()->create([
                    'requester_user_id' => $user->id,
                    'scope_kind' => 'official_system',
                    'dimension_key' => $dimension,
                    'dimension_value_key' => $dimension.'-'.$i,
                    'status' => 'pending_location',
                    'metadata' => ['type_key' => 'neighborhood', 'is_pending_base' => true],
                ]);
            }
        }

        $service = app(PendingLocationGroupRequestService::class);
        $before = LocationScopedGroupRequest::query()->count();
        $pending = $service->readOpenForUser($user);

        $this->assertCount(9, $pending);
        $this->assertSame([
            'age' => 1,
            'gender' => 1,
            'profession' => 3,
            'public' => 1,
            'specialty' => 3,
        ], $pending->countBy('dimension_key')->sortKeys()->all());
        $this->assertSame($before, LocationScopedGroupRequest::query()->count());
    }

}
