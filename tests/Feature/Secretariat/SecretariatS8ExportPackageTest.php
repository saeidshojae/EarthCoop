<?php

namespace Tests\Feature\Secretariat;

use App\Models\User;
use App\Modules\Secretariat\Services\SecretariatAttachmentService;
use App\Modules\Secretariat\Services\SecretariatExportPackageService;
use App\Modules\Secretariat\Services\SecretariatIntegrityService;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class SecretariatS8ExportPackageTest extends TestCase
{
    use RefreshDatabase;

    public function test_official_package_exports_version_manifest_and_verified_attachments(): void
    {
        Storage::fake('local');
        [$actor, $office] = $this->fixture();
        $records = app(SecretariatRecordService::class);

        $record = $records->createDraft($office, $actor, [
            'record_type' => 'official_report',
            'title' => 'گزارش قابل صدور',
            'body' => 'متن گزارش',
        ]);
        $attachment = app(SecretariatAttachmentService::class)->upload(
            $record,
            $actor,
            UploadedFile::fake()->createWithContent('evidence.txt', 'evidence-content'),
            $record->currentVersion,
            'local'
        );
        $record = $records->register($records->submitForApproval($record, $actor), $actor);
        $manifest = app(SecretariatIntegrityService::class)->generate($record->currentVersion, $actor);

        $package = app(SecretariatExportPackageService::class)->generate($manifest, $actor, 'local');
        $verification = app(SecretariatExportPackageService::class)->verify($package);

        Storage::disk('local')->assertExists($package->storage_key);
        $this->assertSame(64, strlen($package->package_checksum));
        $this->assertSame($manifest->id, $package->integrity_manifest_id);
        $this->assertSame($attachment->checksum, $package->package_manifest['attachments'][0]['checksum']);
        $this->assertTrue($verification['storage_present']);
        $this->assertTrue($verification['package_checksum_valid']);
        $this->assertTrue($verification['integrity_valid']);
    }

    public function test_missing_or_tampered_attachment_aborts_export_instead_of_creating_incomplete_package(): void
    {
        Storage::fake('local');
        [$actor, $office] = $this->fixture();
        $records = app(SecretariatRecordService::class);
        $record = $records->createDraft($office, $actor, [
            'record_type' => 'official_report',
            'title' => 'گزارش با شاهد',
        ]);
        $attachment = app(SecretariatAttachmentService::class)->upload(
            $record,
            $actor,
            UploadedFile::fake()->createWithContent('proof.txt', 'original-proof'),
            $record->currentVersion,
            'local'
        );
        $record = $records->register($records->submitForApproval($record, $actor), $actor);
        $manifest = app(SecretariatIntegrityService::class)->generate($record->currentVersion, $actor);

        Storage::disk('local')->put($attachment->storage_key, 'tampered-proof');

        try {
            app(SecretariatExportPackageService::class)->generate($manifest, $actor, 'local');
            $this->fail('Tampered attachment was exported.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('secretariat_export_packages', 0);
        }
    }

    public function test_export_package_row_is_immutable(): void
    {
        Storage::fake('local');
        [$actor, $office] = $this->fixture();
        $records = app(SecretariatRecordService::class);
        $record = $records->createDraft($office, $actor, [
            'record_type' => 'official_note',
            'title' => 'یادداشت قابل بسته‌بندی',
        ]);
        $record = $records->register($records->submitForApproval($record, $actor), $actor);
        $manifest = app(SecretariatIntegrityService::class)->generate($record->currentVersion, $actor);
        $package = app(SecretariatExportPackageService::class)->generate($manifest, $actor, 'local');

        $this->expectException(LogicException::class);
        $package->package_checksum = str_repeat('0', 64);
        $package->save();
    }

    private function fixture(): array
    {
        $actor = User::factory()->create(['is_admin' => 1]);
        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'S8-EXPORT',
            'name' => 'S8 Export Office',
            'office_type' => 'central',
        ]);
        return [$actor, $office];
    }
}
