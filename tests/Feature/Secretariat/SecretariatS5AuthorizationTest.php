<?php

namespace Tests\Feature\Secretariat;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\Secretariat\Services\SecretariatCaseService;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use App\Policies\NajmBahar\ProjectPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecretariatS5AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_oversight_visibility_includes_leadership_but_not_sensitive_material(): void
    {
        [$manager, $member, $observer, $office] = $this->groupOffice();
        $cases = app(SecretariatCaseService::class);
        $records = app(SecretariatRecordService::class);

        $ordinary = $cases->create($office, $manager, [
            'title' => 'Ordinary case',
            'confidentiality' => 'office_members',
        ]);
        $leadership = $cases->create($office, $manager, [
            'title' => 'Leadership case',
            'confidentiality' => 'leadership',
        ]);
        $restricted = $cases->create($office, $manager, [
            'title' => 'Restricted case',
            'confidentiality' => 'restricted',
        ]);

        $leadershipRecord = $records->createDraft($office, $manager, [
            'record_type' => 'official_report',
            'title' => 'Management oversight report',
            'confidentiality' => 'leadership',
        ]);
        $restrictedRecord = $records->createDraft($office, $manager, [
            'record_type' => 'official_note',
            'title' => 'Sensitive note',
            'confidentiality' => 'restricted',
        ]);

        $this->assertTrue($member->can('view', $ordinary));
        $this->assertTrue($member->can('view', $leadership));
        $this->assertTrue($observer->can('view', $leadership));
        $this->assertTrue($member->can('view', $leadershipRecord));
        $this->assertTrue($observer->can('view', $leadershipRecord));

        $this->assertFalse($member->can('view', $restricted));
        $this->assertFalse($manager->can('view', $restricted));
        $this->assertFalse($member->can('view', $restrictedRecord));
        $this->assertFalse($observer->can('view', $restrictedRecord));
        $this->assertTrue($manager->can('manage', $ordinary));
    }

    public function test_is_admin_global_administrator_can_create_and_view_restricted_case(): void
    {
        [$manager, , , $office] = $this->groupOffice();
        $admin = User::factory()->create(['is_admin' => true]);
        $restricted = app(SecretariatCaseService::class)->create($office, $manager, [
            'title' => 'Restricted admin case',
            'confidentiality' => 'restricted',
        ]);

        $this->assertTrue($admin->can('view', $office));
        $this->assertTrue($admin->can('view', $restricted));
        $this->assertTrue($admin->can('manage', $restricted));

        $this->actingAs($admin)
            ->post(route('secretariat.cases.store', $office), [
                'title' => 'Admin confidential case',
                'summary' => 'Canonical is_admin must receive the full confidentiality list.',
                'confidentiality' => 'confidential',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('secretariat_cases', [
            'office_id' => $office->id,
            'title' => 'Admin confidential case',
            'confidentiality' => 'confidential',
        ]);
    }

    public function test_public_project_office_and_non_sensitive_records_are_oversight_visible_but_not_manageable_by_non_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::query()->create([
            'owner_type' => User::class,
            'owner_id' => $owner->id,
            'title' => 'S5 public project',
            'summary' => 'Project fixture required by the real Najm Bahar schema.',
            'project_type' => 'service',
            'project_visibility' => 'public',
            'project_stage' => 'idea',
            'status' => 'approved',
        ]);

        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'PRJ-S5-' . $project->id,
            'name' => 'Project Secretariat',
            'office_type' => 'project',
            'scope_type' => 'najm_bahar_project',
            'scope_id' => $project->id,
        ]);

        $records = app(SecretariatRecordService::class);
        $leadershipRecord = $records->createDraft($office, $owner, [
            'record_type' => 'financial_record',
            'title' => 'Project financial oversight report',
            'confidentiality' => 'leadership',
        ]);
        $restrictedRecord = $records->createDraft($office, $owner, [
            'record_type' => 'official_note',
            'title' => 'Project protected material',
            'confidentiality' => 'restricted',
        ]);

        $this->assertTrue(app(ProjectPolicy::class)->view($other, $project));
        $this->assertTrue($owner->can('view', $office));
        $this->assertTrue($owner->can('manage', $office));
        $this->assertTrue($owner->can('inspect', $office));

        $this->assertTrue($other->can('view', $office));
        $this->assertFalse($other->can('manage', $office));
        $this->assertFalse($other->can('inspect', $office));
        $this->assertTrue($other->can('view', $leadershipRecord));
        $this->assertFalse($other->can('view', $restrictedRecord));

        // Project owners keep preparation/registration authority for their office.
        $this->assertTrue($owner->can('update', $leadershipRecord));
        $pending = $records->submitForApproval($leadershipRecord, $owner);
        $this->assertTrue($owner->can('register', $pending));
    }

    private function groupOffice(): array
    {
        $manager = User::factory()->create();
        $member = User::factory()->create();
        $observer = User::factory()->create();
        $group = Group::query()->create(['name' => 'S5 auth', 'group_type' => '0']);

        foreach ([[$manager, 3], [$member, 1], [$observer, 0]] as [$user, $role]) {
            GroupUser::query()->create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'role' => $role,
                'status' => 1,
                'expired' => null,
            ]);
        }

        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'S5-AUTH',
            'name' => 'S5 Auth Office',
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $group->id,
        ]);

        return [$manager, $member, $observer, $office];
    }
}
