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

class SecretariatS4HttpUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_manager_can_open_guided_correspondence_form_from_office(): void
    {
        [$manager, $office] = $this->managerOffice('S4-HTTP-FORM');

        $this->actingAs($manager)
            ->get(route('secretariat.index', $office))
            ->assertOk()
            ->assertSee('نامه وارده')
            ->assertSee('نامه صادره')
            ->assertSee('مکاتبه داخلی');

        $this->actingAs($manager)
            ->get(route('secretariat.correspondence.create', ['office' => $office, 'direction' => 'incoming']))
            ->assertOk()
            ->assertSee('ثبت مکاتبه')
            ->assertSee('طرف بیرونی');
    }

    public function test_manager_can_create_incoming_letter_with_party_snapshot_attachment_and_specialized_redirect(): void
    {
        Storage::fake('local');
        [$manager, $office] = $this->managerOffice('S4-HTTP-IN');

        $response = $this->actingAs($manager)->post(route('secretariat.correspondence.store', $office), [
            'direction' => 'incoming',
            'title' => 'درخواست رسمی همکاری',
            'subject' => 'همکاری با مجمع',
            'body' => 'متن نامه وارده',
            'confidentiality' => 'office_members',
            'channel' => 'email',
            'external_reference_number' => 'EXT-UI-100',
            'received_at' => now()->format('Y-m-d H:i:s'),
            'external_party_name' => 'سازمان نمونه',
            'external_party_email' => 'office@example.test',
            'attachment' => UploadedFile::fake()->createWithContent('incoming.txt', 'incoming-evidence'),
        ]);

        $record = SecretariatRecord::query()->where('title', 'درخواست رسمی همکاری')->firstOrFail();
        $response->assertRedirect(route('secretariat.correspondence.show', [$office, $record]));
        $this->assertSame('incoming_letter', $record->record_type);
        $this->assertSame('EXT-UI-100', $record->correspondenceDetail->external_reference_number);
        $this->assertSame(1, $record->parties()->where('role', 'sender')->where('display_name', 'سازمان نمونه')->count());
        $this->assertSame(1, $record->parties()->where('role', 'recipient')->where('party_type', 'group')->count());
        Storage::disk('local')->assertExists($record->attachments()->firstOrFail()->storage_key);

        $this->actingAs($manager)
            ->get(route('secretariat.correspondence.show', [$office, $record]))
            ->assertOk()
            ->assertSee('EXT-UI-100')
            ->assertSee('سازمان نمونه')
            ->assertSee('incoming.txt');
    }

    public function test_manager_can_register_correspondence_then_create_and_advance_internal_dispatch(): void
    {
        [$manager, $office, $group] = $this->managerOffice('S4-HTTP-DISPATCH', null, true);
        $recipient = User::factory()->create();
        $this->addMember($group, $recipient, 0);

        $this->actingAs($manager)->post(route('secretariat.correspondence.store', $office), [
            'direction' => 'incoming',
            'title' => 'نامه برای ارجاع',
            'confidentiality' => 'office_members',
            'channel' => 'email',
            'received_at' => now()->format('Y-m-d H:i:s'),
            'external_party_name' => 'مرجع بیرونی',
        ])->assertRedirect();

        $record = SecretariatRecord::query()->where('title', 'نامه برای ارجاع')->firstOrFail();
        $this->actingAs($manager)->post(route('secretariat.records.submit', [$office, $record]))->assertRedirect();
        $this->actingAs($manager)->post(route('secretariat.records.register', [$office, $record]))->assertRedirect();

        $this->actingAs($manager)->post(route('secretariat.dispatches.store', [$office, $record]), [
            'dispatch_type' => 'referral',
            'channel' => 'internal',
            'target_user_id' => $recipient->id,
            'instructions' => 'لطفاً بررسی شود.',
        ])->assertRedirect();

        $dispatch = $record->dispatches()->firstOrFail();
        $this->assertSame('pending', $dispatch->status);

        $this->actingAs($manager)->post(route('secretariat.dispatches.transition', [$office, $record, $dispatch]), [
            'status' => 'sent',
        ])->assertRedirect();

        $this->assertSame('sent', $dispatch->fresh()->status);
        $this->assertNotNull($dispatch->fresh()->dispatched_at);

        $this->actingAs($manager)
            ->get(route('secretariat.correspondence.show', [$office, $record]))
            ->assertOk()
            ->assertSee('لطفاً بررسی شود.')
            ->assertSee('ارسال‌شده');
    }

    public function test_non_office_user_cannot_open_correspondence_create_or_dispatch(): void
    {
        [$manager, $office] = $this->managerOffice('S4-HTTP-AUTH');
        $outsider = User::factory()->create();

        $this->actingAs($manager)->post(route('secretariat.correspondence.store', $office), [
            'direction' => 'incoming',
            'title' => 'نامه محافظت‌شده',
            'confidentiality' => 'office_members',
            'channel' => 'email',
            'received_at' => now()->format('Y-m-d H:i:s'),
            'external_party_name' => 'بیرونی',
        ]);
        $record = SecretariatRecord::query()->where('title', 'نامه محافظت‌شده')->firstOrFail();
        $this->actingAs($manager)->post(route('secretariat.records.submit', [$office, $record]));
        $this->actingAs($manager)->post(route('secretariat.records.register', [$office, $record]));

        $this->actingAs($outsider)
            ->get(route('secretariat.correspondence.create', $office))
            ->assertForbidden();
        $this->actingAs($outsider)
            ->get(route('secretariat.correspondence.show', [$office, $record]))
            ->assertForbidden();
        $this->actingAs($outsider)
            ->post(route('secretariat.dispatches.store', [$office, $record]), [
                'dispatch_type' => 'referral',
                'channel' => 'internal',
                'target_user_id' => $manager->id,
            ])
            ->assertForbidden();
    }

    public function test_correspondence_from_another_office_is_404_on_specialized_route(): void
    {
        [$manager, $officeA] = $this->managerOffice('S4-HTTP-A');
        [, $officeB] = $this->managerOffice('S4-HTTP-B', $manager);

        $this->actingAs($manager)->post(route('secretariat.correspondence.store', $officeA), [
            'direction' => 'incoming',
            'title' => 'نامه دفتر الف',
            'confidentiality' => 'office_members',
            'channel' => 'email',
            'received_at' => now()->format('Y-m-d H:i:s'),
            'external_party_name' => 'فرستنده',
        ]);
        $record = SecretariatRecord::query()->where('title', 'نامه دفتر الف')->firstOrFail();

        $this->actingAs($manager)
            ->get(route('secretariat.correspondence.show', [$officeB, $record]))
            ->assertNotFound();
    }

    /** @return array{0:User,1:\App\Modules\Secretariat\Models\SecretariatOffice,2?:Group} */
    private function managerOffice(string $code, ?User $manager = null, bool $includeGroup = false): array
    {
        $manager ??= User::factory()->create();
        $group = Group::query()->create(['name' => 'Secretariat ' . $code, 'group_type' => '0']);
        $this->addMember($group, $manager, 3);

        $office = app(SecretariatOfficeService::class)->create([
            'code' => $code,
            'name' => 'Secretariat Office ' . $code,
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $group->id,
        ]);

        return $includeGroup ? [$manager, $office, $group] : [$manager, $office];
    }

    private function addMember(Group $group, User $user, int $role): void
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
