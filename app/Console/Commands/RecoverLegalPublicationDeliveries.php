<?php

namespace App\Console\Commands;

use App\Jobs\DeliverLegalPublicationNotice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecoverLegalPublicationDeliveries extends Command
{
    protected $signature = 'legal:recover-publication-deliveries {--limit=200}';

    protected $description = 'Requeue pending or stale legal publication notices';

    public function handle(): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $staleBefore = now()->subSeconds(60);
        $requeued = 0;

        $candidates = DB::table('legal_publication_deliveries as delivery')
            ->join('legal_document_versions as version', 'version.id', '=', 'delivery.legal_document_version_id')
            ->where('version.status', 'published')
            ->whereIn('version.publication_policy', ['notify', 'material'])
            ->where(function ($query) use ($staleBefore): void {
                $query->where(function ($query) use ($staleBefore): void {
                    $query->whereIn('delivery.status', ['queued', 'failed'])
                        ->where('delivery.updated_at', '<=', $staleBefore);
                })->orWhere(function ($query) use ($staleBefore): void {
                    $query->where('delivery.status', 'sending')
                        ->where('delivery.updated_at', '<=', $staleBefore);
                });
            })
            ->where('delivery.attempts', '<', 5)
            ->orderBy('delivery.id')
            ->limit($limit)
            ->select(['delivery.id', 'delivery.legal_document_version_id', 'delivery.user_id', 'delivery.channel', 'delivery.status', 'delivery.updated_at'])
            ->get();

        foreach ($candidates as $delivery) {
            // Atomically acquire the recovery lease before dispatch. This avoids
            // re-enqueuing the same queued/failed row every five-minute cycle.
            $updated = DB::table('legal_publication_deliveries')
                ->where('id', $delivery->id)
                ->where('status', $delivery->status)
                ->where('updated_at', $delivery->updated_at)
                ->where('attempts', '<', 5)
                ->update([
                    'status' => 'queued',
                    'updated_at' => now(),
                ]);

            if ($updated !== 1) {
                continue;
            }

            try {
                DeliverLegalPublicationNotice::dispatch(
                    (int) $delivery->legal_document_version_id,
                    (int) $delivery->user_id,
                    (string) $delivery->channel
                )->onQueue('notifications');
                $requeued++;
            } catch (\Throwable $exception) {
                // Leave the row queued with a fresh timestamp; the recovery
                // command will retry dispatch after the lease interval.
                report($exception);
            }
        }

        $this->info("Requeued {$requeued} legal publication deliveries.");

        return self::SUCCESS;
    }
}
