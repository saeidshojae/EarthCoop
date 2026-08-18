<?php

namespace App\Modules\Secretariat\Services;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Models\SecretariatRecordVersion;
use Illuminate\Support\Facades\DB;
use LogicException;

class SecretariatVersionService
{
    public function __construct(private readonly SecretariatAuditService $audit)
    {
    }

    public function append(
        SecretariatRecord $record,
        User $actor,
        array $content,
        ?string $changeReason = null,
        bool $audit = true
    ): SecretariatRecordVersion {
        return DB::transaction(function () use ($record, $actor, $content, $changeReason, $audit) {
            /** @var SecretariatRecord $locked */
            $locked = SecretariatRecord::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            $last = (int) $locked->versions()->max('version_number');
            $snapshot = [
                'title' => (string) ($content['title'] ?? $locked->title),
                'subject' => $content['subject'] ?? $locked->subject,
                'summary' => $content['summary'] ?? $locked->summary,
                'body' => $content['body'] ?? null,
            ];

            $version = $locked->versions()->create([
                'version_number' => $last + 1,
                ...$snapshot,
                'change_reason' => $changeReason,
                'created_by' => $actor->id,
                'content_checksum' => hash('sha256', $this->canonicalPayload($snapshot)),
                'is_official' => false,
            ]);

            $locked->forceFill([
                'title' => $snapshot['title'],
                'subject' => $snapshot['subject'],
                'summary' => $snapshot['summary'],
                'current_version_id' => $version->id,
            ])->save();

            if ($audit) {
                $this->audit->append($locked->office, $locked, $actor, 'version_created', [
                    'version_number' => $version->version_number,
                    'change_reason' => $changeReason,
                    'checksum' => $version->content_checksum,
                ]);
            }

            return $version;
        });
    }

    public function markOfficial(SecretariatRecordVersion $version, User $actor): SecretariatRecordVersion
    {
        if ($version->is_official) {
            return $version;
        }

        if ($version->record->current_version_id !== $version->id) {
            throw new LogicException('Only the current Secretariat version can become official.');
        }

        $version->forceFill([
            'is_official' => true,
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ])->save();

        return $version->refresh();
    }

    private function canonicalPayload(array $snapshot): string
    {
        return json_encode([
            'title' => $snapshot['title'] ?? null,
            'subject' => $snapshot['subject'] ?? null,
            'summary' => $snapshot['summary'] ?? null,
            'body' => $snapshot['body'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
