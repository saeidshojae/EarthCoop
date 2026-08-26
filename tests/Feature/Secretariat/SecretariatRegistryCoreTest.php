<?php

namespace Tests\Feature\Secretariat;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatAuditEvent;
use App\Modules\Secretariat\Models\SecretariatRecordVersion;
use App\Modules\Secretariat\Models\SecretariatSequence;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class SecretariatRegistryCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_numbered_audited_and_idempotent(): void
    {
        $actor = User::factory()->create();
        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'CENTRAL',
            'name' => 'EarthCoop Central Secretariat',
            'office_type' => 'central',
        ]);

        $service = app(SecretariatRecordService::class);
        $record = $service->createDraft($office, $actor, [
            'record_type' => 'resolution',
            'title' => 'Initial resolution',
            'body' => 'Version one body',
        ]);

        $this->assertSame('draft', $record->status);
        $this->assertSame(1, $record->versions()->count());
        $this->assertNotNull($record->currentVersion->content_checksum);

        $record = $service->submitForApproval($record, $actor);
        $registered = $service->register($record, $actor);
        $retried = $service->register($registered, $actor);

        $this->assertSame('registered', $registered->status);
        $this->assertSame('CENTRAL/' . now()->year . '/GOV/000001', $registered->registry_number);
        $this->assertSame($registered->registry_number, $retried->registry_number);
        $this->assertSame(1, (int) SecretariatSequence::query()->value('last_value'));
        $this->assertTrue($registered->currentVersion->is_official);

        $active = $service->transition($registered, 'active', $actor);
        $lateRetry = $service->register($active, $actor);
        $this->assertSame('active', $lateRetry->status);
        $this->assertSame($registered->registry_number, $lateRetry->registry_number);
        $this->assertSame(1, (int) SecretariatSequence::query()->value('last_value'));

        $events = SecretariatAuditEvent::query()
            ->where('record_id', $registered->id)
            ->pluck('event_type')
            ->all();

        $this->assertContains('created', $events);
        $this->assertContains('submitted_for_approval', $events);
        $this->assertContains('approved', $events);
        $this->assertContains('registered', $events);
        $this->assertContains('activated', $events);
        $this->assertSame(1, SecretariatAuditEvent::query()->where('record_id', $registered->id)->where('event_type', 'registered')->count());
    }

    public function test_sequences_are_independent_by_office_and_increment_within_scope(): void
    {
        $actor = User::factory()->create();
        $offices = app(SecretariatOfficeService::class);
        $records = app(SecretariatRecordService::class);

        $a = $offices->create(['code' => 'A', 'name' => 'Office A', 'office_type' => 'central']);
        $b = $offices->create(['code' => 'B', 'name' => 'Office B', 'office_type' => 'central']);

        $register = function ($office, string $title) use ($actor, $records) {
            $record = $records->createDraft($office, $actor, [
                'record_type' => 'official_report',
                'title' => $title,
            ]);
            return $records->register($records->submitForApproval($record, $actor), $actor);
        };

        $a1 = $register($a, 'A1');
        $a2 = $register($a, 'A2');
        $b1 = $register($b, 'B1');

        $this->assertSame(1, (int) $a1->registry_sequence);
        $this->assertSame(2, (int) $a2->registry_sequence);
        $this->assertSame(1, (int) $b1->registry_sequence);
    }

    public function test_amendment_does_not_replace_current_official_version_before_approval(): void
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
            'body' => 'Original',
        ]);
        $record = $service->register($service->submitForApproval($record, $actor), $actor);
        $v1Id = $record->current_version_id;

        $v2 = $service->createAmendment($record, $actor, [
            'title' => 'Policy v2',
            'body' => 'Amended',
        ], 'Clarification');

        $record->refresh();
        $this->assertSame($v1Id, $record->current_version_id);
        $this->assertSame('Policy v1', $record->title);
        $this->assertFalse($v2->is_official);

        $record = $service->approveAmendment($v2, $actor);
        $this->assertSame($v2->id, $record->current_version_id);
        $this->assertSame('Policy v2', $record->title);
        $this->assertTrue($v2->refresh()->is_official);

        /** @var SecretariatRecordVersion $v1 */
        $v1 = SecretariatRecordVersion::query()->findOrFail($v1Id);
        $this->expectException(LogicException::class);
        $v1->forceFill(['title' => 'tampered'])->save();
    }

    public function test_forbidden_transitions_and_hard_delete_are_rejected(): void
    {
        $actor = User::factory()->create();
        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'CENTRAL',
            'name' => 'Central',
            'office_type' => 'central',
        ]);
        $service = app(SecretariatRecordService::class);

        $draft = $service->createDraft($office, $actor, [
            'record_type' => 'official_note',
            'title' => 'Note',
        ]);

        try {
            $service->transition($draft, 'archived', $actor);
            $this->fail('Forbidden transition was accepted.');
        } catch (LogicException) {
            $this->assertSame('draft', $draft->refresh()->status);
        }

        $registered = $service->register($service->submitForApproval($draft, $actor), $actor);

        $this->expectException(LogicException::class);
        $service->deleteDraft($registered);
    }
}
