<?php

namespace App\Modules\Secretariat\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SecretariatLegacyAssessmentService
{
    /** @return array<string,mixed> */
    public function assess(int $limit = 1000): array
    {
        $limit = max(1, min(10000, $limit));

        $legacyFiles = [
            'table_present' => Schema::hasTable('files'),
            'row_count' => 0,
            'importable_count' => 0,
            'decision' => 'not_a_secretariat_source',
            'reason' => 'Legacy files has no owner, storage path, MIME or provenance contract.',
        ];
        if ($legacyFiles['table_present']) {
            $legacyFiles['row_count'] = DB::table('files')->count();
        }

        $ticket = [
            'table_present' => Schema::hasTable('ticket_attachments'),
            'row_count' => 0,
            'assessed_count' => 0,
            'candidate_count' => 0,
            'missing_storage_count' => 0,
            'missing_ticket_count' => 0,
            'missing_uploader_count' => 0,
            'decision' => 'assessment_only',
            'storage_disk' => 'public',
            'candidates' => [],
        ];

        if (! $ticket['table_present']) {
            return ['legacy_files' => $legacyFiles, 'ticket_attachments' => $ticket];
        }

        $ticket['row_count'] = DB::table('ticket_attachments')->count();
        $rows = DB::table('ticket_attachments')
            ->orderBy('id')
            ->limit($limit)
            ->get();
        $ticket['assessed_count'] = $rows->count();
        $disk = Storage::disk('public');

        foreach ($rows as $row) {
            $ticketExists = $row->ticket_id !== null
                && DB::table('tickets')->where('id', $row->ticket_id)->exists();
            $uploaderExists = $row->uploaded_by === null
                || DB::table('users')->where('id', $row->uploaded_by)->exists();
            $storagePresent = is_string($row->file_path ?? null)
                && trim((string) $row->file_path) !== ''
                && $disk->exists((string) $row->file_path);

            if (! $ticketExists) {
                $ticket['missing_ticket_count']++;
            }
            if (! $uploaderExists) {
                $ticket['missing_uploader_count']++;
            }
            if (! $storagePresent) {
                $ticket['missing_storage_count']++;
            }

            $candidate = $ticketExists
                && $uploaderExists
                && $storagePresent
                && trim((string) ($row->file_name ?? '')) !== '';

            if ($candidate) {
                $ticket['candidate_count']++;
                $ticket['candidates'][] = [
                    'ticket_attachment_id' => (int) $row->id,
                    'ticket_id' => (int) $row->ticket_id,
                    'comment_id' => $row->comment_id === null ? null : (int) $row->comment_id,
                    'uploaded_by' => $row->uploaded_by === null ? null : (int) $row->uploaded_by,
                    'file_name' => (string) $row->file_name,
                    'file_path' => (string) $row->file_path,
                    'mime_type' => $row->mime_type,
                    'file_size' => $row->file_size === null ? null : (int) $row->file_size,
                ];
            }
        }

        return ['legacy_files' => $legacyFiles, 'ticket_attachments' => $ticket];
    }
}
