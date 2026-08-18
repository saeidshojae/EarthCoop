<?php

namespace Tests\Feature\Secretariat;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\Secretariat\Models\SecretariatCase;
use App\Modules\Secretariat\Services\SecretariatCaseService;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
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

    public function test_project_office_uses_existing_project_owner_authority(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::query()->create([
            'owner_type' => User::class,
            'owner_id' => $owner->id,
            'title' => 'S5 project',
            'project_type' => 'service',
            'project_visibility' => 'private',
            'project_stage' => 'idea',
            'status' => 'draft',
        ]);

        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'PRJ-S5-' . $project->id,
            'name' => 'Project Secretariat',
            'office_type' => 'project',
            'scope_type' => 'najm_bahar_project',
            'scope_id' => $project->id,
        ]);

        $this->assertTrue($owner->can('view', $office));
        $this->assertTrue($owner->can('manage', $office));
        $this->assertFalse($other->can('view', $office));
        $this->assertFalse($other->can('manage', $office));
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
