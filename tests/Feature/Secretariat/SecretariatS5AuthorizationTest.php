<?php

namespace Tests\Feature\Secretariat;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\Secretariat\Services\SecretariatCaseService;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Policies\NajmBahar\ProjectPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecretariatS5AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_case_visibility_respects_case_confidentiality(): void
    {
        [$manager, $member, $office] = $this->groupOffice();
        $service = app(SecretariatCaseService::class);

        $ordinary = $service->create($office, $manager, [
            'title' => 'Ordinary case',
            'confidentiality' => 'office_members',
        ]);
        $leadership = $service->create($office, $manager, [
            'title' => 'Leadership case',
            'confidentiality' => 'leadership',
        ]);
        $restricted = $service->create($office, $manager, [
            'title' => 'Restricted case',
            'confidentiality' => 'restricted',
        ]);

        $this->assertTrue($member->can('view', $ordinary));
        $this->assertFalse($member->can('view', $leadership));
        $this->assertFalse($manager->can('view', $restricted));
        $this->assertTrue($manager->can('manage', $ordinary));
    }

    public function test_project_office_uses_project_owner_authority_not_public_project_visibility(): void
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

        // Source-domain public visibility remains intact.
        $this->assertTrue(app(ProjectPolicy::class)->view($other, $project));

        // Registry authority is intentionally narrower: project visibility does
        // not disclose the project's Secretariat office or formal case metadata.
        $this->assertTrue($owner->can('view', $office));
        $this->assertTrue($owner->can('manage', $office));
        $this->assertTrue($owner->can('inspect', $office));
        $this->assertFalse($other->can('view', $office));
        $this->assertFalse($other->can('manage', $office));
        $this->assertFalse($other->can('inspect', $office));
    }

    private function groupOffice(): array
    {
        $manager = User::factory()->create();
        $member = User::factory()->create();
        $group = Group::query()->create(['name' => 'S5 auth', 'group_type' => '0']);

        GroupUser::query()->create([
            'group_id' => $group->id,
            'user_id' => $manager->id,
            'role' => 3,
            'status' => 1,
            'expired' => null,
        ]);
        GroupUser::query()->create([
            'group_id' => $group->id,
            'user_id' => $member->id,
            'role' => 1,
            'status' => 1,
            'expired' => null,
        ]);

        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'S5-AUTH',
            'name' => 'S5 Auth Office',
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $group->id,
        ]);

        return [$manager, $member, $office];
    }
}
