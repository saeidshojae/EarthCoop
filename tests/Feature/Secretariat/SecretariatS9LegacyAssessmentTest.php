<?php

namespace Tests\Feature\Secretariat;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Modules\Secretariat\Services\SecretariatLegacyAssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecretariatS9LegacyAssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_files_are_never_treated_as_importable_and_ticket_attachments_require_provenance_and_storage(): void
    {
        Storage::fake('public');
        DB::table('files')->insert(['created_at' => now(), 'updated_at' => now()]);

        $user = User::factory()->create();
        $ticket = Ticket::query()->create([
            'user_id' => $user->id,
            'tracking_code' => 'TK-S9LEGACY',
            'subject' => 'Legacy evidence',
            'message' => 'Legacy ticket attachment assessment fixture.',
            'status' => 'open',
        ]);

        Storage::disk('public')->put('tickets/attachments/good.txt', 'good evidence');
        TicketAttachment::query()->create([
            'ticket_id' => $ticket->id,
            'file_name' => 'good.txt',
            'file_path' => 'tickets/attachments/good.txt',
            'file_type' => 'text/plain',
            'file_size' => 13,
            'mime_type' => 'text/plain',
            'uploaded_by' => $user->id,
        ]);
        TicketAttachment::query()->create([
            'ticket_id' => $ticket->id,
            'file_name' => 'missing.txt',
            'file_path' => 'tickets/attachments/missing.txt',
            'file_type' => 'text/plain',
            'file_size' => 10,
            'mime_type' => 'text/plain',
            'uploaded_by' => $user->id,
        ]);

        $result = app(SecretariatLegacyAssessmentService::class)->assess();

        $this->assertSame('not_a_secretariat_source', $result['legacy_files']['decision']);
        $this->assertSame(0, $result['legacy_files']['importable_count']);
        $this->assertSame(1, $result['legacy_files']['row_count']);

        $this->assertSame(2, $result['ticket_attachments']['row_count']);
        $this->assertSame(1, $result['ticket_attachments']['candidate_count']);
        $this->assertSame(1, $result['ticket_attachments']['missing_storage_count']);
        $this->assertSame('good.txt', $result['ticket_attachments']['candidates'][0]['file_name']);
    }

    public function test_legacy_assessment_command_is_read_only_and_json_capable(): void
    {
        $beforeSecretariat = DB::table('secretariat_records')->count();

        $this->artisan('secretariat:legacy-assess --json')
            ->expectsOutputToContain('not_a_secretariat_source')
            ->assertExitCode(0);

        $this->assertSame($beforeSecretariat, DB::table('secretariat_records')->count());
    }
}
