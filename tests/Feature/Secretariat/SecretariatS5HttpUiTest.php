<?php

namespace Tests\Feature\Secretariat;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatCase;
use App\Modules\Secretariat\Services\SecretariatCaseService;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecretariatS5HttpUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_manager_can_create_case_from_http_and_member_can_view_ordinary_case(): void
    {
        [$manager, $member, $office] = $this->groupOffice('S5-HTTP-A');

        $response = $this->actingAs($manager)->post(route('secretariat.cases.store', $office), [
            'title' => 'Public office matter',
            'summary' => 'Case created through S5 HTTP boundary.',
            'confidentiality' => 'office_members',
        ]);

        $case = SecretariatCase::query()->where('title', 'Public office matter')->firstOrFail();
        $response->assertRedirect(route('secretariat.cases.show', [$office, $case]));

        $this->actingAs($member)
            ->get(route('secretariat.cases.show', [$office, $case]))
            ->assertOk()
            ->assertSee('Public office matter')
            ->assertSee($case->case_number);
    }

    public function test_outsider_cannot_list_or_open_group_cases(): void
    {
        [$manager, , $office] = $this->groupOffice('S5-HTTP-B');
        $outsider = User::factory()->create();
        $case = app(SecretariatCaseService::class)->create($office, $manager, [
            'title' => 'Internal case',
            'confidentiality' => 'office_members',
        ]);

        $this->actingAs($outsider)->get(route('secretariat.cases.index', $office))->assertForbidden();
        $this->actingAs($outsider)->get(route('secretariat.cases.show', [$office, $case]))->assertForbidden();
    }

    public function test_ordinary_case_does_not_leak_restricted_member_record_metadata(): void
    {
        [$manager, $member, $office] = $this->groupOffice('S5-HTTP-C');
        $case = app(SecretariatCaseService::class)->create($office, $manager, [
            'title' => 'Ordinary case shell',
            'confidentiality' => 'office_members',
        ]);

        $records = app(SecretariatRecordService::class);
        $secret = $records->createDraft($office, $manager, [
            'record_type' => 'official_note',
            'direction' => 'internal',
            'title' => 'TOP SECRET MEMBER RECORD',
            'confidentiality' => 'restricted',
        ]);
        $records->submitForApproval($secret, $manager);
        $secret = $records->register($secret->fresh(), $manager);
        app(SecretariatCaseService::class)->addRecord($case, $secret, $manager, 'evidence');

        $this->actingAs($member)
            ->get(route('secretariat.cases.show', [$office, $case]))
            ->assertOk()
            ->assertDontSee('TOP SECRET MEMBER RECORD')
            ->assertDontSee($secret->registry_number);
    }

    public function test_manager_can_add_visible_formal_record_and_transition_case_via_http(): void
    {
        [$manager, , $office] = $this->groupOffice('S5-HTTP-D');
        $case = app(SecretariatCaseService::class)->create($office, $manager, [
            'title' => 'Action case',
            'confidentiality' => 'office_members',
        ]);
        $record = $this->formalRecord($office, $manager, 'Visible evidence');

        $this->actingAs($manager)
            ->post(route('secretariat.cases.records.store', [$office, $case]), [
                'record_id' => $record->id,
                'role' => 'evidence',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('secretariat_case_records', [
            'case_id' => $case->id,
            'record_id' => $record->id,
            'link_type' => 'local_membership',
            'source_office_id' => $office->id,
            'role' => 'evidence',
        ]);

        $this->actingAs($manager)
            ->post(route('secretariat.cases.transition', [$office, $case]), ['status' => 'closed'])
            ->assertRedirect();

        $this->assertSame('closed', $case->fresh()->status);
    }

    public function test_cross_office_reference_is_not_a_copy_and_is_rechecked_for_each_case_viewer(): void
    {
        [$manager, $destinationMember, $destinationOffice] = $this->groupOffice('S5-XOFF-DST');
        [, , $sourceOffice] = $this->groupOffice('S5-XOFF-SRC', $manager);
        $case = app(SecretariatCaseService::class)->create($destinationOffice, $manager, [
            'title' => 'Cross-office case',
            'confidentiality' => 'office_members',
        ]);
        $foreign = $this->formalRecord($sourceOffice, $manager, 'Foreign source evidence');

        $this->actingAs($manager)
            ->post(route('secretariat.cases.references.store', [$destinationOffice, $case]), [
                'source_office_id' => $sourceOffice->id,
                'registry_number' => $foreign->registry_number,
                'role' => 'evidence',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('secretariat_case_records', [
            'case_id' => $case->id,
            'record_id' => $foreign->id,
            'link_type' => 'cross_office_reference',
            'source_office_id' => $sourceOffice->id,
        ]);
        $this->assertSame($sourceOffice->id, $foreign->fresh()->office_id);

        // The manager belongs to both offices and sees the referenced source.
        $this->actingAs($manager)
            ->get(route('secretariat.cases.show', [$destinationOffice, $case]))
            ->assertOk()
            ->assertSee('Foreign source evidence');

        // Destination-only member can see the Case but receives no title/number
        // metadata for the foreign record because its source RecordPolicy denies it.
        $this->actingAs($destinationMember)
            ->get(route('secretariat.cases.show', [$destinationOffice, $case]))
            ->assertOk()
            ->assertDontSee('Foreign source evidence')
            ->assertDontSee($foreign->registry_number);

        $this->assertDatabaseHas('secretariat_audit_events', [
            'office_id' => $destinationOffice->id,
            'record_id' => null,
            'actor_id' => $manager->id,
            'event_type' => 'cross_office_case_reference_added',
        ]);
        $this->assertDatabaseHas('secretariat_audit_events', [
            'office_id' => $sourceOffice->id,
            'record_id' => $foreign->id,
            'actor_id' => $manager->id,
            'event_type' => 'record_referenced_by_foreign_case',
        ]);
    }

    public function test_case_from_another_office_returns_404_even_for_manager_of_both(): void
    {
        [$manager, , $officeA] = $this->groupOffice('S5-HTTP-E1');
        [, , $officeB] = $this->groupOffice('S5-HTTP-E2', $manager);
        $case = app(SecretariatCaseService::class)->create($officeA, $manager, [
            'title' => 'Office A case',
            'confidentiality' => 'office_members',
        ]);

        $this->actingAs($manager)
            ->get(route('secretariat.cases.show', [$officeB, $case]))
            ->assertNotFound();
    }

    public function test_non_admin_http_cannot_create_sensitive_case_without_case_acl(): void
    {
        [$manager, , $office] = $this->groupOffice('S5-HTTP-F');

        $this->actingAs($manager)
            ->post(route('secretariat.cases.store', $office), [
                'title' => 'Unreadable sensitive case',
                'confidentiality' => 'restricted',
            ])
            ->assertSessionHasErrors('confidentiality');

        $this->assertDatabaseMissing('secretariat_cases', ['title' => 'Unreadable sensitive case']);
    }

    private function formalRecord($office, User $actor, string $title)
    {
        $service = app(SecretariatRecordService::class);
        $record = $service->createDraft($office, $actor, [
            'record_type' => 'official_note',
            'direction' => 'internal',
            'title' => $title,
            'confidentiality' => 'office_members',
        ]);
        $service->submitForApproval($record, $actor);
        return $service->register($record->fresh(), $actor);
    }

    private function groupOffice(string $code, ?User $manager = null): array
    {
        $manager ??= User::factory()->create();
        $member = User::factory()->create();
        $group = Group::query()->create(['name' => $code, 'group_type' => '0']);

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
            'code' => $code,
            'name' => $code,
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $group->id,
        ]);

        return [$manager, $member, $office];
    }
}
