<?php

namespace Tests\Feature\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Models\CommunicationRule;
use App\Models\CommunicationSenderIdentity;
use App\Models\CommunicationTemplate;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Communication\CommunicationTemplateService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class AdminAutomationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_canonical_permission_seeder_registers_communication_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->assertDatabaseHas('permissions', ['slug' => 'communications.view', 'module' => 'communications']);
        $this->assertDatabaseHas('permissions', ['slug' => 'communications.templates.manage', 'module' => 'communications']);
        $this->assertDatabaseHas('permissions', ['slug' => 'communications.rules.manage', 'module' => 'communications']);
        $this->assertDatabaseHas('permissions', ['slug' => 'communications.senders.manage', 'module' => 'communications']);
    }

    public function test_view_permission_does_not_grant_rule_management(): void
    {
        $user = $this->userWithPermissions(['communications.view']);
        [$template, $sender] = $this->templateAndSender();

        $this->actingAs($user)
            ->post('/admin/communications/automations', $this->scheduledPayload($template, $sender))
            ->assertForbidden();
    }

    public function test_rules_manager_can_create_registered_structured_scheduled_automation(): void
    {
        $user = $this->userWithPermissions([
            'communications.view',
            'communications.rules.manage',
        ]);
        [$template, $sender] = $this->templateAndSender();

        $this->actingAs($user)
            ->post('/admin/communications/automations', $this->scheduledPayload($template, $sender))
            ->assertRedirect('/admin/communications/automations');

        $rule = CommunicationRule::query()->where('key', 'task9.scheduled.rule')->firstOrFail();
        $this->assertSame('scheduled', $rule->trigger_type);
        $this->assertSame('role.member', $rule->audience_definition['key']);
        $this->assertSame($user->id, $rule->created_by);
        $this->assertSame($user->id, $rule->approved_by);
        $this->assertNotNull($rule->schedule);
        $this->assertSame('weekly', $rule->schedule->frequency);
        $this->assertSame(1, $rule->schedule->schedule_definition['interval']);
    }

    public function test_specific_user_scheduled_automation_persists_target_user_ids(): void
    {
        $manager = $this->userWithPermissions([
            'communications.view',
            'communications.rules.manage',
        ]);
        [$template, $sender] = $this->templateAndSender();
        $recipientA = User::factory()->create();
        $recipientB = User::factory()->create();
        $payload = $this->scheduledPayload($template, $sender);
        $payload['key'] = 'task9.scheduled.specific-users';
        $payload['audience_key'] = 'specific.user';
        $payload['user_ids'] = [$recipientA->id, $recipientB->id];

        $this->actingAs($manager)
            ->post('/admin/communications/automations', $payload)
            ->assertRedirect('/admin/communications/automations');

        $rule = CommunicationRule::query()->where('key', 'task9.scheduled.specific-users')->firstOrFail();
        $this->assertSame('specific.user', $rule->audience_definition['key']);
        $this->assertSame(
            [$recipientA->id, $recipientB->id],
            $rule->audience_definition['user_ids'] ?? null,
        );
    }

    public function test_automation_rejects_unregistered_audience_key(): void
    {
        $user = $this->userWithPermissions([
            'communications.view',
            'communications.rules.manage',
        ]);
        [$template, $sender] = $this->templateAndSender();
        $payload = $this->scheduledPayload($template, $sender);
        $payload['audience_key'] = 'raw.sql.audience';

        $this->actingAs($user)
            ->postJson('/admin/communications/automations', $payload)
            ->assertStatus(422)
            ->assertJsonPath('ok', false);

        $this->assertDatabaseMissing('communication_rules', ['key' => 'task9.scheduled.rule']);
    }

    public function test_conditional_automation_rejects_unregistered_condition_key(): void
    {
        $user = $this->userWithPermissions([
            'communications.view',
            'communications.rules.manage',
        ]);
        [$template, $sender] = $this->templateAndSender();

        $this->actingAs($user)
            ->postJson('/admin/communications/automations', [
                'key' => 'task9.conditional.rule',
                'name' => 'Task 9 conditional rule',
                'trigger_type' => 'conditional',
                'audience_key' => 'specific.user',
                'condition_key' => 'arbitrary.database.condition',
                'communication_template_id' => $template->id,
                'communication_sender_identity_id' => $sender->id,
                'classification' => 'operational',
                'priority' => 2,
                'delay_seconds' => 3600,
                'is_active' => true,
            ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);

        $this->assertDatabaseMissing('communication_rules', ['key' => 'task9.conditional.rule']);
    }

    public function test_sender_identity_in_use_cannot_be_deleted(): void
    {
        $user = $this->userWithPermissions([
            'communications.view',
            'communications.senders.manage',
        ]);
        [$template, $sender] = $this->templateAndSender();

        CommunicationRule::query()->create([
            'key' => 'task9.sender.reference',
            'name' => 'Sender reference',
            'trigger_type' => 'event',
            'event_key' => 'task9.test',
            'audience_definition' => ['key' => 'event.user'],
            'communication_template_id' => $template->id,
            'communication_sender_identity_id' => $sender->id,
            'classification' => CommunicationClassification::Operational,
            'priority' => 2,
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->deleteJson('/admin/communications/senders/'.$sender->id)
            ->assertStatus(409)
            ->assertJsonPath('ok', false);

        $this->assertDatabaseHas('communication_sender_identities', ['id' => $sender->id]);
    }

    /** @return array{0:CommunicationTemplate,1:CommunicationSenderIdentity} */
    private function templateAndSender(): array
    {
        $sender = CommunicationSenderIdentity::query()->create([
            'key' => 'task9-sender-'.uniqid(),
            'email' => 'task9-'.uniqid().'@earthcoop.ir',
            'display_name' => 'Task 9 Sender',
            'is_active' => true,
            'is_default' => false,
        ]);

        $template = CommunicationTemplate::query()->create([
            'key' => 'task9.automation.template.'.uniqid('', true),
            'name' => 'Task 9 automation template',
            'category' => 'test',
            'classification' => CommunicationClassification::Operational,
            'is_active' => true,
        ]);

        app(CommunicationTemplateService::class)->publish(
            $template,
            'fa',
            'گزارش {{display_name}}',
            '<p>{{display_name}}</p>',
            ['display_name' => ['type' => 'string', 'required' => true]],
            $sender,
        );

        return [$template, $sender];
    }

    /** @return array<string,mixed> */
    private function scheduledPayload(CommunicationTemplate $template, CommunicationSenderIdentity $sender): array
    {
        return [
            'key' => 'task9.scheduled.rule',
            'name' => 'Task 9 scheduled rule',
            'trigger_type' => 'scheduled',
            'audience_key' => 'role.member',
            'communication_template_id' => $template->id,
            'communication_sender_identity_id' => $sender->id,
            'classification' => 'operational',
            'priority' => 2,
            'delay_seconds' => 0,
            'is_active' => true,
            'frequency' => 'weekly',
            'schedule_definition' => ['interval' => 1],
            'timezone' => 'Asia/Tehran',
        ];
    }

    /** @param array<int,string> $permissionSlugs */
    private function userWithPermissions(array $permissionSlugs): User
    {
        $role = Role::query()->create([
            'name' => 'Communication automation role '.uniqid('', true),
            'slug' => 'communication-automation-role-'.uniqid(),
            'is_system' => false,
            'order' => 910,
        ]);

        foreach ($permissionSlugs as $index => $slug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $slug,
                    'description' => $slug,
                    'module' => 'communications',
                    'order' => 910 + $index,
                ],
            );
            $role->permissions()->attach($permission->id);
        }

        $user = User::factory()->create(['is_admin' => false]);
        $user->roles()->attach($role->id);

        return $user->fresh();
    }
}
