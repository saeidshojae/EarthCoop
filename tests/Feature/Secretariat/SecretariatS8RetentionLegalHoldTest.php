<?php

namespace Tests\Feature\Secretariat;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatLegalHold;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use App\Modules\Secretariat\Services\SecretariatRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class SecretariatS8RetentionLegalHoldTest extends TestCase
{
    use RefreshDatabase;

    public function test_retention_is_versioned_by_assignment_sequence_and_never_authorizes_purge(): void
    {
        [$actor, $record] = $this->formalRecord();
        $service = app(SecretariatRetentionService::class);

        $first = $service->assign($record, $actor, [
            'disposition' => 'review',
            'retention_until' => now()->subDay(),
            'policy_reference' => 'RET-1',
        ]);
        $second = $service->assign($record, $actor, [
            'disposition' => 'eligible_for_disposition',
            'retention_until' => now()->subHour(),
            'policy_reference' => 'RET-2',
        ]);
        $assessment = $service->assess($record);

        $this->assertSame(1, $first->assignment_sequence);
        $this->assertSame(2, $second->assignment_sequence);
        $this->assertTrue($assessment['retention_elapsed']);
        $this->assertTrue($assessment['eligible_for_disposition']);
        $this->assertFalse($assessment['purge_authorized']);

        $this->expectException(LogicException::class);
        $second->disposition = 'preserve';
        $second->save();
    }

    public function test_active_legal_hold_blocks_disposition_until_explicit_release(): void
    {
        [$actor, $record] = $this->formalRecord();
        $service = app(SecretariatRetentionService::class);
        $service->assign($record, $actor, [
            'disposition' => 'eligible_for_disposition',
            'retention_until' => now()->subDay(),
        ]);
        $hold = $service->placeHold($record, $actor, [
            'hold_reference' => 'CASE-LEGAL-7',
            'reason' => 'Litigation preservation',
        ]);

        $held = $service->assess($record);
        $this->assertSame(1, $held['active_hold_count']);
        $this->assertFalse($held['eligible_for_disposition']);
        $this->assertFalse($held['purge_authorized']);

        $released = $service->releaseHold($hold, $actor, 'Matter concluded');
        $after = $service->assess($record);

        $this->assertNotNull($released->released_at);
        $this->assertSame(0, $after['active_hold_count']);
        $this->assertTrue($after['eligible_for_disposition']);
        $this->assertFalse($after['purge_authorized']);
    }

    public function test_legal_hold_is_controlled_and_cannot_be_deleted_or_directly_released(): void
    {
        [$actor, $record] = $this->formalRecord();
        $hold = app(SecretariatRetentionService::class)->placeHold($record, $actor, [
            'reason' => 'Preserve for audit',
        ]);

        try {
            $hold->released_by = $actor->id;
            $hold->released_at = now();
            $hold->save();
            $this->fail('Direct legal hold release was allowed.');
        } catch (LogicException) {
            $this->assertNull($hold->fresh()->released_at);
        }

        $this->expectException(LogicException::class);
        $hold->fresh()->delete();
    }

    public function test_retention_and_hold_cannot_be_applied_to_draft_records(): void
    {
        $actor = User::factory()->create(['is_admin' => 1]);
        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'S8-RET-DRAFT',
            'name' => 'S8 Retention Draft Office',
            'office_type' => 'central',
        ]);
        $draft = app(SecretariatRecordService::class)->createDraft($office, $actor, [
            'record_type' => 'official_note',
            'title' => 'Draft note',
        ]);

        $this->expectException(ValidationException::class);
        app(SecretariatRetentionService::class)->assign($draft, $actor, ['disposition' => 'preserve']);
    }

    private function formalRecord(): array
    {
        $actor = User::factory()->create(['is_admin' => 1]);
        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'S8-RET-' . $actor->id,
            'name' => 'S8 Retention Office',
            'office_type' => 'central',
        ]);
        $records = app(SecretariatRecordService::class);
        $record = $records->createDraft($office, $actor, [
            'record_type' => 'official_report',
            'title' => 'Retention test record',
        ]);
        $record = $records->register($records->submitForApproval($record, $actor), $actor);
        return [$actor, $record];
    }
}
