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

        DB::table('legal_publication_deliveries as delivery')
            ->join('legal_document_versions as version', 'version.id', '=', 'delivery.legal_document_version_id')
            ->where('version.status', 'published')
            ->whereIn('version.publication_policy', ['notify', 'material'])
            ->where(function ($query) use ($staleBefore): void {
                $query->whereIn('delivery.status', ['queued', 'failed'])
                    ->orWhere(function ($query) use ($staleBefore): void {
                        $query->where('delivery.status', 'sending')
                            ->where('delivery.updated_at', '<=', $staleBefore);
                    });
            })
            ->where('delivery.attempts', '<', 5)
            ->orderBy('delivery.id')
            ->limit($limit)
            ->select(['delivery.legal_document_version_id', 'delivery.user_id', 'delivery.channel'])
            ->get()
            ->each(function ($delivery) use (&$requeued): void {
                DeliverLegalPublicationNotice::dispatch(
                    (int) $delivery->legal_document_version_id,
                    (int) $delivery->user_id,
                    (string) $delivery->channel
                )->onQueue('notifications');
                $requeued++;
            });

        $this->info("Requeued {$requeued} legal publication deliveries.");

        return self::SUCCESS;
    }
}
