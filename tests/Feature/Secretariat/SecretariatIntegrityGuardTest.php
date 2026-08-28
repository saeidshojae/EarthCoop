<?php

namespace Tests\Feature\Secretariat;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatAuditEvent;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class SecretariatIntegrityGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_content_cannot_bypass_version_service(): void
    {
        $actor = User::factory()->create();
        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'DRAFT-GUARD',
            'name' => 'Draft Guard Office',
            'office_type' => 'central',
        ]);
        $record = app(SecretariatRecordService::class)->createDraft($office, $actor, [
            'record_type' => 'official_note',
            'title' => 'Draft v1',
        ]);

        try {
            $record->forceFill(['title' => 'silent draft overwrite'])->save();
            $this->fail('Direct draft content mutation was accepted.');
        } catch (LogicException) {
            $this->assertSame('Draft v1', $record->fresh()->title);
            $this->assertSame(1, $record->fresh()->versions()->count());
        }
    }

    public function test_pending_record_cannot_jump_to_registered_without_registration_service(): void
    {
        $actor = User::factory()->create();
        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'REGISTER-GUARD',
            'name' => 'Registration Guard Office',
            'office_type' => 'central',
        ]);
        $service = app(SecretariatRecordService::class);
        $pending = $service->submitForApproval($service->createDraft($office, $actor, [
            'record_type' => 'official_note',
            'title' => 'Pending note',
        ]), $actor);

        try {
            $pending->forceFill(['status' => 'registered'])->save();
            $this->fail('Direct registration-state mutation was accepted.');
        } catch (LogicException) {
            $pending = $pending->fresh();
            $this->assertSame('pending_approval', $pending->status);
            $this->assertNull($pending->registry_number);
            $this->assertFalse($pending->currentVersion->is_official);
        }
    }

    public function test_formal_record_fields_cannot_be_overwritten_directly(): void
    {
        [, $record] = $this->registeredPolicy();

        $this->expectException(LogicException::class);
        $record->forceFill(['title' => 'silent overwrite'])->save();
    }

    public function test_formal_lifecycle_cannot_bypass_transition_service(): void
    {
        [, $record] = $this->registeredPolicy();

        try {
            $record->forceFill(['status' => 'archived'])->save();
            $this->fail('Direct lifecycle mutation was accepted.');
        } catch (LogicException) {
            $this->assertSame('registered', $record->fresh()->status);
        }
    }

    public function test_formal_current_version_pointer_cannot_be_changed_directly(): void
    {
        [$actor, $record, $service] = $this->registeredPolicy(true);
        $officialVersionId = $record->current_version_id;
        $pending = $service->createAmendment($record, $actor, ['title' => 'Pending v2'], 'pending');

        try {
            $record->refresh()->forceFill(['current_version_id' => $pending->id])->save();
            $this->fail('Direct current-version pointer mutation was accepted.');
        } catch (LogicException) {
            $this->assertSame($officialVersionId, $record->fresh()->current_version_id);
            $this->assertFalse($pending->fresh()->is_official);
        }
    }

    public function test_pending_version_cannot_be_overwritten_or_self_promoted(): void
    {
        [$actor, $record, $service] = $this->registeredPolicy(true);
        $pending = $service->createAmendment($record, $actor, ['title' => 'Pending v2'], 'pending');

        try {
            $pending->forceFill(['title' => 'silently rewritten'])->save();
            $this->fail('Pending version overwrite was accepted.');
        } catch (LogicException) {
            $this->assertSame('Pending v2', $pending->fresh()->title);
        }

        try {
            $pending->refresh()->forceFill([
                'is_official' => true,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ])->save();
            $this->fail('Direct official promotion was accepted.');
        } catch (LogicException) {
            $this->assertFalse($pending->fresh()->is_official);
        }

        $this->expectException(LogicException::class);
        $pending->refresh()->delete();
    }

    public function test_audit_events_cannot_be_updated_or_deleted_through_model(): void
    {
        [, $record] = $this->registeredPolicy();
        $event = SecretariatAuditEvent::query()->where('record_id', $record->id)->firstOrFail();

        try {
            $event->forceFill(['event_type' => 'rewritten'])->save();
            $this->fail('Audit update was accepted.');
        } catch (LogicException) {
            $this->assertDatabaseHas('secretariat_audit_events', [
                'id' => $event->id,
                'event_type' => 'created',
            ]);
        }

        $this->expectException(LogicException::class);
        $event->delete();
    }

    public function test_older_pending_amendment_cannot_supersede_a_newer_one(): void
    {
        [$actor, $record, $service] = $this->registeredPolicy(true);

        $v2 = $service->createAmendment($record, $actor, ['title' => 'Policy v2'], 'v2');
        $v3 = $service->createAmendment($record, $actor, ['title' => 'Policy v3'], 'v3');

        $this->assertFalse($v2->is_official);
        $this->assertFalse($v3->is_official);

        $this->expectException(LogicException::class);
        $service->approveAmendment($v2, $actor);
    }

    public function test_new_pending_amendment_builds_on_latest_pending_revision(): void
    {
        [$actor, $record, $service] = $this->registeredPolicy(true);

        $v2 = $service->createAmendment($record, $actor, [
            'title' => 'Policy v2',
            'summary' => 'Summary from v2',
        ], 'v2');

        $v3 = $service->createAmendment($record, $actor, [
            'body' => 'Body from v3',
        ], 'v3');

        $this->assertSame('Policy v2', $v3->title);
        $this->assertSame('Summary from v2', $v3->summary);
        $this->assertSame('Body from v3', $v3->body);
        $this->assertSame($v2->version_number + 1, $v3->version_number);
    }

    /**
     * @return array{0:User,1:\App\Modules\Secretariat\Models\SecretariatRecord,2?:SecretariatRecordService}
     */
    private function registeredPolicy(bool $withService = false): array
    {
        $actor = User::factory()->create();
        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'CENTRAL',
            'name' => 'Central',
            'office_type' => 'central',
        ]);
        $service = app(SecretariatRecordService::class);
        $record = $service->createDraft($office, $actor, [
            'record_type' => 'policy',
            'title' => 'Policy v1',
        ]);
        $record = $service->register($service->submitForApproval($record, $actor), $actor);

        return $withService ? [$actor, $record, $service] : [$actor, $record];
    }
}
