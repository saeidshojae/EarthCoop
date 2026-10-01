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

final class WeeklyRoleAudienceSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_ordinary_member_report_excludes_users_with_manager_or_inspector_responsibility(): void
    {
        Queue::fake();

        $ordinary = User::factory()->create(['status' => 'active', 'is_system' => false]);
        $manager = User::factory()->create(['status' => 'active', 'is_system' => false]);
        $inspector = User::factory()->create(['status' => 'active', 'is_system' => false]);

        $group = Group::query()->create(['name' => 'گروه نقش گزارش']);
        DB::table('group_user')->insert([
            $this->membership($group->id, $ordinary->id, 1),
            $this->membership($group->id, $manager->id, 3),
            $this->membership($group->id, $inspector->id, 2),
        ]);

        $run = $this->runFor('reports.member.weekly.rule');
        (new ResolveCommunicationRunAudience($run->id, 50, 0))
            ->handle(app(CommunicationAudienceRegistry::class));

        $recipientIds = Communication::query()
            ->where('communication_run_id', $run->id)
            ->get()
            ->flatMap(fn (Communication $communication) => $communication->recipients()->pluck('user_id'))
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->all();

        $this->assertSame([$ordinary->id], $recipientIds);
    }

    public function test_manager_and_inspector_reports_select_only_the_corresponding_current_role(): void
    {
        Queue::fake();

        $ordinary = User::factory()->create(['status' => 'active', 'is_system' => false]);
        $manager = User::factory()->create(['status' => 'active', 'is_system' => false]);
        $inspector = User::factory()->create(['status' => 'active', 'is_system' => false]);
        $expiredManager = User::factory()->create(['status' => 'active', 'is_system' => false]);

        $group = Group::query()->create(['name' => 'گروه نقش متناظر']);
        DB::table('group_user')->insert([
            $this->membership($group->id, $ordinary->id, 1),
            $this->membership($group->id, $manager->id, 3),
            $this->membership($group->id, $inspector->id, 2),
            $this->membership($group->id, $expiredManager->id, 3, now()->subDay()),
        ]);

        $this->assertSame([$manager->id], $this->recipientIdsFor('reports.manager.weekly.rule'));
        $this->assertSame([$inspector->id], $this->recipientIdsFor('reports.inspector.weekly.rule'));
    }

    /** @return array<string,mixed> */
    private function membership(int $groupId, int $userId, int $role, $expired = null): array
    {
        return [
            'group_id' => $groupId,
            'user_id' => $userId,
            'role' => $role,
            'status' => 1,
            'expired' => $expired,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function runFor(string $ruleKey): CommunicationRun
    {
        $rule = CommunicationRule::query()->where('key', $ruleKey)->firstOrFail();

        return CommunicationRun::query()->create([
            'communication_rule_id' => $rule->id,
            'run_key' => $ruleKey.':selection-test:'.uniqid('', true),
            'status' => 'pending',
            'started_at' => now(),
        ]);
    }

    /** @return array<int,int> */
    private function recipientIdsFor(string $ruleKey): array
    {
        $run = $this->runFor($ruleKey);
        (new ResolveCommunicationRunAudience($run->id, 50, 0))
            ->handle(app(CommunicationAudienceRegistry::class));

        return Communication::query()
            ->where('communication_run_id', $run->id)
            ->get()
            ->flatMap(fn (Communication $communication) => $communication->recipients()->pluck('user_id'))
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->all();
    }
}
