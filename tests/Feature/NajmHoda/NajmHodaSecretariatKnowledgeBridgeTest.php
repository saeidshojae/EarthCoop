<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use App\Services\NajmHoda\Knowledge\NajmHodaSecretariatKnowledgeBridge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NajmHodaSecretariatKnowledgeBridgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_bridge_uses_trusted_actor_object_and_ignores_spoofed_actor_id_context(): void
    {
        $manager = User::factory()->create();
        $outsider = User::factory()->create();
        $group = Group::query()->create(['name' => 'Bridge Group', 'group_type' => '0']);
        GroupUser::query()->create([
            'group_id' => $group->id,
            'user_id' => $manager->id,
            'role' => 3,
            'status' => 1,
            'expired' => null,
        ]);

        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'S6-BRIDGE',
            'name' => 'S6 Bridge Office',
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $group->id,
        ]);
        $record = app(SecretariatRecordService::class)->createDraft($office, $manager, [
            'record_type' => 'official_note',
            'direction' => 'internal',
            'title' => 'Bridge protected record',
            'body' => 'bridge-canary-77123 protected knowledge',
            'confidentiality' => 'office_members',
        ]);

        $bridge = app(NajmHodaSecretariatKnowledgeBridge::class);
        $spoofed = $bridge->retrieve($outsider, 'bridge-canary-77123', [
            'actor_id' => $manager->id,
            'user_id' => $manager->id,
            'office_id' => $office->id,
        ]);

        $this->assertSame($outsider->id, $spoofed['actor_id']);
        $this->assertSame(0, $spoofed['count']);
        $this->assertSame([], $spoofed['packets']);

        $authorized = $bridge->retrieve($manager, 'bridge-canary-77123', [
            'actor_id' => $outsider->id,
            'office_id' => $office->id,
        ]);
        $this->assertSame($manager->id, $authorized['actor_id']);
        $this->assertSame(1, $authorized['count']);
        $this->assertSame($record->id, $authorized['packets'][0]['record_id']);
    }

    public function test_bridge_only_forwards_whitelisted_retrieval_filters(): void
    {
        $manager = User::factory()->create();
        $group = Group::query()->create(['name' => 'Bridge Filter Group', 'group_type' => '0']);
        GroupUser::query()->create([
            'group_id' => $group->id,
            'user_id' => $manager->id,
            'role' => 3,
            'status' => 1,
            'expired' => null,
        ]);
        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'S6-BRIDGE-FILTER',
            'name' => 'S6 Bridge Filter Office',
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $group->id,
        ]);
        $record = app(SecretariatRecordService::class)->createDraft($office, $manager, [
            'record_type' => 'official_note',
            'direction' => 'internal',
            'title' => 'Whitelisted bridge filter',
            'body' => 'bridge-filter-canary-22991',
            'confidentiality' => 'office_members',
        ]);

        $result = app(NajmHodaSecretariatKnowledgeBridge::class)->retrieve(
            $manager,
            'bridge-filter-canary-22991',
            [
                'office_id' => $office->id,
                'limit' => 1,
                'text' => 'attacker-controlled-override-must-not-win',
                'registry_number' => 'attacker-override',
            ]
        );

        $this->assertSame(1, $result['count']);
        $this->assertSame($record->id, $result['packets'][0]['record_id']);
    }
}
