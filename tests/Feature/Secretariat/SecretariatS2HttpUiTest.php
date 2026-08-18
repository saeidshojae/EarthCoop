<?php

namespace Tests\Feature\Secretariat;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecretariatS2HttpUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_manager_can_create_confidential_record_with_attachment_and_reach_same_record(): void
    {
        Storage::fake('local');
        [$manager, $office] = $this->managerOffice();

        $response = $this->actingAs($manager)->post(route('secretariat.records.store', $office), [
            'record_type' => 'official_report',
            'direction' => 'internal',
            'title' => 'Confidential UI report',
            'subject' => 'S2 end to end',
            'summary' => 'Sensitive report created through the real HTTP boundary.',
            'body' => 'Evidence body',
            'confidentiality' => 'confidential',
            'attachment' => UploadedFile::fake()->createWithContent('evidence.txt', 'ui-evidence'),
        ]);

        $record = SecretariatRecord::query()->where('title', 'Confidential UI report')->firstOrFail();
        $response->assertRedirect(route('secretariat.records.show', [$office, $record]));

        $this->assertDatabaseHas('secretariat_acl_entries', [
            'record_id' => $record->id,
            'principal_type' => 'user',
            'principal_id' => $manager->id,
            'permission' => 'view',
            'revoked_at' => null,
        ]);

        $attachment = $record->attachments()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->storage_key);

        $this->actingAs($manager)
            ->get(route('secretariat.records.show', [$office, $record]))
            ->assertOk()
            ->assertSee('Confidential UI report')
            ->assertSee('evidence.txt');

        $this->assertDatabaseHas('secretariat_audit_events', [
            'record_id' => $record->id,
            'actor_id' => $manager->id,
            'event_type' => 'access_sensitive',
        ]);
    }

    public function test_ungranted_user_cannot_open_or_download_confidential_record(): void
    {
        Storage::fake('local');
        [$manager, $office] = $this->managerOffice();
        $other = User::factory()->create();

        $this->actingAs($manager)->post(route('secretariat.records.store', $office), [
            'record_type' => 'official_note',
            'direction' => 'internal',
            'title' => 'Private note',
            'confidentiality' => 'confidential',
            'attachment' => UploadedFile::fake()->createWithContent('private.txt', 'private'),
        ])->assertRedirect();

        $record = SecretariatRecord::query()->where('title', 'Private note')->firstOrFail();
        $attachment = $record->attachments()->firstOrFail();

        $this->actingAs($other)
            ->get(route('secretariat.records.show', [$office, $record]))
            ->assertForbidden();

        $this->actingAs($other)
            ->get(route('secretariat.attachments.download', [$office, $record, $attachment]))
            ->assertForbidden();

        $this->assertDatabaseMissing('secretariat_audit_events', [
            'record_id' => $record->id,
            'actor_id' => $other->id,
            'event_type' => 'access_sensitive',
        ]);
    }

    public function test_manager_can_submit_and_register_record_created_from_ui(): void
    {
        [$manager, $office] = $this->managerOffice();

        $this->actingAs($manager)->post(route('secretariat.records.store', $office), [
            'record_type' => 'meeting_minute',
            'direction' => 'internal',
            'title' => 'Meeting minute through UI',
            'confidentiality' => 'office_members',
        ])->assertRedirect();

        $record = SecretariatRecord::query()->where('title', 'Meeting minute through UI')->firstOrFail();

        $this->actingAs($manager)
            ->post(route('secretariat.records.submit', [$office, $record]))
            ->assertRedirect();
        $this->assertSame('pending_approval', $record->fresh()->status);

        $this->actingAs($manager)
            ->post(route('secretariat.records.register', [$office, $record]))
            ->assertRedirect();

        $registered = $record->fresh();
        $this->assertSame('registered', $registered->status);
        $this->assertNotNull($registered->registry_number);
        $this->assertTrue((bool) $registered->currentVersion->is_official);
    }

    public function test_record_from_another_office_returns_404_even_when_user_can_view_both_offices(): void
    {
        [$manager, $officeA] = $this->managerOffice('HTTP-A');
        [, $officeB] = $this->managerOffice('HTTP-B', $manager);

        $this->actingAs($manager)->post(route('secretariat.records.store', $officeA), [
            'record_type' => 'official_note',
            'direction' => 'internal',
            'title' => 'Office A note',
            'confidentiality' => 'office_members',
        ]);

        $record = SecretariatRecord::query()->where('title', 'Office A note')->firstOrFail();

        $this->actingAs($manager)
            ->get(route('secretariat.records.show', [$officeB, $record]))
            ->assertNotFound();
    }

    /** @return array{0:User,1:\App\Modules\Secretariat\Models\SecretariatOffice} */
    private function managerOffice(string $code = 'HTTP-S2', ?User $manager = null): array
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

        return [$manager, $office];
    }
}
