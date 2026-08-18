<?php

namespace Tests\Feature\Secretariat;

use App\Models\User;
use App\Modules\Secretariat\Services\SecretariatAttachmentService;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

class SecretariatS2AttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_attachment_is_stored_checksummed_version_pinned_and_audited(): void
    {
        Storage::fake('local');
        [$actor, $record] = $this->draft();

        $file = UploadedFile::fake()->createWithContent('evidence.txt', 'earthcoop-secretariat-evidence');
        $attachment = app(SecretariatAttachmentService::class)->upload($record, $actor, $file, null, 'local');

        $this->assertSame($record->id, $attachment->record_id);
        $this->assertSame($record->current_version_id, $attachment->version_id);
        $this->assertSame(hash('sha256', 'earthcoop-secretariat-evidence'), $attachment->checksum);
        Storage::disk('local')->assertExists($attachment->storage_key);

        $this->assertDatabaseHas('secretariat_audit_events', [
            'record_id' => $record->id,
            'event_type' => 'attachment_added',
        ]);
    }

    public function test_formal_version_cannot_receive_retroactive_attachment_or_hard_delete_existing_attachment(): void
    {
        Storage::fake('local');
        [$actor, $record, $records] = $this->draft(true);

        $attachment = app(SecretariatAttachmentService::class)->upload(
            $record,
            $actor,
            UploadedFile::fake()->createWithContent('before.txt', 'before registration'),
            null,
            'local'
        );

        $registered = $records->register($records->submitForApproval($record, $actor), $actor);

        try {
            app(SecretariatAttachmentService::class)->upload(
                $registered,
                $actor,
                UploadedFile::fake()->createWithContent('after.txt', 'retroactive'),
                null,
                'local'
            );
            $this->fail('Retroactive attachment to an official version was accepted.');
        } catch (LogicException) {
            $this->assertDatabaseMissing('secretariat_attachments', ['original_name' => 'after.txt']);
        }

        $this->expectException(LogicException::class);
        $attachment->refresh()->delete();
    }

    /** @return array{0:User,1:\App\Modules\Secretariat\Models\SecretariatRecord,2?:SecretariatRecordService} */
    private function draft(bool $withService = false): array
    {
        $actor = User::factory()->create();
        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'S2-ATTACH',
            'name' => 'S2 Attachment Office',
            'office_type' => 'central',
        ]);
        $records = app(SecretariatRecordService::class);
        $record = $records->createDraft($office, $actor, [
            'record_type' => 'official_report',
            'title' => 'Attachment record',
        ]);

        return $withService ? [$actor, $record, $records] : [$actor, $record];
    }
}
