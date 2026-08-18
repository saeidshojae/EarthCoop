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

    public function test_formal_record_fields_cannot_be_overwritten_directly(): void
    {
        [$actor, $record] = $this->registeredPolicy();

        $this->expectException(LogicException::class);
        $record->forceFill(['title' => 'silent overwrite'])->save();
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
