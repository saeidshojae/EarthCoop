<?php

namespace Tests\Feature\Secretariat;

use App\Models\User;
use App\Modules\Secretariat\Contracts\SecretariatMalwareScanner;
use App\Modules\Secretariat\Models\SecretariatAttachmentScan;
use App\Modules\Secretariat\Services\SecretariatAttachmentService;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class SecretariatS9AttachmentHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_side_size_limit_rejects_before_persistence(): void
    {
        [$actor, $record] = $this->fixture();
        Storage::fake('local');
        config(['secretariat.attachments.max_bytes' => 1024]);

        $this->expectException(ValidationException::class);
        try {
            app(SecretariatAttachmentService::class)->upload(
                $record,
                $actor,
                UploadedFile::fake()->create('too-large.txt', 2, 'text/plain'),
                disk: 'local'
            );
        } finally {
            $this->assertDatabaseCount('secretariat_attachments', 0);
            $this->assertDatabaseCount('secretariat_attachment_scans', 0);
        }
    }

    public function test_mime_allowlist_uses_server_detected_type_and_rejects_disallowed_payload(): void
    {
        [$actor, $record] = $this->fixture();
        Storage::fake('local');
        config(['secretariat.attachments.allowed_mime_types' => ['application/pdf']]);

        $this->expectException(ValidationException::class);
        try {
            app(SecretariatAttachmentService::class)->upload(
                $record,
                $actor,
                UploadedFile::fake()->createWithContent('payload.php', '<?php echo "unsafe";'),
                disk: 'local'
            );
        } finally {
            $this->assertDatabaseCount('secretariat_attachments', 0);
        }
    }

    public function test_infected_scan_rejects_and_never_persists_attachment(): void
    {
        [$actor, $record] = $this->fixture();
        Storage::fake('local');
        $this->app->instance(SecretariatMalwareScanner::class, new class implements SecretariatMalwareScanner {
            public function scan(string $path, ?string $mimeType = null): array
            {
                return ['status'=>'infected','engine'=>'test-scanner','signature'=>'EICAR-Test','metadata'=>[]];
            }
        });

        $this->expectException(ValidationException::class);
        try {
            app(SecretariatAttachmentService::class)->upload(
                $record,
                $actor,
                UploadedFile::fake()->createWithContent('unsafe.txt', 'EICAR-like-test-content'),
                disk: 'local'
            );
        } finally {
            $this->assertDatabaseCount('secretariat_attachments', 0);
            $this->assertDatabaseCount('secretariat_attachment_scans', 0);
        }
    }

    public function test_unconfigured_scanner_is_recorded_as_unavailable_not_clean(): void
    {
        [$actor, $record] = $this->fixture();
        Storage::fake('local');

        $attachment = app(SecretariatAttachmentService::class)->upload(
            $record,
            $actor,
            UploadedFile::fake()->createWithContent('allowed.txt', 'ordinary secretariat text'),
            disk: 'local'
        );

        $scan = SecretariatAttachmentScan::query()->where('attachment_id', $attachment->id)->firstOrFail();
        $this->assertSame('unavailable', $scan->status);
        $this->assertSame('unconfigured', $scan->engine);
        $this->assertNotSame('clean', $scan->status);
    }

    public function test_clean_scanner_evidence_is_append_only_and_draft_cleanup_still_works(): void
    {
        [$actor, $record] = $this->fixture();
        Storage::fake('local');
        $this->app->instance(SecretariatMalwareScanner::class, new class implements SecretariatMalwareScanner {
            public function scan(string $path, ?string $mimeType = null): array
            {
                return ['status'=>'clean','engine'=>'test-scanner','signature'=>null,'metadata'=>['version'=>'1']];
            }
        });

        $service = app(SecretariatAttachmentService::class);
        $attachment = $service->upload(
            $record,
            $actor,
            UploadedFile::fake()->createWithContent('clean.txt', 'clean secretariat text'),
            disk: 'local'
        );
        $scan = SecretariatAttachmentScan::query()->where('attachment_id', $attachment->id)->firstOrFail();
        $this->assertSame('clean', $scan->status);

        try {
            $scan->status = 'unavailable';
            $scan->save();
            $this->fail('Scan evidence was mutable.');
        } catch (LogicException) {
            $this->assertSame('clean', $scan->fresh()->status);
        }

        $service->deleteDraftAttachment($attachment->fresh());
        $this->assertDatabaseMissing('secretariat_attachments', ['id'=>$attachment->id]);
        $this->assertDatabaseMissing('secretariat_attachment_scans', ['id'=>$scan->id]);
    }

    private function fixture(): array
    {
        $actor = User::factory()->create(['is_admin'=>1]);
        $office = app(SecretariatOfficeService::class)->create([
            'code'=>'S9-UPLOAD', 'name'=>'S9 Upload Office', 'office_type'=>'central',
        ]);
        $record = app(SecretariatRecordService::class)->createDraft($office, $actor, [
            'record_type'=>'official_note', 'direction'=>'none', 'title'=>'Upload hardening fixture', 'body'=>'fixture',
        ]);
        return [$actor, $record];
    }
}
