<?php

namespace Tests\Feature\Secretariat;

use App\Models\User;
use App\Modules\Secretariat\Services\SecretariatAttachmentService;
use App\Modules\Secretariat\Services\SecretariatIntegrityService;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatOperationalHealthService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecretariatS9OperationalHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_reports_pending_scan_and_hold_metrics_without_storage_io(): void
    {
        [$actor, $office] = $this->fixture();
        Storage::fake('local');
        $records = app(SecretariatRecordService::class);

        $pending = $records->createDraft($office, $actor, [
            'record_type'=>'official_note','direction'=>'none','title'=>'Pending health record','body'=>'pending',
        ]);
        $records->submitForApproval($pending, $actor);

        $draft = $records->createDraft($office, $actor, [
            'record_type'=>'official_note','direction'=>'none','title'=>'Attachment health record','body'=>'attachment',
        ]);
        app(SecretariatAttachmentService::class)->upload(
            $draft,
            $actor,
            UploadedFile::fake()->createWithContent('health.txt', 'health payload'),
            disk: 'local'
        );

        $snapshot = app(SecretariatOperationalHealthService::class)->snapshot();

        $this->assertSame(2, $snapshot['records_total']);
        $this->assertSame(1, $snapshot['records_pending_approval']);
        $this->assertSame(1, $snapshot['attachments_total']);
        $this->assertSame(0, $snapshot['attachments_without_scan_evidence']);
        $this->assertSame(1, $snapshot['scan_unavailable']);
    }

    public function test_deep_audit_detects_missing_storage_object_and_manifest_drift(): void
    {
        [$actor, $office] = $this->fixture();
        Storage::fake('local');
        $records = app(SecretariatRecordService::class);

        $draftWithFile = $records->createDraft($office, $actor, [
            'record_type'=>'official_note','direction'=>'none','title'=>'Storage drift','body'=>'storage',
        ]);
        $attachment = app(SecretariatAttachmentService::class)->upload(
            $draftWithFile,
            $actor,
            UploadedFile::fake()->createWithContent('storage.txt', 'stored payload'),
            disk: 'local'
        );
        Storage::disk('local')->delete($attachment->storage_key);

        $formal = $records->createDraft($office, $actor, [
            'record_type'=>'official_note','direction'=>'none','title'=>'Integrity drift','body'=>'official body',
        ]);
        $formal = $records->register($records->submitForApproval($formal, $actor), $actor);
        $manifest = app(SecretariatIntegrityService::class)->generate($formal->currentVersion, $actor);

        // Simulate out-of-band database corruption. Normal Eloquent mutation is
        // intentionally blocked by the version aggregate.
        DB::table('secretariat_record_versions')->where('id', $formal->current_version_id)
            ->update(['content_checksum'=>str_repeat('f', 64)]);

        $audit = app(SecretariatOperationalHealthService::class)->deepAudit(100);

        $this->assertFalse($audit['healthy']);
        $this->assertContains(
            ['attachment_id'=>(int)$attachment->id,'issue'=>'missing_storage_object'],
            $audit['attachment_issues']
        );
        $manifestIssue = collect($audit['manifest_issues'])->firstWhere('manifest_id', $manifest->id);
        $this->assertNotNull($manifestIssue);
        $this->assertFalse($manifestIssue['current_version_matches']);
    }

    public function test_health_command_can_fail_ci_or_gameday_on_deep_integrity_issue(): void
    {
        [$actor, $office] = $this->fixture();
        Storage::fake('local');
        $record = app(SecretariatRecordService::class)->createDraft($office, $actor, [
            'record_type'=>'official_note','direction'=>'none','title'=>'Missing file','body'=>'body',
        ]);
        $attachment = app(SecretariatAttachmentService::class)->upload(
            $record,
            $actor,
            UploadedFile::fake()->createWithContent('missing.txt', 'payload'),
            disk: 'local'
        );
        Storage::disk('local')->delete($attachment->storage_key);

        $this->artisan('secretariat:health --deep --limit=20 --fail-on-issues')->assertExitCode(1);
    }

    private function fixture(): array
    {
        $actor = User::factory()->create(['is_admin'=>1]);
        $office = app(SecretariatOfficeService::class)->create([
            'code'=>'S9-HEALTH','name'=>'S9 Health Office','office_type'=>'central',
        ]);
        return [$actor, $office];
    }
}
