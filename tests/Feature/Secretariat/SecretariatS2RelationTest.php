<?php

namespace Tests\Feature\Secretariat;

use App\Models\User;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use App\Modules\Secretariat\Services\SecretariatRelationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class SecretariatS2RelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_relation_is_directed_idempotent_and_audited(): void
    {
        [$actor, $office, $records] = $this->context();
        $source = $records->createDraft($office, $actor, ['record_type' => 'resolution', 'title' => 'Resolution']);
        $target = $records->createDraft($office, $actor, ['record_type' => 'execution_record', 'title' => 'Execution']);

        $relations = app(SecretariatRelationService::class);
        $first = $relations->add($target, $source, 'implements', $actor);
        $second = $relations->add($target, $source, 'implements', $actor);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($target->id, $first->source_record_id);
        $this->assertSame($source->id, $first->target_record_id);
        $this->assertSame(1, $target->outgoingRelations()->count());
        $this->assertSame(1, $source->incomingRelations()->count());
        $this->assertDatabaseHas('secretariat_audit_events', [
            'record_id' => $target->id,
            'event_type' => 'relation_added',
        ]);
    }

    public function test_self_relation_and_cross_office_relation_are_rejected_in_s2(): void
    {
        [$actor, $office, $records] = $this->context();
        $record = $records->createDraft($office, $actor, ['record_type' => 'official_note', 'title' => 'A']);

        try {
            app(SecretariatRelationService::class)->add($record, $record, 'related_to', $actor);
            $this->fail('Self relation was accepted.');
        } catch (ValidationException) {
            $this->assertSame(0, $record->outgoingRelations()->count());
        }

        $otherOffice = app(SecretariatOfficeService::class)->create([
            'code' => 'S2-REL-OTHER',
            'name' => 'Other Office',
            'office_type' => 'central',
        ]);
        $other = $records->createDraft($otherOffice, $actor, ['record_type' => 'official_note', 'title' => 'B']);

        $this->expectException(ValidationException::class);
        app(SecretariatRelationService::class)->add($record, $other, 'related_to', $actor);
    }

    public function test_relation_involving_registered_record_cannot_be_hard_deleted(): void
    {
        [$actor, $office, $records] = $this->context();
        $source = $records->createDraft($office, $actor, ['record_type' => 'resolution', 'title' => 'Formal source']);
        $target = $records->createDraft($office, $actor, ['record_type' => 'official_report', 'title' => 'Target']);
        $relation = app(SecretariatRelationService::class)->add($source, $target, 'refers_to', $actor);

        $records->register($records->submitForApproval($source, $actor), $actor);

        $this->expectException(LogicException::class);
        $relation->refresh()->delete();
    }

    private function context(): array
    {
        $actor = User::factory()->create();
        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'S2-REL',
            'name' => 'S2 Relation Office',
            'office_type' => 'central',
        ]);

        return [$actor, $office, app(SecretariatRecordService::class)];
    }
}
