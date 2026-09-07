<?php

namespace Tests\Feature\Secretariat;

use App\Models\Group;
use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatParty;
use App\Modules\Secretariat\Services\SecretariatCorrespondenceService;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class SecretariatS4CorrespondenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_incoming_correspondence_preserves_queryable_details_and_party_snapshots(): void
    {
        [$actor, $group, $office] = $this->context('S4-IN');
        $sender = User::factory()->create();

        $record = app(SecretariatCorrespondenceService::class)->createDraft(
            $office,
            $actor,
            'incoming',
            [
                'title' => 'نامه درخواست همکاری',
                'subject' => 'همکاری تخصصی',
                'body' => 'متن نامه وارده',
                'external_reference_number' => 'EXT-2026-77',
                'received_at' => now()->subHour(),
                'channel' => 'email',
            ],
            [
                [
                    'role' => 'sender',
                    'party_type' => 'user',
                    'user_id' => $sender->id,
                    'display_name' => 'فرستنده عضو',
                    'email' => 'sender@example.test',
                ],
                [
                    'role' => 'recipient',
                    'party_type' => 'group',
                    'group_id' => $group->id,
                    'display_name' => $group->name,
                ],
            ],
        );

        $this->assertSame('incoming_letter', $record->record_type);
        $this->assertSame('incoming', $record->direction);
        $this->assertSame('EXT-2026-77', $record->correspondenceDetail->external_reference_number);
        $this->assertNotNull($record->correspondenceDetail->received_at);
        $this->assertNull($record->correspondenceDetail->sent_at);
        $this->assertCount(2, $record->parties);
        $this->assertSame(1, $record->parties()->where('role', 'sender')->count());
        $this->assertSame(1, $record->parties()->where('role', 'recipient')->count());
        $this->assertDatabaseHas('secretariat_audit_events', [
            'record_id' => $record->id,
            'event_type' => 'party_added',
        ]);
    }

    public function test_correspondence_requires_sender_and_recipient(): void
    {
        [$actor, $group, $office] = $this->context('S4-PARTY-REQ');

        $this->expectException(ValidationException::class);
        app(SecretariatCorrespondenceService::class)->createDraft(
            $office,
            $actor,
            'incoming',
            [
                'title' => 'نامه ناقص',
                'received_at' => now(),
            ],
            [[
                'role' => 'recipient',
                'party_type' => 'group',
                'group_id' => $group->id,
                'display_name' => $group->name,
            ]],
        );
    }

    public function test_formal_record_party_snapshot_cannot_be_mutated(): void
    {
        [$actor, $group, $office] = $this->context('S4-IMMUTABLE');
        $record = $this->incomingDraft($actor, $group, $office);
        $records = app(SecretariatRecordService::class);
        $formal = $records->register($records->submitForApproval($record, $actor), $actor);
        $party = $formal->parties()->where('role', 'sender')->firstOrFail();

        $this->expectException(LogicException::class);
        $party->update(['display_name' => 'نام تغییر یافته']);
    }

    public function test_response_relation_uses_existing_registry_relation_model(): void
    {
        [$actor, $group, $office] = $this->context('S4-REPLY');
        $service = app(SecretariatCorrespondenceService::class);
        $incoming = $this->incomingDraft($actor, $group, $office);
        $outgoing = $service->createDraft(
            $office,
            $actor,
            'outgoing',
            [
                'title' => 'پاسخ رسمی',
                'body' => 'پاسخ به نامه',
                'channel' => 'email',
            ],
            [
                [
                    'role' => 'sender',
                    'party_type' => 'group',
                    'group_id' => $group->id,
                    'display_name' => $group->name,
                ],
                [
                    'role' => 'recipient',
                    'party_type' => 'external',
                    'display_name' => 'سازمان بیرونی',
                    'email' => 'external@example.test',
                ],
            ],
        );

        $service->linkResponse($outgoing, $incoming, $actor);
        $service->linkResponse($outgoing, $incoming, $actor);

        $this->assertSame(1, $outgoing->outgoingRelations()
            ->where('target_record_id', $incoming->id)
            ->where('relation_type', 'responds_to')
            ->count());
    }

    public function test_external_party_cannot_reference_internal_identity(): void
    {
        [$actor, $group, $office] = $this->context('S4-EXTERNAL');

        $this->expectException(ValidationException::class);
        app(SecretariatCorrespondenceService::class)->createDraft(
            $office,
            $actor,
            'incoming',
            ['title' => 'ورودی نامعتبر', 'received_at' => now()],
            [
                [
                    'role' => 'sender',
                    'party_type' => 'external',
                    'user_id' => $actor->id,
                    'display_name' => 'طرف بیرونی جعلی',
                ],
                [
                    'role' => 'recipient',
                    'party_type' => 'group',
                    'group_id' => $group->id,
                    'display_name' => $group->name,
                ],
            ],
        );
    }

    private function incomingDraft(User $actor, Group $group, $office)
    {
        return app(SecretariatCorrespondenceService::class)->createDraft(
            $office,
            $actor,
            'incoming',
            [
                'title' => 'نامه مرجع',
                'received_at' => now(),
                'channel' => 'email',
            ],
            [
                [
                    'role' => 'sender',
                    'party_type' => 'external',
                    'display_name' => 'فرستنده بیرونی',
                    'email' => 'outside@example.test',
                ],
                [
                    'role' => 'recipient',
                    'party_type' => 'group',
                    'group_id' => $group->id,
                    'display_name' => $group->name,
                ],
            ],
        );
    }

    /** @return array{0:User,1:Group,2:mixed} */
    private function context(string $code): array
    {
        $actor = User::factory()->create();
        $group = Group::query()->create([
            'name' => 'Secretariat ' . $code,
            'group_type' => '0',
        ]);
        $office = app(SecretariatOfficeService::class)->create([
            'code' => $code,
            'name' => 'Secretariat Office ' . $code,
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $group->id,
        ]);

        return [$actor, $group, $office];
    }
}
