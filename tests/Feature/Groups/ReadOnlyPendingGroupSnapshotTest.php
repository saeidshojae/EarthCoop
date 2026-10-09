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
}
