<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\NajmHodaGroupAttentionSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GroupAttentionPanelEndpointTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('najm_hoda_group_attention_settings')
            || ! Schema::hasTable('najm_hoda_group_attention_events')) {
            Artisan::call('migrate', [
                '--path' => 'database/migrations/2026_08_17_044500_create_najm_hoda_group_attention_tables.php',
                '--force' => true,
            ]);
        }
    }

    public function test_group_leader_can_read_and_update_attention_policy(): void
    {
        [$group, $manager] = $this->seedMember(3, 'مدیر', 'پیگیری');

        $this->actingAs($manager)
            ->getJson(route('groups.najm-hoda.attention', $group))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('attention.policy.enabled', false)
            ->assertJsonPath('attention.stats.active_events', 0);

        $this->actingAs($manager)
            ->putJson(route('groups.najm-hoda.attention.update', $group), [
                'enabled' => true,
                'digest_mode' => 'immediate',
                'preferred_time' => '09:30',
                'timezone' => 'Asia/Tehran',
                'due_soon_hours' => 24,
                'suppress_minutes' => 360,
                'alert_overdue' => true,
                'alert_due_soon' => true,
                'alert_blocked' => true,
                'alert_urgent' => true,
                'alert_unassigned' => false,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('attention.policy.enabled', true)
            ->assertJsonPath('attention.policy.digest_mode', 'immediate')
            ->assertJsonPath('attention.policy.preferred_time', '09:30')
            ->assertJsonPath('attention.policy.alert_unassigned', false);

        $setting = NajmHodaGroupAttentionSetting::where('group_id', $group->id)->firstOrFail();
        $this->assertTrue($setting->enabled);
        $this->assertSame('immediate', $setting->digest_mode);
        $this->assertSame(24, (int) $setting->due_soon_hours);
        $this->assertSame(360, (int) $setting->suppress_minutes);
        $this->assertFalse($setting->alert_unassigned);
    }

    public function test_regular_member_cannot_read_or_update_attention_policy(): void
    {
        [$group, $member] = $this->seedMember(1, 'عضو', 'عادی');

        $this->actingAs($member)
            ->getJson(route('groups.najm-hoda.attention', $group))
            ->assertForbidden();

        $this->actingAs($member)
            ->putJson(route('groups.najm-hoda.attention.update', $group), [
                'enabled' => true,
                'digest_mode' => 'daily',
                'preferred_time' => '08:00',
                'timezone' => 'Asia/Tehran',
                'due_soon_hours' => 48,
                'suppress_minutes' => 720,
                'alert_overdue' => true,
                'alert_due_soon' => true,
                'alert_blocked' => true,
                'alert_urgent' => true,
                'alert_unassigned' => true,
            ])
            ->assertForbidden();
    }

    public function test_frontend_module_declares_attention_panel_contract(): void
    {
        $module = file_get_contents(resource_path('js/najm-hoda-attention-panel.js'));
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertIsString($module);
        $this->assertStringContainsString('/najm-hoda/attention', $module);
        $this->assertStringContainsString('group-hoda-attention-panel', $module);
        $this->assertStringContainsString('attention-digest-mode', $module);
        $this->assertStringContainsString('attention-last-evaluated', $module);
        $this->assertStringContainsString('attention-stat-unassigned', $module);
        $this->assertStringContainsString('./najm-hoda-attention-panel.js', $app);
    }

    /** @return array{0:Group,1:User} */
    private function seedMember(int $role, string $firstName, string $lastName): array
    {
        $group = Group::create(['name' => 'Attention panel endpoint group', 'is_open' => 1]);
        $user = User::create([
            'email' => uniqid('attention-panel-', true) . '@example.test',
            'password' => Hash::make('password'),
            'status' => 1,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'is_system' => false,
        ]);
        GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 1,
        ]);

        return [$group, $user];
    }
}
