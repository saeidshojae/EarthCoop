<?php

namespace App\Jobs\Communication;

use App\Models\CommunicationRun;
use App\Models\User;
use App\Services\Communication\CommunicationAudienceRegistry;
use App\Services\Communication\CommunicationDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;

class ResolveCommunicationRunAudience implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $runId,
        public readonly int $chunkSize = 500,
        public readonly int $offset = 0,
    ) {
    }

    public function handle(CommunicationAudienceRegistry $audiences): void
    {
        $run = CommunicationRun::query()->with('rule.template')->find($this->runId);
        $rule = $run?->rule;
        if (! $run || ! $rule || ! $rule->is_active) {
            return;
        }

        $definition = (array) $rule->audience_definition;
        $audienceKey = trim((string) ($definition['key'] ?? ''));
        $audiences->get($audienceKey);

        if ($audienceKey !== 'specific.user') {
            throw new InvalidArgumentException('Scheduled audience resolver is not implemented for this registered audience.');
        }

        $ids = array_values(array_filter(
            array_unique(array_map('intval', (array) ($definition['user_ids'] ?? []))),
            fn (int $id): bool => $id > 0,
        ));
        $chunkSize = max(1, $this->chunkSize);

        if ($this->offset === 0) {
            for ($nextOffset = $chunkSize; $nextOffset < count($ids); $nextOffset += $chunkSize) {
                self::dispatch($run->id, $chunkSize, $nextOffset)->onQueue('communications-bulk');
            }
        }

        $chunkIds = array_slice($ids, $this->offset, $chunkSize);
        if ($chunkIds === []) {
            return;
        }

        $recipients = User::query()->whereIn('id', $chunkIds)->orderBy('id')->get();
        if ($recipients->isEmpty()) {
            return;
        }

        $communication = app(CommunicationDispatcher::class)->dispatch(
            $rule->template->key,
            ['type' => 'scheduled_rule', 'id' => (string) $rule->id],
            $recipients,
            [],
            [
                'priority' => (int) $rule->priority,
                'delivery_class' => 'bulk',
                'deduplication_key' => 'run:'.$run->id.':offset:'.$this->offset,
            ],
        );

        $communication->update([
            'communication_rule_id' => $rule->id,
            'communication_run_id' => $run->id,
        ]);

        $counts = $communication->recipients()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $run->increment('matched_count', $recipients->count());
        $run->increment('eligible_count', (int) ($counts['queued'] ?? 0));
        $run->increment('suppressed_count', (int) ($counts['suppressed'] ?? 0));
        $run->increment('invalid_count', (int) ($counts['invalid'] ?? 0));
        $run->increment('queued_count', (int) ($counts['queued'] ?? 0));
    }
}
