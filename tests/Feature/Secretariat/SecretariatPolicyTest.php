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

        $this->assertTrue($manager->can('register', $recordA));
        $this->assertFalse($manager->can('register', $recordB));
        $this->assertFalse($member->can('register', $recordA));
        $this->assertTrue($member->can('view', $recordA));

        $recordA->forceFill(['confidentiality' => 'confidential'])->save();
        $this->assertFalse($member->can('view', $recordA));
        $this->assertFalse($manager->can('view', $recordA));
    }
}
