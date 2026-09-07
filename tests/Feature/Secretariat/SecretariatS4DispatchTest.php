<?php

namespace Tests\Feature\Secretariat;

use App\Models\Group;
use App\Models\User;
use App\Modules\Secretariat\Services\SecretariatCorrespondenceService;
use App\Modules\Secretariat\Services\SecretariatDispatchService;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class SecretariatS4DispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_formal_record_can_follow_controlled_internal_referral_lifecycle(): void
    {
        [$actor, $group, $office] = $this->context('S4-DISPATCH');
        $target = User::factory()->create();
        $record = $this->formalIncoming($actor, $group, $office);
        $service = app(SecretariatDispatchService::class);

        $dispatch = $service->create($record, $actor, [
            'dispatch_type' => 'referral',
            'channel' => 'internal',
            'target_user_id' => $target->id,
            'instructions' => 'بررسی و اقدام شود.',
        ]);

        $this->assertSame('pending', $dispatch->status);
        $dispatch = $service->transition($dispatch, 'sent', $actor);
        $this->assertNotNull($dispatch->dispatched_at);
        $dispatch = $service->transition($dispatch, 'received', $target);
        $this->assertNotNull($dispatch->received_at);
        $dispatch = $service->transition($dispatch, 'acknowledged', $target);
        $this->assertNotNull($dispatch->acknowledged_at);
        $dispatch = $service->transition($dispatch, 'completed', $target);
        $this->assertNotNull($dispatch->completed_at);

        $this->assertDatabaseHas('secretariat_audit_events', [
            'record_id' => $record->id,
            'event_type' => 'dispatch_created',
        ]);
        $this->assertSame(4, $record->auditEvents()->where('event_type', 'dispatch_status_changed')->count());
    }

    public function test_draft_record_cannot_be_dispatched(): void
    {
        [$actor, $group, $office] = $this->context('S4-DRAFT-DISPATCH');
        $target = User::factory()->create();
        $draft = $this->incomingDraft($actor, $group, $office);

        $this->expectException(ValidationException::class);
        app(SecretariatDispatchService::class)->create($draft, $actor, [
            'dispatch_type' => 'referral',
            'channel' => 'internal',
            'target_user_id' => $target->id,
        ]);
    }

    public function test_external_delivery_must_target_party_of_same_record(): void
    {
        [$actor, $group, $office] = $this->context('S4-TARGET');
        $first = $this->formalIncoming($actor, $group, $office);
        $secondDraft = $this->incomingDraft($actor, $group, $office);
        $records = app(SecretariatRecordService::class);
        $second = $records->register($records->submitForApproval($secondDraft, $actor), $actor);
        $foreignParty = $second->parties()->where('role', 'sender')->firstOrFail();

        $this->expectException(ValidationException::class);
        app(SecretariatDispatchService::class)->create($first, $actor, [
            'dispatch_type' => 'delivery',
            'channel' => 'email',
            'target_party_id' => $foreignParty->id,
        ]);
    }

    public function test_external_delivery_uses_record_party_snapshot_and_never_provider_specific_state(): void
    {
        [$actor, $group, $office] = $this->context('S4-EXT-DISPATCH');
        $record = $this->formalIncoming($actor, $group, $office);
        $party = $record->parties()->where('role', 'sender')->firstOrFail();
        $service = app(SecretariatDispatchService::class);

        $dispatch = $service->create($record, $actor, [
            'dispatch_type' => 'delivery',
            'channel' => 'email',
            'target_party_id' => $party->id,
            'external_reference_number' => 'MAIL-EXT-88',
        ]);

        $this->assertSame('pending', $dispatch->status);
        $this->assertSame('email', $dispatch->channel);
        $this->assertSame($party->id, $dispatch->target_party_id);
        $this->assertSame('MAIL-EXT-88', $dispatch->external_reference_number);
        $this->assertNull($dispatch->target_user_id);
    }

    public function test_dispatch_cannot_skip_lifecycle_states_or_be_mutated_directly(): void
    {
        [$actor, $group, $office] = $this->context('S4-LIFECYCLE');
        $target = User::factory()->create();
        $record = $this->formalIncoming($actor, $group, $office);
        $service = app(SecretariatDispatchService::class);
        $dispatch = $service->create($record, $actor, [
            'dispatch_type' => 'referral',
            'channel' => 'internal',
            'target_user_id' => $target->id,
        ]);

        try {
            $service->transition($dispatch, 'completed', $actor);
            $this->fail('Pending dispatch must not jump directly to completed.');
        } catch (ValidationException) {
            $this->assertSame('pending', $dispatch->fresh()->status);
        }

        $this->expectException(LogicException::class);
        $dispatch->update(['status' => 'sent']);
    }

    private function formalIncoming(User $actor, Group $group, $office)
    {
        $draft = $this->incomingDraft($actor, $group, $office);
        $records = app(SecretariatRecordService::class);
        return $records->register($records->submitForApproval($draft, $actor), $actor);
    }

    private function incomingDraft(User $actor, Group $group, $office)
    {
        return app(SecretariatCorrespondenceService::class)->createDraft(
            $office,
            $actor,
            'incoming',
            [
                'title' => 'نامه برای گردش',
                'received_at' => now(),
                'channel' => 'email',
            ],
            [
                [
                    'role' => 'sender',
                    'party_type' => 'external',
                    'display_name' => 'طرف بیرونی',
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
