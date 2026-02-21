<?php

namespace App\Services\NajmHoda\Runtime;

use Illuminate\Support\Facades\Cache;

class NajmHodaOpsRetentionService
{
    protected string $summaryHistoryKey = 'najm_hoda:ops:run_summary_history';
    protected string $telemetryIndexKey = 'najm_hoda:ops:telemetry:index';

    public function __construct(
        protected RuntimeEventBus $eventBus
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function prune(): array
    {
        $historyTrimmed = $this->pruneSummaryHistory();
        $telemetryPruned = $this->pruneTelemetryIndex();

        $result = [
            'history_trimmed' => $historyTrimmed,
            'telemetry_keys_pruned' => $telemetryPruned,
        ];

        $this->eventBus->emit('najm_hoda.ops.retention.pruned', $result);

        return $result;
    }

    protected function pruneSummaryHistory(): int
    {
        $history = Cache::get($this->summaryHistoryKey, []);
        if (!is_array($history)) {
            $history = [];
        }

        $maxHistory = max(1, (int) config('najm-hoda.runtime.ops.monitor.summary_history_size', 50));
        $originalCount = count($history);
        $history = array_slice($history, 0, $maxHistory);

        $ttlMinutes = max(30, (int) config('najm-hoda.runtime.ops.monitor.summary_ttl_minutes', 180));
        Cache::put($this->summaryHistoryKey, $history, now()->addMinutes($ttlMinutes));

        return max(0, $originalCount - count($history));
    }

    protected function pruneTelemetryIndex(): int
    {
        $index = Cache::get($this->telemetryIndexKey, []);
        if (!is_array($index)) {
            $index = [];
        }

        $retentionHours = max(1, (int) config('najm-hoda.runtime.ops.retention.telemetry_index_retention_hours', 72));
        $cutoff = time() - ($retentionHours * 3600);
        $maxIndexSize = max(100, (int) config('najm-hoda.runtime.ops.retention.telemetry_index_max_size', 5000));

        $retained = [];
        $pruned = 0;

        foreach ($index as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $key = (string) ($entry['key'] ?? '');
            $createdAt = (int) ($entry['created_at'] ?? 0);
            if ($key === '') {
                continue;
            }

            if ($createdAt > 0 && $createdAt < $cutoff) {
                Cache::forget($key);
                $pruned++;
                continue;
            }

            $retained[] = [
                'key' => $key,
                'created_at' => $createdAt > 0 ? $createdAt : time(),
            ];
        }

        if (count($retained) > $maxIndexSize) {
            $extra = count($retained) - $maxIndexSize;
            $drop = array_slice($retained, 0, $extra);
            foreach ($drop as $entry) {
                Cache::forget((string) ($entry['key'] ?? ''));
            }
            $retained = array_slice($retained, $extra);
            $pruned += $extra;
        }

        Cache::put($this->telemetryIndexKey, $retained, now()->addHours($retentionHours + 24));

        return $pruned;
    }
}

