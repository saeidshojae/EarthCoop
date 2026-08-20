<?php

namespace Tests\Feature\Secretariat;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatParty;
use App\Modules\Secretariat\Services\SecretariatContractService;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class SecretariatS8ContractFormalityTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_registration_requires_version_details_and_signatory_snapshot(): void
    {
        [$actor, $office] = $this->fixture();
        $records = app(SecretariatRecordService::class);
        $contracts = app(SecretariatContractService::class);

        $record = $records->createDraft($office, $actor, [
            'record_type' => 'contract',
            'direction' => 'none',
            'title' => 'قرارداد خدمات فنی',
            'body' => 'متن نسخه نخست قرارداد',
        ]);
        $version = $record->currentVersion;
        $record = $records->submitForApproval($record, $actor);

        try {
            $records->register($record, $actor);
            $this->fail('Contract registered without S8 formality metadata.');
        } catch (LogicException) {
            $this->assertNull($record->fresh()->registry_number);
        }

        $record = $records->returnToDraft($record->fresh(), $actor, 'Complete contract formality');
        $party = $this->externalParty($record->id, $actor->id, 'شرکت نمونه');

        $contracts->putVersionDetails($version->fresh(), $actor, [
            'effective_at' => '2026-09-01 00:00:00',
            'expires_at' => '2027-09-01 00:00:00',
            'renewal_mode' => 'manual',
            'renewal_notice_days' => 30,
            'governing_law' => 'قانون حاکم توافق‌شده',
            'jurisdiction' => 'مرجع توافق‌شده',
        ]);
        $contracts->addSignatory($version->fresh(), $party, $actor, [
            'capacity' => 'نماینده مجاز',
            'title' => 'مدیرعامل',
            'signing_order' => 1,
        ]);

        $registered = $records->register($records->submitForApproval($record->fresh(), $actor), $actor);

        $this->assertSame('registered', $registered->status);
        $this->assertTrue($registered->currentVersion->is_official);
        $this->assertSame('manual', $registered->currentVersion->contractDetails->renewal_mode);
        $this->assertCount(1, $registered->currentVersion->contractSignatories);
    }

    public function test_official_contract_formality_is_immutable_and_amendment_gets_its_own_snapshot(): void
    {
        [$actor, $office] = $this->fixture();
        $records = app(SecretariatRecordService::class);
        $contracts = app(SecretariatContractService::class);

        $record = $records->createDraft($office, $actor, [
            'record_type' => 'memorandum_of_understanding',
            'direction' => 'none',
            'title' => 'تفاهم‌نامه همکاری',
            'body' => 'نسخه اول',
        ]);
        $v1 = $record->currentVersion;
        $party = $this->externalParty($record->id, $actor->id, 'نهاد همکار');
        $contracts->putVersionDetails($v1, $actor, [
            'effective_at' => '2026-09-01',
            'expires_at' => '2027-09-01',
            'renewal_mode' => 'none',
        ]);
        $contracts->addSignatory($v1, $party, $actor, ['capacity' => 'نماینده طرف دوم']);
        $record = $records->register($records->submitForApproval($record, $actor), $actor);

        try {
            $detail = $record->currentVersion->contractDetails;
            $detail->renewal_mode = 'automatic';
            $detail->save();
            $this->fail('Official contract metadata was mutable.');
        } catch (LogicException) {
            $this->assertSame('none', $record->currentVersion->contractDetails->fresh()->renewal_mode);
        }

        $v2 = $records->createAmendment($record, $actor, [
            'title' => 'تفاهم‌نامه همکاری - الحاقیه',
            'body' => 'نسخه دوم با تمدید',
        ], 'تمدید مدت');

        try {
            $records->approveAmendment($v2, $actor);
            $this->fail('Contract amendment became official without its own formality snapshot.');
        } catch (LogicException) {
            $this->assertFalse($v2->fresh()->is_official);
        }

        $contracts->putVersionDetails($v2->fresh(), $actor, [
            'effective_at' => '2027-09-01',
            'expires_at' => '2028-09-01',
            'renewal_mode' => 'manual',
            'renewal_notice_days' => 45,
        ]);
        $contracts->addSignatory($v2->fresh(), $party, $actor, [
            'capacity' => 'نماینده طرف دوم',
            'signing_order' => 1,
        ]);

        $updated = $records->approveAmendment($v2->fresh(), $actor);

        $this->assertSame($v2->id, $updated->current_version_id);
        $this->assertSame('none', $v1->fresh()->contractDetails->renewal_mode);
        $this->assertSame('manual', $v2->fresh()->contractDetails->renewal_mode);
        $this->assertSame(45, (int) $v2->fresh()->contractDetails->renewal_notice_days);
    }

    public function test_contract_formality_metadata_is_rejected_for_non_contract_records(): void
    {
        [$actor, $office] = $this->fixture();
        $record = app(SecretariatRecordService::class)->createDraft($office, $actor, [
            'record_type' => 'official_note',
            'title' => 'یادداشت عادی',
        ]);

        $this->expectException(ValidationException::class);
        app(SecretariatContractService::class)->putVersionDetails($record->currentVersion, $actor, [
            'renewal_mode' => 'none',
        ]);
    }

    private function externalParty(int $recordId, int $actorId, string $name): SecretariatParty
    {
        return SecretariatParty::query()->create([
            'record_id' => $recordId,
            'role' => 'other',
            'party_type' => 'external',
            'display_name' => $name,
            'created_by' => $actorId,
        ]);
    }

    private function fixture(): array
    {
        $actor = User::factory()->create(['is_admin' => 1]);
        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'S8-CENTRAL',
            'name' => 'S8 Central Secretariat',
            'office_type' => 'central',
        ]);
        return [$actor, $office];
    }
}
