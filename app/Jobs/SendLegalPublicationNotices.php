<?php

namespace App\Jobs;

use App\Models\LegalDocumentVersion;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SendLegalPublicationNotices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $versionId)
    {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $version = LegalDocumentVersion::with('document')->find($this->versionId);
        if (! $version || $version->status !== 'published' || ! in_array($version->publication_policy, ['notify', 'material'], true)) {
            return;
        }

        User::query()->where('is_system', false)->select(['id', 'email'])->chunkById(200, function ($users) use ($version): void {
            foreach ($users as $user) {
                foreach (['in_app', 'email'] as $channel) {
                    DB::table('legal_publication_deliveries')->insertOrIgnore([
                        'legal_document_version_id' => $version->id,
                        'user_id' => $user->id,
                        'channel' => $channel,
                        'status' => $channel === 'email' && blank($user->email) ? 'skipped' : 'queued',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    if ($channel === 'email' && blank($user->email)) {
                        continue;
                    }

                    DeliverLegalPublicationNotice::dispatch($version->id, (int) $user->id, $channel)
                        ->onQueue('notifications');
                }
            }
        });
    }
}
