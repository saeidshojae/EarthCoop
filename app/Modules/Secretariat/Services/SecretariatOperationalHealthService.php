<?php

namespace App\Modules\Secretariat\Services;

use App\Modules\Secretariat\Models\SecretariatAttachment;
use App\Modules\Secretariat\Models\SecretariatAttachmentScan;
use App\Modules\Secretariat\Models\SecretariatDispatch;
use App\Modules\Secretariat\Models\SecretariatIntegrityManifest;
use App\Modules\Secretariat\Models\SecretariatLegalHold;
use App\Modules\Secretariat\Models\SecretariatRecord;
use Illuminate\Support\Facades\Storage;

class SecretariatOperationalHealthService
{
    public function __construct(private readonly SecretariatIntegrityService $integrity) {}

    /**
     * Cheap DB-only production snapshot. Safe for frequent health/metrics polling.
     * No object-storage calls and no per-record integrity verification occur here.
     *
     * @return array<string,int>
     */
    public function snapshot(): array
    {
        $now = now();

        return [
            'records_total' => SecretariatRecord::query()->count(),
            'records_pending_approval' => SecretariatRecord::query()->where('status', 'pending_approval')->count(),
            'records_formal' => SecretariatRecord::query()->whereNotNull('registry_number')->count(),
            'attachments_total' => SecretariatAttachment::query()->count(),
            'attachments_without_scan_evidence' => SecretariatAttachment::query()
                ->whereNotExists(function ($query) {
                    $query->selectRaw('1')->from('secretariat_attachment_scans as sas_health')
                        ->whereColumn('sas_health.attachment_id', 'secretariat_attachments.id');
                })->count(),
            'scan_unavailable' => SecretariatAttachmentScan::query()->where('status', 'unavailable')->count(),
            'scan_errors' => SecretariatAttachmentScan::query()->where('status', 'error')->count(),
            'integrity_manifests' => SecretariatIntegrityManifest::query()->count(),
            'active_legal_holds' => SecretariatLegalHold::query()->whereNull('released_at')->count(),
            'dispatches_overdue' => SecretariatDispatch::query()
                ->whereIn('status', ['pending', 'sent', 'received'])
                ->whereNotNull('due_at')->where('due_at', '<', $now)->count(),
            'dispatches_follow_up_due' => SecretariatDispatch::query()
                ->whereIn('status', ['pending', 'sent', 'received'])
                ->whereNotNull('follow_up_at')->where('follow_up_at', '<=', $now)->count(),
        ];
    }

    /**
     * Bounded deep audit intended for scheduled/admin diagnostics, not per-request polling.
     *
     * @return array<string,mixed>
     */
    public function deepAudit(int $limit = 200): array
    {
        $limit = max(1, min(1000, $limit));

        $attachmentIssues = [];
        $attachments = SecretariatAttachment::query()->orderBy('id')->limit($limit)->get();
        foreach ($attachments as $attachment) {
            $disk = Storage::disk($attachment->storage_disk);
            if (! $disk->exists($attachment->storage_key)) {
                $attachmentIssues[] = [
                    'attachment_id' => (int) $attachment->id,
                    'issue' => 'missing_storage_object',
                ];
                continue;
            }

            $contents = $disk->get($attachment->storage_key);
            if (! hash_equals((string) $attachment->checksum, hash('sha256', $contents))) {
                $attachmentIssues[] = [
                    'attachment_id' => (int) $attachment->id,
                    'issue' => 'checksum_mismatch',
                ];
            }
        }

        $manifestIssues = [];
        $manifests = SecretariatIntegrityManifest::query()->orderBy('id')->limit($limit)->get();
        foreach ($manifests as $manifest) {
            $verification = $this->integrity->verify($manifest);
            if (! $verification['stored_payload_valid'] || ! $verification['current_version_matches']) {
                $manifestIssues[] = [
                    'manifest_id' => (int) $manifest->id,
                    'record_version_id' => (int) $manifest->record_version_id,
                    'stored_payload_valid' => (bool) $verification['stored_payload_valid'],
                    'current_version_matches' => (bool) $verification['current_version_matches'],
                ];
            }
        }

        return [
            'limit' => $limit,
            'attachments_checked' => $attachments->count(),
            'attachment_issues' => $attachmentIssues,
            'manifests_checked' => $manifests->count(),
            'manifest_issues' => $manifestIssues,
            'healthy' => $attachmentIssues === [] && $manifestIssues === [],
        ];
    }
}
