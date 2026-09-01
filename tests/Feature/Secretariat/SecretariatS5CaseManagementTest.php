<?php

namespace Tests\Feature\Secretariat;

use App\Models\Group;
use App\Models\User;
use App\Modules\Secretariat\Services\SecretariatCaseService;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class SecretariatS5CaseManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_case_collects_formal_records_without_copying_record_truth(): void
    {
        [$actor, $office] = $this->office('CASE-A');
        $record = $this->formalRecord($office, $actor, 'Evidence A');
        $case = app(SecretariatCaseService::class)->create($office, $actor, ['title' => 'Water dispute case']);

        app(SecretariatCaseService::class)->addRecord($case, $record, $actor, 'evidence');

        $this->assertSame($record->id, $case->fresh()->records()->firstOrFail()->id);
        $this->assertDatabaseHas('secretariat_case_records', ['case_id' => $case->id, 'record_id' => $record->id, 'role' => 'evidence']);
    }

    public function test_case_numbers_use_independent_office_case_sequences_not_database_ids(): void
    {
        [$actorA, $officeA] = $this->office('CASE-SEQ-A');
        [$actorB, $officeB] = $this->office('CASE-SEQ-B');
        $service = app(SecretariatCaseService::class);
        $year = (int) now()->year;

        $a1 = $service->create($officeA, $actorA, ['title' => 'A1']);
        $b1 = $service->create($officeB, $actorB, ['title' => 'B1']);
        $a2 = $service->create($officeA, $actorA, ['title' => 'A2']);

        $this->assertSame("CASE-SEQ-A/{$year}/CASE/000001", $a1->case_number);
        $this->assertSame("CASE-SEQ-B/{$year}/CASE/000001", $b1->case_number);
        $this->assertSame("CASE-SEQ-A/{$year}/CASE/000002", $a2->case_number);

        $this->assertDatabaseHas('secretariat_sequences', [
            'office_id' => $officeA->id,
            'calendar_year' => $year,
            'record_family' => 'CASE',
            'last_value' => 2,
        ]);
        $this->assertDatabaseHas('secretariat_sequences', [
            'office_id' => $officeB->id,
            'calendar_year' => $year,
            'record_family' => 'CASE',
            'last_value' => 1,
        ]);
    }

    public function test_case_rejects_draft_and_cross_office_records(): void
    {
        [$actor, $officeA] = $this->office('CASE-B1');
        [, $officeB] = $this->office('CASE-B2');
        $case = app(SecretariatCaseService::class)->create($officeA, $actor, ['title' => 'Case']);
        $draft = app(SecretariatRecordService::class)->createDraft($officeA, $actor, ['record_type' => 'official_note', 'direction' => 'internal', 'title' => 'Draft']);

        try {
            app(SecretariatCaseService::class)->addRecord($case, $draft, $actor);
            $this->fail('Draft record entered a formal case.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $foreign = $this->formalRecord($officeB, $actor, 'Foreign record');
        $this->expectException(ValidationException::class);
        app(SecretariatCaseService::class)->addRecord($case, $foreign, $actor);
    }

    public function test_archived_case_is_immutable_container_for_new_membership(): void
    {
        [$actor, $office] = $this->office('CASE-C');
        $case = app(SecretariatCaseService::class)->create($office, $actor, ['title' => 'Closed matter']);
        app(SecretariatCaseService::class)->transition($case, 'closed', $actor);
        app(SecretariatCaseService::class)->transition($case->fresh(), 'archived', $actor);
        $record = $this->formalRecord($office, $actor, 'Late evidence');

        $this->expectException(ValidationException::class);
        app(SecretariatCaseService::class)->addRecord($case->fresh(), $record, $actor);
    }

    public function test_case_direct_mutation_and_hard_delete_are_blocked(): void
    {
        [$actor, $office] = $this->office('CASE-D');
        $case = app(SecretariatCaseService::class)->create($office, $actor, ['title' => 'Protected case']);

        try {
            $case->status = 'closed';
            $case->save();
            $this->fail('Direct case mutation was allowed.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }

        $this->expectException(LogicException::class);
        $case->fresh()->delete();
    }

    private function formalRecord($office, User $actor, string $title)
    {
        $service = app(SecretariatRecordService::class);
        $record = $service->createDraft($office, $actor, ['record_type' => 'official_note', 'direction' => 'internal', 'title' => $title]);
        $service->submitForApproval($record, $actor);
        return $service->register($record->fresh(), $actor);
    }

    private function office(string $code): array
    {
        $actor = User::factory()->create();
        $group = Group::query()->create(['name' => $code, 'group_type' => '0']);
        $office = app(SecretariatOfficeService::class)->create(['code' => $code, 'name' => $code, 'office_type' => 'group', 'scope_type' => 'group', 'scope_id' => $group->id]);
        return [$actor, $office];
    }
}
