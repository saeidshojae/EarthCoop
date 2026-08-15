<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Modules\Governance\Models\EconomicAction;
use App\Modules\Governance\Models\Proposal;
use App\Modules\Governance\Models\Resolution;
use App\Modules\Governance\Services\PublicExecutionBridge;
use App\Services\NajmHoda\Runtime\NajmBaharHealthBridgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NajmBaharHealthBridgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_bridge_is_read_only_and_reports_healthy_state(): void
    {
        $snapshot = app(NajmBaharHealthBridgeService::class)->snapshot();

        $this->assertSame('najm_bahar', $snapshot['source']);
        $this->assertTrue($snapshot['read_only']);
        $this->assertSame('healthy', $snapshot['severity']);
        $this->assertFalse($snapshot['operator_attention']['required']);
        $this->assertTrue($snapshot['capabilities']['observe']);
        $this->assertTrue($snapshot['capabilities']['report']);
        $this->assertFalse($snapshot['capabilities']['retry']);
        $this->assertFalse($snapshot['capabilities']['recover_dead_letter']);
        $this->assertFalse($snapshot['capabilities']['move_money']);
    }

    public function test_bridge_surfaces_critical_dead_letter_without_recovery_capability(): void
    {
        $group = Group::create([
            'name' => 'مجمع تست سلامت نجم بهار',
            'group_type' => 'public',
            'location_level' => 'neighborhood',
        ]);
        $proposal = Proposal::create([
            'group_id' => $group->id,
            'type' => 'public_project',
            'title' => 'پروژه تست سلامت',
        ]);
        $resolution = Resolution::create([
            'proposal_id' => $proposal->id,
            'group_id' => $group->id,
            'type' => 'public_project',
            'status' => 'adopted',
        ]);
        $action = EconomicAction::create([
            'resolution_id' => $resolution->id,
            'group_id' => $group->id,
            'action_type' => PublicExecutionBridge::PUBLIC_PROJECT_EXECUTION_AUTHORIZED,
            'status' => 'dead_letter',
            'idempotency_key' => 'najm-hoda-health-bridge:' . $resolution->id,
            'payload' => [],
            'attempts' => 5,
            'failure_reason' => 'synthetic critical failure',
            'failed_at' => now(),
        ]);

        $snapshot = app(NajmBaharHealthBridgeService::class)->snapshot();

        $this->assertSame('critical', $snapshot['severity']);
        $this->assertSame(1, $snapshot['dead_letter']);
        $this->assertTrue($snapshot['operator_attention']['required']);
        $this->assertSame((int) $action->id, (int) $snapshot['items'][0]['id']);
        $this->assertFalse($snapshot['capabilities']['recover_dead_letter']);
        $this->assertFalse($snapshot['capabilities']['move_money']);
        $this->assertSame('dead_letter', $action->fresh()->status);
    }
}
