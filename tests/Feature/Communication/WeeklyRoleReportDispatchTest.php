<?php

namespace Tests\Feature\Communication;

use App\Jobs\Communication\ResolveCommunicationRunAudience;
use App\Models\Communication;
use App\Models\CommunicationRule;
use App\Models\CommunicationRun;
use App\Models\Group;
use App\Models\User;
use App\Services\Communication\CommunicationAudienceRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class WeeklyRoleReportDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_weekly_report_dispatches_structured_role_context(): void
    {
        $this->assertRoleReportDispatches(
            role: 3,
            ruleKey: 'reports.manager.weekly.rule',
            expectedContextKey: 'managed_groups_count',
            expectedIdsKey: 'managed_group_ids',
        );
    }

    public function test_inspector_weekly_report_dispatches_structured_role_context(): void
    {
        $this->assertRoleReportDispatches(
            role: 2,
            ruleKey: 'reports.inspector.weekly.rule',
            expectedContextKey: 'inspected_groups_count',
            expectedIdsKey: 'inspected_group_ids',
        );
    }

    private function assertRoleReportDispatches(
        int $role,
        string $ruleKey,
        string $expectedContextKey,
        string $expectedIdsKey,
    ): void {
        Queue::fake();

        $user = User::factory()->create([
            'status' => 'active',
            'is_system' => false,
        ]);
        $group = Group::query()->create(['name' => 'گروه نقش هفتگی']);

        DB::table('group_user')->insert([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 1,
            'expired' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rule = CommunicationRule::query()->where('key', $ruleKey)->firstOrFail();
        $run = CommunicationRun::query()->create([
            'communication_rule_id' => $rule->id,
            'run_key' => $ruleKey.':test:'.$user->id,
            'status' => 'pending',
            'started_at' => now(),
        ]);

        (new ResolveCommunicationRunAudience($run->id, 50, 0))
            ->handle(app(CommunicationAudienceRegistry::class));

        $communication = Communication::query()
            ->where('communication_run_id', $run->id)
            ->firstOrFail();

        $this->assertSame(1, $communication->context_snapshot[$expectedContextKey]);
        $this->assertSame([$group->id], $communication->context_snapshot[$expectedIdsKey]);

        $recipient = $communication->recipients()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('queued', $recipient->status->value ?? $recipient->status);
    }
}
