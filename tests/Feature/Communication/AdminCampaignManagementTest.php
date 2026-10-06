<?php

namespace Tests\Feature\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationSenderIdentity;
use App\Models\CommunicationTemplate;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Communication\CommunicationTemplateService;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AdminCampaignManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_canonical_permission_seeder_registers_separate_campaign_create_and_approve_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->assertDatabaseHas('permissions', ['slug' => 'communications.campaigns.create', 'module' => 'communications']);
        $this->assertDatabaseHas('permissions', ['slug' => 'communications.campaigns.approve', 'module' => 'communications']);
    }

    public function test_campaign_creator_can_create_draft_but_cannot_confirm_without_approval_permission(): void
    {
        $creator = $this->userWithPermissions(['communications.view', 'communications.campaigns.create']);
        [$template, $sender, $recipient] = $this->fixture();

        $this->actingAs($creator)
            ->post('/admin/communications/campaigns', $this->payload($template, $sender, $recipient))
            ->assertRedirect('/admin/communications/campaigns');

        $campaign = CommunicationCampaign::query()->where('name', 'Task 10 admin campaign')->firstOrFail();
        $this->assertSame('draft', $campaign->status);
        $this->assertSame($creator->id, $campaign->created_by);

        $this->actingAs($creator)
            ->post('/admin/communications/campaigns/'.$campaign->id.'/confirm', ['elevated_confirmed' => true])
            ->assertForbidden();
    }

    public function test_campaign_schedule_input_uses_temporal_context_and_persists_canonical_utc(): void
    {
        $creator = $this->userWithPermissions(['communications.view', 'communications.campaigns.create']);
        $creator->forceFill([
            'locale' => 'fa',
            'timezone' => 'Asia/Tehran',
        ])->save();

        [$template, $sender, $recipient] = $this->fixture();

        $this->actingAs($creator)
            ->get('/admin/communications/campaigns/create')
            ->assertOk()
            ->assertSee('data-temporal-datetime-input', false);

        $localized = '۱۴۰۵/۰۷/۱۴ ۱۰:۳۰';
        $context = app(TemporalContextResolver::class)->forUser($creator->fresh());
        $expected = app(TemporalService::class)->parseDateTime($localized, $context);

        $this->actingAs($creator)
            ->post('/admin/communications/campaigns', array_merge(
                $this->payload($template, $sender, $recipient),
                ['scheduled_at' => $localized],
            ))
            ->assertRedirect('/admin/communications/campaigns');

        $campaign = CommunicationCampaign::query()
            ->where('name', 'Task 10 admin campaign')
            ->latest('id')
            ->firstOrFail();

        $raw = DB::table('communication_campaigns')
            ->where('id', $campaign->id)
            ->value('scheduled_at');
        $databaseTimezone = DB::selectOne(
            'SELECT @@session.time_zone AS session_tz, @@system_time_zone AS system_tz'
        );

        $this->assertSame(
            $expected->format('Y-m-d H:i:s'),
            (string) $raw,
            'MySQL timezone session='.$databaseTimezone->session_tz.' system='.$databaseTimezone->system_tz,
        );
        $this->assertSame(
            $expected->format('Y-m-d H:i:s'),
            $campaign->scheduled_at?->utc()->format('Y-m-d H:i:s'),
        );
    }

    public function test_campaign_approver_can_preview_and_confirm_existing_draft(): void
    {
        $approver = $this->userWithPermissions(['communications.view', 'communications.campaigns.approve']);
        [$template, $sender, $recipient] = $this->fixture();

        $campaign = CommunicationCampaign::query()->create([
            'name' => 'Task 10 approval campaign',
            'status' => 'draft',
            'audience_definition' => ['key' => 'specific.user', 'user_ids' => [$recipient->id]],
            'communication_template_id' => $template->id,
            'communication_sender_identity_id' => $sender->id,
            'classification' => CommunicationClassification::Operational,
            'priority' => 4,
        ]);

        $this->actingAs($approver)
            ->get('/admin/communications/campaigns/'.$campaign->id.'/preview')
            ->assertOk()
            ->assertSee('1');

        $this->actingAs($approver)
            ->post('/admin/communications/campaigns/'.$campaign->id.'/confirm', ['elevated_confirmed' => true])
            ->assertRedirect('/admin/communications/campaigns');

        $this->assertSame($approver->id, $campaign->fresh()->approved_by);
        $this->assertNotNull($campaign->fresh()->confirmed_at);
    }

    /** @return array{0:CommunicationTemplate,1:CommunicationSenderIdentity,2:User} */
    private function fixture(): array
    {
        $sender = CommunicationSenderIdentity::query()->create([
            'key' => 'task10-admin-sender-'.uniqid(),
            'email' => 'task10-admin-'.uniqid().'@earthcoop.ir',
            'display_name' => 'Task 10 Admin Sender',
            'is_active' => true,
            'is_default' => false,
        ]);

        $template = CommunicationTemplate::query()->create([
            'key' => 'task10.admin.template.'.uniqid('', true),
            'name' => 'Task 10 admin template',
            'category' => 'test',
            'classification' => CommunicationClassification::Operational,
            'is_active' => true,
        ]);

        app(CommunicationTemplateService::class)->publish(
            $template,
            'fa',
            'کمپین {{display_name}}',
            '<p>{{display_name}}</p>',
            ['display_name' => ['type' => 'string', 'required' => true]],
            $sender,
        );

        return [$template, $sender, User::factory()->create(['email' => 'campaign-admin-recipient@example.test'])];
    }

    /** @return array<string,mixed> */
    private function payload(CommunicationTemplate $template, CommunicationSenderIdentity $sender, User $recipient): array
    {
        return [
            'name' => 'Task 10 admin campaign',
            'audience_key' => 'specific.user',
            'user_ids' => [$recipient->id],
            'communication_template_id' => $template->id,
            'communication_sender_identity_id' => $sender->id,
            'classification' => 'operational',
            'priority' => 4,
        ];
    }

    /** @param array<int,string> $permissionSlugs */
    private function userWithPermissions(array $permissionSlugs): User
    {
        $role = Role::query()->create([
            'name' => 'Communication campaign role '.uniqid('', true),
            'slug' => 'communication-campaign-role-'.uniqid(),
            'is_system' => false,
            'order' => 920,
        ]);

        foreach ($permissionSlugs as $index => $slug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $slug,
                    'description' => $slug,
                    'module' => 'communications',
                    'order' => 920 + $index,
                ],
            );
            $role->permissions()->attach($permission->id);
        }

        $user = User::factory()->create(['is_admin' => false]);
        $user->roles()->attach($role->id);

        return $user->fresh();
    }
}
