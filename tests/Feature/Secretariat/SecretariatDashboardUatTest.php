<?php

namespace Tests\Feature\Secretariat;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatOffice;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecretariatDashboardUatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_central_shortcut_provisions_one_canonical_office_and_opens_dashboard(): void
    {
        $admin = User::factory()->create(['is_admin' => 1]);

        $this->actingAs($admin)
            ->get(route('secretariat.central'))
            ->assertRedirect();

        $office = SecretariatOffice::query()->where('office_type', 'central')->firstOrFail();

        $this->assertSame('EARTHCOOP-CENTRAL', $office->code);
        $this->assertSame(1, SecretariatOffice::query()->where('office_type', 'central')->count());

        $this->actingAs($admin)->get(route('secretariat.central'))->assertRedirect(route('secretariat.index', $office));
        $this->assertSame(1, SecretariatOffice::query()->where('office_type', 'central')->count());

        $this->actingAs($admin)
            ->get(route('secretariat.index', $office))
            ->assertOk()
            ->assertSee('داشبورد دبیرخانه')
            ->assertSee('دبیرخانه مرکزی EarthCoop')
            ->assertSee('ثبت سند رسمی')
            ->assertSee('تنظیمات دبیرخانه')
            ->assertSee('اسناد بنیادین EarthCoop');
    }

    public function test_non_admin_cannot_provision_central_office(): void
    {
        $user = User::factory()->create(['is_admin' => 0]);

        $this->actingAs($user)->get(route('secretariat.central'))->assertForbidden();

        $this->assertDatabaseMissing('secretariat_offices', ['office_type' => 'central']);
    }

    public function test_active_group_member_can_trigger_canonical_group_office_provisioning_and_open_read_only_dashboard(): void
    {
        $member = User::factory()->create();
        $group = Group::query()->create(['name' => 'گروه UAT دبیرخانه', 'group_type' => '0']);
        $this->attachMember($group, $member, 1);

        $this->actingAs($member)
            ->get(route('secretariat.group', $group))
            ->assertRedirect();

        $office = SecretariatOffice::query()
            ->where('office_type', 'group')
            ->where('scope_type', 'group')
            ->where('scope_id', $group->id)
            ->firstOrFail();

        $this->assertSame('GROUP-' . $group->id, $office->code);
        $this->assertSame(1, SecretariatOffice::query()
            ->where('office_type', 'group')
            ->where('scope_type', 'group')
            ->where('scope_id', $group->id)
            ->count());

        $this->actingAs($member)
            ->get(route('secretariat.index', $office))
            ->assertOk()
            ->assertSee('داشبورد دبیرخانه')
            ->assertSee('حالت مشاهده')
            ->assertDontSee('ثبت سند رسمی')
            ->assertDontSee('تنظیمات دبیرخانه');
    }

    public function test_group_manager_dashboard_uses_group_specific_operational_guidance_not_central_foundational_documents_copy(): void
    {
        $manager = User::factory()->create();
        $group = Group::query()->create(['name' => 'گروه مدیریت دبیرخانه', 'group_type' => '0']);
        $this->attachMember($group, $manager, 3);
        $office = app(SecretariatOfficeService::class)->ensureGroup($group);

        $this->actingAs($manager)
            ->get(route('secretariat.index', $office))
            ->assertOk()
            ->assertSee('دفتر گروه')
            ->assertSee('حالت مدیریت')
            ->assertSee('کار فوری دبیرخانه')
            ->assertSee('امور رسمی این گروه')
            ->assertDontSee('اسناد بنیادین EarthCoop');
    }

    public function test_group_inspector_can_open_existing_dashboard_but_cannot_open_office_settings(): void
    {
        $manager = User::factory()->create();
        $inspector = User::factory()->create();
        $group = Group::query()->create(['name' => 'گروه بازرسی دبیرخانه', 'group_type' => '0']);
        $this->attachMember($group, $manager, 3);
        $this->attachMember($group, $inspector, 2);

        $office = app(SecretariatOfficeService::class)->ensureGroup($group);

        $this->actingAs($inspector)
            ->get(route('secretariat.index', $office))
            ->assertOk()
            ->assertSee('حالت بازرسی')
            ->assertDontSee('تنظیمات دبیرخانه');

        $this->actingAs($inspector)
            ->get(route('secretariat.settings.edit', $office))
            ->assertForbidden();
    }

    public function test_group_manager_can_update_bounded_office_settings(): void
    {
        $manager = User::factory()->create();
        $group = Group::query()->create(['name' => 'گروه تنظیمات دبیرخانه', 'group_type' => '0']);
        $this->attachMember($group, $manager, 3);
        $office = app(SecretariatOfficeService::class)->ensureGroup($group);

        $this->actingAs($manager)
            ->get(route('secretariat.settings.edit', $office))
            ->assertOk()
            ->assertSee('تنظیمات دبیرخانه');

        $this->actingAs($manager)
            ->put(route('secretariat.settings.update', $office), [
                'name' => 'دبیرخانه رسمی گروه تنظیمات',
                'default_confidentiality' => 'office_members',
                'numbering_format' => 'GRP/{YEAR}/{FAMILY}/{SEQ}',
                'sequence_width' => 5,
            ])
            ->assertRedirect(route('secretariat.index', $office));

        $office->refresh();
        $this->assertSame('دبیرخانه رسمی گروه تنظیمات', $office->name);
        $this->assertSame('office_members', $office->default_confidentiality);
        $this->assertSame('GRP/{YEAR}/{FAMILY}/{SEQ}', $office->numbering_policy['format']);
        $this->assertSame(5, $office->numbering_policy['sequence_width']);
    }

    private function attachMember(Group $group, User $user, int $role): void
    {
        GroupUser::query()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 1,
            'expired' => null,
        ]);
    }
}
