<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\FounderOps\FounderAuthoritySnapshotService;
use Tests\TestCase;

class FounderAuthoritySnapshotServiceTest extends TestCase
{
    public function test_default_rollout_is_fail_closed_with_no_active_delegations(): void
    {
        $snapshot = app(FounderAuthoritySnapshotService::class)->snapshot();

        $this->assertTrue($snapshot['fail_closed']);
        $this->assertSame(0, $snapshot['active_delegations_count']);
        $this->assertSame([], $snapshot['active_delegations']);
        $this->assertGreaterThan(0, $snapshot['total_actions']);
        $this->assertGreaterThan(0, data_get($snapshot, 'by_mode.approval_required', 0));
        $this->assertGreaterThan(0, data_get($snapshot, 'by_mode.forbidden', 0));
    }
}
