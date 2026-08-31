<?php

namespace Tests\Feature\Secretariat;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecretariatS2AccessDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_directory_only_lists_offices_visible_to_user(): void
    {
        [$manager, $ownedOffice] = $this->managerOffice('DIR-OWNED');
        [, $otherOffice] = $this->managerOffice('DIR-OTHER');

        $this->actingAs($manager)
            ->get(route('secretariat.directory'))
            ->assertOk()
            ->assertSee($ownedOffice->name)
            ->assertDontSee($otherOffice->name);
    }

    public function test_authenticated_user_dropdown_exposes_my_secretariats_entry(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $html = view('components.user-dropdown-unified')->render();

        $this->assertStringContainsString('دبیرخانه‌های من', $html);
        $this->assertStringContainsString(route('secretariat.directory'), $html);
    }

    public function test_group_shortcut_redirects_member_to_canonical_group_office(): void
    {
        [$manager, $office, $group] = $this->managerOffice('DIR-SHORTCUT');

        $this->actingAs($manager)
            ->get(route('secretariat.group', $group))
            ->assertRedirect(route('secretariat.index', $office));
    }

    public function test_central_shortcut_redirects_admin_to_central_office(): void
    {
        $admin = User::factory()->create(['is_admin' => 1]);
        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'CENTRAL-UAT',
            'name' => 'EarthCoop Central Secretariat',
            'office_type' => 'central',
        ]);

        $this->actingAs($admin)
            ->get(route('secretariat.central'))
            ->assertRedirect(route('secretariat.index', $office));
    }

    public function test_manager_can_grant_and_revoke_explicit_view_access_through_http_ui(): void
    {
        [$manager, $office, $group] = $this->managerOffice('ACL-HTTP');
        $reader = User::factory()->create();
        $record = app(SecretariatRecordService::class)->createDraft($office, $manager, [
            'record_type' => 'official_note',
            'title' => 'Restricted access record',
            'confidentiality' => 'restricted',
        ]);

        $this->assertTrue($manager->can('manageAcl', $record));
        $this->assertFalse($reader->can('view', $record));

        $this->actingAs($manager)
            ->get(route('secretariat.acl.index', [$office, $record]))
            ->assertOk()
            ->assertSee('مدیریت دسترسی صریح');

        $this->actingAs($manager)
            ->post(route('secretariat.acl.grant', [$office, $record]), [
                'principal_type' => 'user',
                'principal_id' => $reader->id,
            ])
            ->assertRedirect();

        $entry = $record->aclEntries()
            ->where('principal_type', 'user')
            ->where('principal_id', $reader->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertTrue($reader->can('view', $record));
        $this->assertDatabaseHas('secretariat_audit_events', [
            'record_id' => $record->id,
            'actor_id' => $manager->id,
            'event_type' => 'acl_granted',
        ]);

        $this->actingAs($manager)
            ->delete(route('secretariat.acl.revoke', [$office, $record, $entry]))
            ->assertRedirect();

        $this->assertFalse($reader->can('view', $record));
        $this->assertNotNull($entry->fresh()->revoked_at);
        $this->assertDatabaseHas('secretariat_audit_events', [
            'record_id' => $record->id,
            'actor_id' => $manager->id,
            'event_type' => 'acl_revoked',
        ]);
    }

    public function test_ordinary_member_cannot_manage_acl(): void
    {
        [$manager, $office, $group] = $this->managerOffice('ACL-DENY');
        $member = User::factory()->create();
        $reader = User::factory()->create();

        GroupUser::query()->create([
            'group_id' => $group->id,
            'user_id' => $member->id,
            'role' => 1,
            'status' => 1,
            'expired' => null,
        ]);

        $record = app(SecretariatRecordService::class)->createDraft($office, $manager, [
            'record_type' => 'official_note',
            'title' => 'ACL denied record',
            'confidentiality' => 'restricted',
        ]);

        $this->actingAs($member)
            ->get(route('secretariat.acl.index', [$office, $record]))
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('secretariat.acl.grant', [$office, $record]), [
                'principal_type' => 'user',
                'principal_id' => $reader->id,
            ])
            ->assertForbidden();

        $this->assertFalse($reader->can('view', $record));
    }

    /** @return array{0:User,1:\App\Modules\Secretariat\Models\SecretariatOffice,2:Group} */
    private function managerOffice(string $code, ?User $manager = null): array
    {
        $manager ??= User::factory()->create();
        $group = Group::query()->create([
            'name' => 'Secretariat ' . $code,
            'group_type' => '0',
        ]);

        GroupUser::query()->create([
            'group_id' => $group->id,
            'user_id' => $manager->id,
            'role' => 3,
            'status' => 1,
            'expired' => null,
        ]);

        $office = app(SecretariatOfficeService::class)->create([
            'code' => $code,
            'name' => 'Secretariat Office ' . $code,
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $group->id,
        ]);

        return [$manager, $office, $group];
    }
}
