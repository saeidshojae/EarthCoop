<?php

namespace Tests\Feature\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Models\CommunicationRule;
use App\Models\CommunicationTemplate;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ScheduledAutomationSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_specific_user_target_field_is_present_on_creation_form(): void
    {
        $manager = $this->userWithPermissions([
            'communications.view',
            'communications.rules.manage',
        ]);

        $this->actingAs($manager)
            ->get('/admin/communications/automations/create')
            ->assertOk()
            ->assertSee('name="user_ids[]"', false);
    }

    public function test_rule_manager_can_deactivate_an_active_automation(): void
    {
        $manager = $this->userWithPermissions([
            'communications.view',
            'communications.rules.manage',
        ]);
        $template = CommunicationTemplate::query()->create([
            'key' => 'scheduled.safety.template',
            'name' => 'Scheduled safety template',
            'category' => 'test',
            'classification' => CommunicationClassification::Operational,
            'is_active' => true,
        ]);

        $rule = CommunicationRule::query()->create([
            'key' => 'scheduled.safety.deactivate',
            'name' => 'Scheduled safety deactivate',
            'trigger_type' => 'scheduled',
            'audience_definition' => ['key' => 'specific.user', 'user_ids' => [$manager->id]],
            'communication_template_id' => $template->id,
            'communication_sender_identity_id' => null,
            'classification' => CommunicationClassification::Operational,
            'priority' => 2,
            'is_active' => true,
            'created_by' => $manager->id,
            'approved_by' => $manager->id,
        ]);

        $deactivatePath = '/admin/communications/automations/'.$rule->id.'/deactivate';

        $this->actingAs($manager)
            ->get('/admin/communications/automations')
            ->assertOk()
            ->assertSee($deactivatePath, false);

        $this->actingAs($manager)
            ->post($deactivatePath)
            ->assertRedirect('/admin/communications/automations');

        $this->assertFalse($rule->fresh()->is_active);
    }

    /** @param array<int,string> $permissionSlugs */
    private function userWithPermissions(array $permissionSlugs): User
    {
        $role = Role::query()->create([
            'name' => 'Scheduled automation safety role '.uniqid('', true),
            'slug' => 'scheduled-automation-safety-'.uniqid(),
            'is_system' => false,
            'order' => 930,
        ]);

        foreach ($permissionSlugs as $index => $slug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $slug,
                    'description' => $slug,
                    'module' => 'communications',
                    'order' => 930 + $index,
                ],
            );
            $role->permissions()->attach($permission->id);
        }

        $user = User::factory()->create(['is_admin' => false]);
        $user->roles()->attach($role->id);

        return $user->fresh();
    }
}
