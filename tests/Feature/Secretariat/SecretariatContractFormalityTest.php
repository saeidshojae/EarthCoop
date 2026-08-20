<?php

namespace Tests\Feature\Secretariat;

use App\Models\Group;
use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatContractSignatory;
use App\Modules\Secretariat\Models\SecretariatContractVersionDetail;
use App\Modules\Secretariat\Services\SecretariatContractService;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class SecretariatContractFormalityTest extends TestCase
{
    use RefreshDatabase;

    public function test_incomplete_contract_cannot_be_promoted_to_formal_registry(): void
    {
        [$actor, $office] = $this->context();
        $records = app(SecretariatRecordService::class);
        $record = $records->createDraft($office, $actor, [
            'record_type' => 'contract',
            'title' => 'Incomplete contract',
            'body' => 'Terms',
        ]);
        $submitted = $records->submitForApproval($record, $actor);

        try {
            $records->register($submitted, $actor);
            $this->fail('Incomplete contract was registered.');
        } catch (LogicException) {
            $this->assertNull($record->refresh()->registry_number);
            $this->assertFalse((bool) $record->currentVersion->refresh()->is_official);
        }
    }

    public function test_complete_contract_registers_with_versioned_terms_and_signatory_snapshot(): void
    {
        [$actor, $office] = $this->context();
        [$record, $party] = $this->draftCompleteContract($actor, $office);
        $records = app(SecretariatRecordService::class);

        $registered = $records->register($records->submitForApproval($record, $actor), $actor);
        $version = $registered->currentVersion->fresh();

        $this->assertTrue((bool) $version->is_official);
        $this->assertNotNull($registered->registry_number);
        $this->assertDatabaseHas('secretariat_contract_version_details', [
            'record_version_id' => $version->id,
            'renewal_mode' => 'manual',
            'renewal_notice_days' => 30,
            'governing_law' => 'EarthCoop Charter',
        ]);
        $this->assertDatabaseHas('secretariat_contract_signatories', [
            'record_version_id' => $version->id,
            'party_id' => $party->id,
            'capacity' => 'Authorized representative',
            'signing_order' => 1,
        ]);
        $this->assertDatabaseHas('secretariat_audit_events', [
            'record_id' => $registered->id,
            'event_type' => 'contract_version_details_saved',
        ]);
        $this->assertDatabaseHas('secretariat_audit_events', [
            'record_id' => $registered->id,
            'event_type' => 'contract_signatory_saved',
        ]);
    }

    public function test_official_contract_terms_and_signatories_are_immutable(): void
    {
        [$actor, $office] = $this->context();
        [$record] = $this->draftCompleteContract($actor, $office);
        $records = app(SecretariatRecordService::class);
        $registered = $records->register($records->submitForApproval($record, $actor), $actor);
        $versionId = $registered->current_version_id;

        $detail = SecretariatContractVersionDetail::query()->where('record_version_id', $versionId)->firstOrFail();
        try {
            $detail->forceFill(['jurisdiction' => 'Tampered'])->save();
            $this->fail('Official contract metadata was mutable.');
        } catch (LogicException) {
            $this->assertNotSame('Tampered', $detail->fresh()->jurisdiction);
        }

        $signatory = SecretariatContractSignatory::query()->where('record_version_id', $versionId)->firstOrFail();
        $this->expectException(LogicException::class);
        $signatory->forceFill(['capacity' => 'Tampered'])->save();
    }

    public function test_amendment_gets_new_terms_and_signatories_without_rewriting_previous_official_version(): void
    {
        [$actor, $office] = $this->context();
        [$record, $partyV1] = $this->draftCompleteContract($actor, $office);
        $records = app(SecretariatRecordService::class);
        $contracts = app(SecretariatContractService::class);
        $registered = $records->register($records->submitForApproval($record, $actor), $actor);
        $v1 = $registered->currentVersion->fresh();
        $v1Detail = SecretariatContractVersionDetail::query()->where('record_version_id', $v1->id)->firstOrFail();
        $v1Signatory = SecretariatContractSignatory::query()->where('record_version_id', $v1->id)->firstOrFail();

        $v2 = $records->createAmendment($registered, $actor, [
            'title' => 'Service agreement — amendment 1',
            'body' => 'Revised terms',
        ], 'Extend term and add representative');

        $newParty = $contracts->addParty($registered->fresh(), $actor, [
            'party_type' => 'external',
            'display_name' => 'Second Representative',
            'organization_name' => 'Example Partner',
            'email' => 'second@example.org',
        ]);
        $contracts->putVersionDetails($v2, $actor, [
            'effective_at' => '2027-01-01 00:00:00',
            'expires_at' => '2029-01-01 00:00:00',
            'renewal_mode' => 'automatic',
            'renewal_notice_days' => 60,
            'governing_law' => 'EarthCoop Charter v2',
            'jurisdiction' => 'EarthCoop',
        ]);
        $contracts->addSignatory($v2, $newParty, $actor, [
            'capacity' => 'Amendment representative',
            'signing_order' => 1,
        ]);

        $updated = $records->approveAmendment($v2, $actor);
        $this->assertSame($v2->id, $updated->current_version_id);
        $this->assertTrue((bool) $v2->fresh()->is_official);

        $this->assertSame('manual', $v1Detail->fresh()->renewal_mode);
        $this->assertSame(30, (int) $v1Detail->fresh()->renewal_notice_days);
        $this->assertSame($partyV1->id, (int) $v1Signatory->fresh()->party_id);
        $this->assertSame('Authorized representative', $v1Signatory->fresh()->capacity);

        $this->assertDatabaseHas('secretariat_contract_version_details', [
            'record_version_id' => $v2->id,
            'renewal_mode' => 'automatic',
            'renewal_notice_days' => 60,
        ]);
        $this->assertDatabaseHas('secretariat_contract_signatories', [
            'record_version_id' => $v2->id,
            'party_id' => $newParty->id,
            'capacity' => 'Amendment representative',
        ]);
    }

    public function test_incomplete_amendment_cannot_become_official(): void
    {
        [$actor, $office] = $this->context();
        [$record] = $this->draftCompleteContract($actor, $office);
        $records = app(SecretariatRecordService::class);
        $registered = $records->register($records->submitForApproval($record, $actor), $actor);
        $v1Id = $registered->current_version_id;

        $v2 = $records->createAmendment($registered, $actor, [
            'title' => 'Incomplete amendment',
            'body' => 'Changed text without formality metadata',
        ], 'Incomplete on purpose');

        try {
            $records->approveAmendment($v2, $actor);
            $this->fail('Incomplete amendment became official.');
        } catch (LogicException) {
            $this->assertFalse((bool) $v2->refresh()->is_official);
            $this->assertSame($v1Id, $registered->refresh()->current_version_id);
        }
    }

    public function test_contract_service_rejects_cross_record_signatory_and_invalid_terms(): void
    {
        [$actor, $office] = $this->context();
        $contracts = app(SecretariatContractService::class);
        $records = app(SecretariatRecordService::class);

        [$recordA] = $this->draftCompleteContract($actor, $office, 'Contract A');
        [$recordB, $partyB] = $this->draftCompleteContract($actor, $office, 'Contract B');

        try {
            $contracts->addSignatory($recordA->currentVersion, $partyB, $actor, ['capacity' => 'Wrong record']);
            $this->fail('Cross-record signatory was accepted.');
        } catch (ValidationException) {
            $this->assertSame(1, SecretariatContractSignatory::query()->where('record_version_id', $recordA->current_version_id)->count());
        }

        $note = $records->createDraft($office, $actor, ['record_type' => 'official_note', 'title' => 'Not a contract']);
        try {
            $contracts->putVersionDetails($note->currentVersion, $actor, ['renewal_mode' => 'none']);
            $this->fail('Non-contract accepted contract metadata.');
        } catch (ValidationException) {
            $this->assertDatabaseMissing('secretariat_contract_version_details', ['record_version_id' => $note->current_version_id]);
        }

        $this->expectException(ValidationException::class);
        $contracts->putVersionDetails($recordB->currentVersion, $actor, [
            'effective_at' => '2028-01-01 00:00:00',
            'expires_at' => '2027-01-01 00:00:00',
            'renewal_mode' => 'none',
        ]);
    }

    public function test_nonrenewing_contract_cannot_have_renewal_notice(): void
    {
        [$actor, $office] = $this->context();
        $record = app(SecretariatRecordService::class)->createDraft($office, $actor, [
            'record_type' => 'agreement',
            'title' => 'Agreement',
        ]);

        $this->expectException(ValidationException::class);
        app(SecretariatContractService::class)->putVersionDetails($record->currentVersion, $actor, [
            'renewal_mode' => 'none',
            'renewal_notice_days' => 30,
        ]);
    }

    private function draftCompleteContract(User $actor, $office, string $title = 'Service agreement'): array
    {
        $records = app(SecretariatRecordService::class);
        $contracts = app(SecretariatContractService::class);
        $record = $records->createDraft($office, $actor, [
            'record_type' => 'contract',
            'title' => $title,
            'body' => 'Version one terms',
        ]);

        $party = $contracts->addParty($record, $actor, [
            'party_type' => 'external',
            'display_name' => 'Example Partner Representative',
            'organization_name' => 'Example Partner',
            'email' => 'partner@example.org',
        ]);
        $contracts->putVersionDetails($record->currentVersion, $actor, [
            'effective_at' => '2027-01-01 00:00:00',
            'expires_at' => '2028-01-01 00:00:00',
            'renewal_mode' => 'manual',
            'renewal_notice_days' => 30,
            'governing_law' => 'EarthCoop Charter',
            'jurisdiction' => 'EarthCoop',
        ]);
        $contracts->addSignatory($record->currentVersion, $party, $actor, [
            'capacity' => 'Authorized representative',
            'title' => 'Director',
            'signing_order' => 1,
        ]);

        return [$record, $party];
    }

    private function context(): array
    {
        // These are positive-path formality tests. The actor must be genuinely
        // authorized now that S9 enforces authorization inside the domain service.
        $actor = User::factory()->create(['is_admin' => 1]);
        $group = Group::query()->create(['name' => 'S8 Contract Office', 'group_type' => '0']);
        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'S8-CONTRACT-' . $group->id,
            'name' => 'S8 Contract Office',
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $group->id,
        ]);

        return [$actor, $office];
    }
}
