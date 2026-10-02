<?php

namespace Tests\Feature\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Models\CommunicationSenderIdentity;
use App\Models\CommunicationTemplate;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Communication\CommunicationTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminTemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_view_permission_does_not_grant_template_management(): void
    {
        $user = $this->userWithPermissions(['communications.view']);
        $template = $this->templateWithVersion();

        $this->actingAs($user)
            ->get('/admin/communications/templates')
            ->assertOk();

        $this->actingAs($user)
            ->post('/admin/communications/templates/'.$template->id.'/publish', [
                'locale' => 'fa',
                'subject' => 'نسخه دوم {{display_name}}',
                'body' => '<p>{{display_name}}</p>',
                'variables_schema' => json_encode([
                    'display_name' => ['type' => 'string', 'required' => true],
                ]),
            ])
            ->assertForbidden();
    }

    public function test_template_manager_publishes_an_immutable_new_version(): void
    {
        $user = $this->userWithPermissions([
            'communications.view',
            'communications.templates.manage',
        ]);
        $template = $this->templateWithVersion();

        $this->actingAs($user)
            ->post('/admin/communications/templates/'.$template->id.'/publish', [
                'locale' => 'fa',
                'subject' => 'نسخه دوم {{display_name}}',
                'body' => '<p>سلام {{display_name}}</p>',
                'variables_schema' => json_encode([
                    'display_name' => ['type' => 'string', 'required' => true],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ])
            ->assertRedirect('/admin/communications/templates/'.$template->id);

        $versions = $template->versions()->where('locale', 'fa')->orderBy('version')->get();
        $this->assertCount(2, $versions);
        $this->assertSame(1, $versions[0]->version);
        $this->assertSame(2, $versions[1]->version);
        $this->assertSame('نسخه دوم {{display_name}}', $versions[1]->subject);
        $this->assertNotNull($versions[0]->published_at);
        $this->assertNotNull($versions[1]->published_at);
        $this->assertSame($user->id, $versions[1]->created_by);
        $this->assertSame($user->id, $versions[1]->approved_by);
    }

    public function test_preview_rejects_context_variables_outside_published_schema(): void
    {
        $user = $this->userWithPermissions([
            'communications.view',
            'communications.templates.manage',
        ]);
        $template = $this->templateWithVersion();

        $this->actingAs($user)
            ->postJson('/admin/communications/templates/'.$template->id.'/preview', [
                'locale' => 'fa',
                'context' => [
                    'display_name' => 'کاربر نمونه',
                    'rogue_variable' => 'نباید پذیرفته شود',
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    private function templateWithVersion(): CommunicationTemplate
    {
        $sender = CommunicationSenderIdentity::query()->firstOrCreate(
            ['key' => 'task9-test-sender'],
            [
                'email' => 'task9@earthcoop.ir',
                'display_name' => 'EarthCoop Task 9',
                'is_active' => true,
            ],
        );

        $template = CommunicationTemplate::query()->create([
            'key' => 'task9.template.'.uniqid('', true),
            'name' => 'Task 9 template',
            'category' => 'test',
            'classification' => CommunicationClassification::Operational,
            'is_active' => true,
        ]);

        app(CommunicationTemplateService::class)->publish(
            $template,
            'fa',
            'نسخه اول {{display_name}}',
            '<p>{{display_name}}</p>',
            ['display_name' => ['type' => 'string', 'required' => true]],
            $sender,
        );

        return $template;
    }

    /** @param array<int,string> $permissionSlugs */
    private function userWithPermissions(array $permissionSlugs): User
    {
        $role = Role::query()->create([
            'name' => 'Communication test role '.uniqid('', true),
            'slug' => 'communication-test-role-'.uniqid(),
            'is_system' => false,
            'order' => 900,
        ]);

        foreach ($permissionSlugs as $index => $slug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $slug,
                    'description' => $slug,
                    'module' => 'communications',
                    'order' => 900 + $index,
                ],
            );
            $role->permissions()->attach($permission->id);
        }

        $user = User::factory()->create(['is_admin' => false]);
        $user->roles()->attach($role->id);

        return $user->fresh();
    }
}
