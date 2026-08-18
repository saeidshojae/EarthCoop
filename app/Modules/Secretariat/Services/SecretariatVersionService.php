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
        bool $audit = true,
        bool $makeCurrent = true,
    ): SecretariatRecordVersion {
        return DB::transaction(function () use ($record, $actor, $content, $changeReason, $audit, $makeCurrent) {
            /** @var SecretariatRecord $locked */
            $locked = SecretariatRecord::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            // Do not use $locked->versions() here: that relationship intentionally
            // carries ASC ordering for read/display purposes. Appending a revision
            // must resolve the latest persisted version with an independent query,
            // otherwise inherited ordering can make repeated amendments allocate
            // the same version number.
            $base = SecretariatRecordVersion::query()
                ->where('record_id', $locked->id)
                ->orderByDesc('version_number')
                ->first() ?? $locked->currentVersion;

            $last = (int) ($base?->version_number ?? 0);
            $snapshot = [
                'title' => (string) ($content['title'] ?? $base?->title ?? $locked->title),
                'subject' => array_key_exists('subject', $content) ? $content['subject'] : ($base?->subject ?? $locked->subject),
                'summary' => array_key_exists('summary', $content) ? $content['summary'] : ($base?->summary ?? $locked->summary),
                'body' => array_key_exists('body', $content) ? $content['body'] : $base?->body,
            ];

            $version = $locked->versions()->create([
                'version_number' => $last + 1,
                ...$snapshot,
                'change_reason' => $changeReason,
                'created_by' => $actor->id,
                'content_checksum' => hash('sha256', $this->canonicalPayload($snapshot)),
                'is_official' => false,
            ]);

            if ($makeCurrent) {
                $this->applyVersionSnapshot($locked, $version);
            }

            if ($audit) {
                $this->audit->append($locked->office, $locked, $actor, 'version_created', [
                    'version_number' => $version->version_number,
                    'change_reason' => $changeReason,
                    'checksum' => $version->content_checksum,
                    'made_current' => $makeCurrent,
                ]);
            }

            return $version;
        });
    }

    public function markOfficial(SecretariatRecordVersion $version, User $actor, bool $makeCurrent = true): SecretariatRecordVersion
    {
        return DB::transaction(function () use ($version, $actor, $makeCurrent) {
            /** @var SecretariatRecordVersion $lockedVersion */
            $lockedVersion = SecretariatRecordVersion::query()
                ->with('record.office')
                ->whereKey($version->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($makeCurrent) {
                $latestNumber = (int) SecretariatRecordVersion::query()
                    ->where('record_id', $lockedVersion->record_id)
                    ->max('version_number');

                if ((int) $lockedVersion->version_number !== $latestNumber) {
                    throw new LogicException('A stale Secretariat version cannot supersede a newer amendment.');
                }
            }

            if (! $lockedVersion->is_official) {
                $lockedVersion->performOfficialPromotion(function (SecretariatRecordVersion $target) use ($actor): void {
                    $target->forceFill([
                        'is_official' => true,
                        'approved_by' => $actor->id,
                        'approved_at' => now(),
                    ])->save();
                });
            }

            if ($makeCurrent) {
                /** @var SecretariatRecord $record */
                $record = SecretariatRecord::query()->whereKey($lockedVersion->record_id)->lockForUpdate()->firstOrFail();
                $this->applyVersionSnapshot($record, $lockedVersion);
            }

            return $lockedVersion->refresh();
        });
    }

    private function applyVersionSnapshot(SecretariatRecord $record, SecretariatRecordVersion $version): void
    {
        $record->performControlledMutation(function (SecretariatRecord $target) use ($version): void {
            $target->forceFill([
                'title' => $version->title,
                'subject' => $version->subject,
                'summary' => $version->summary,
                'current_version_id' => $version->id,
            ])->save();
        });
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
