<?php

namespace Tests\Feature\Secretariat;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecretariatPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_manager_is_scoped_to_own_office_and_ordinary_member_cannot_register(): void
    {
        $manager = User::factory()->create();
        $member = User::factory()->create();
        $groupA = Group::query()->create(['name' => 'Group A', 'group_type' => '0']);
        $groupB = Group::query()->create(['name' => 'Group B', 'group_type' => '0']);

        GroupUser::query()->create([
            'group_id' => $groupA->id,
            'user_id' => $manager->id,
            'role' => 3,
            'status' => 1,
            'expired' => null,
        ]);
        GroupUser::query()->create([
            'group_id' => $groupA->id,
            'user_id' => $member->id,
            'role' => 1,
            'status' => 1,
            'expired' => null,
        ]);

        $offices = app(SecretariatOfficeService::class);
        $officeA = $offices->create([
            'code' => 'GROUP-A',
            'name' => 'Group A Secretariat',
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $groupA->id,
        ]);
        $officeB = $offices->create([
            'code' => 'GROUP-B',
            'name' => 'Group B Secretariat',
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $groupB->id,
        ]);

        $records = app(SecretariatRecordService::class);
        $recordA = $records->submitForApproval($records->createDraft($officeA, $manager, [
            'record_type' => 'meeting_minute',
            'title' => 'A minute',
        ]), $manager);
        $recordB = $records->submitForApproval($records->createDraft($officeB, $manager, [
            'record_type' => 'meeting_minute',
            'title' => 'B minute',
        ]), $manager);
        $confidential = $records->submitForApproval($records->createDraft($officeA, $manager, [
            'record_type' => 'official_note',
            'title' => 'Sensitive note',
            'confidentiality' => 'confidential',
        ]), $manager);

        $this->assertTrue($manager->can('register', $recordA));
        $this->assertFalse($manager->can('register', $recordB));
        $this->assertFalse($member->can('register', $recordA));
        $this->assertTrue($member->can('view', $recordA));
        $this->assertFalse($member->can('view', $confidential));
        $this->assertFalse($manager->can('view', $confidential));
    }

    public function test_inspector_can_prepare_but_cannot_register_or_drive_formal_lifecycle(): void
    {
        $inspector = User::factory()->create();
        $manager = User::factory()->create();
        $group = Group::query()->create(['name' => 'Policy Group', 'group_type' => '0']);

        GroupUser::query()->create([
            'group_id' => $group->id,
            'user_id' => $inspector->id,
            'role' => 2,
            'status' => 1,
            'expired' => null,
        ]);
        GroupUser::query()->create([
            'group_id' => $group->id,
            'user_id' => $manager->id,
            'role' => 3,
            'status' => 1,
            'expired' => null,
        ]);

        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'POLICY-GROUP',
            'name' => 'Policy Group Secretariat',
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $group->id,
        ]);

        $records = app(SecretariatRecordService::class);
        $draft = $records->createDraft($office, $inspector, [
            'record_type' => 'official_report',
            'title' => 'Inspector report draft',
        ]);

        $this->assertTrue($inspector->can('update', $draft));
        $this->assertTrue($inspector->can('submitForApproval', $draft));

        $pending = $records->submitForApproval($draft, $inspector);
        $this->assertFalse($inspector->can('register', $pending));
        $this->assertTrue($manager->can('register', $pending));

        $registered = $records->register($pending, $manager);
        $this->assertFalse($inspector->can('transition', $registered));
        $this->assertTrue($manager->can('transition', $registered));
    }
}
