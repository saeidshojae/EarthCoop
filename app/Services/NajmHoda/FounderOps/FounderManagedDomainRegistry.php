<?php

namespace App\Services\NajmHoda\FounderOps;

use Illuminate\Support\Arr;

class FounderManagedDomainRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $domains = config('najm-hoda-founder-operations.domains', []);

        if (! is_array($domains)) {
            return [];
        }

        $normalized = [];
        foreach ($domains as $key => $domain) {
            if (! is_string($key) || ! is_array($domain)) {
                continue;
            }

            $normalized[$key] = $this->normalize($key, $domain);
        }

        uasort($normalized, static function (array $a, array $b): int {
            $priority = ((int) $b['priority']) <=> ((int) $a['priority']);
            return $priority !== 0 ? $priority : strcmp((string) $a['key'], (string) $b['key']);
        });

        return $normalized;
    }

    public function get(string $key): ?array
    {
        $domains = $this->all();
        return $domains[$key] ?? null;
    }

    /**
     * Coverage is deliberately contract-level, not a claim that every action is
     * autonomous. It tells the founder what has been inventoried and how far each
     * domain has entered the common management pipeline.
     *
     * @return array<string, mixed>
     */
    public function coverage(): array
    {
        $domains = $this->all();
        $counts = [
            'total' => count($domains),
            'planned' => 0,
            'mapped' => 0,
            'observed' => 0,
            'managed' => 0,
        ];

        foreach ($domains as $domain) {
            $stage = (string) ($domain['integration_stage'] ?? 'planned');
            if (array_key_exists($stage, $counts)) {
                $counts[$stage]++;
            }
        }

        $integrated = $counts['observed'] + $counts['managed'];
        $coveragePercent = $counts['total'] > 0
            ? round(($integrated / $counts['total']) * 100, 2)
            : 0.0;

        return [
            'counts' => $counts,
            'integration_coverage_percent' => $coveragePercent,
            'next_domains' => $this->rolloutQueue(8),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rolloutQueue(int $limit = 8): array
    {
        $limit = max(1, min($limit, 50));
        $stageRank = array_flip((array) config(
            'najm-hoda-founder-operations.stage_order',
            ['planned', 'mapped', 'observed', 'managed']
        ));

        $domains = array_values(array_filter($this->all(), static function (array $domain): bool {
            return (string) ($domain['integration_stage'] ?? 'planned') !== 'managed';
        }));

        usort($domains, static function (array $a, array $b) use ($stageRank): int {
            $aStage = (string) ($a['integration_stage'] ?? 'planned');
            $bStage = (string) ($b['integration_stage'] ?? 'planned');
            $aRank = (int) ($stageRank[$aStage] ?? 0);
            $bRank = (int) ($stageRank[$bStage] ?? 0);

            // More mature integrations should be completed first; priority breaks ties.
            $stageCompare = $bRank <=> $aRank;
            if ($stageCompare !== 0) {
                return $stageCompare;
            }

            $priorityCompare = ((int) $b['priority']) <=> ((int) $a['priority']);
            return $priorityCompare !== 0
                ? $priorityCompare
                : strcmp((string) $a['key'], (string) $b['key']);
        });

        return array_slice($domains, 0, $limit);
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalize(string $key, array $domain): array
    {
        $stage = (string) Arr::get($domain, 'integration_stage', 'planned');
        if (! in_array($stage, ['planned', 'mapped', 'observed', 'managed'], true)) {
            $stage = 'planned';
        }

        return [
            'key' => $key,
            'label' => (string) Arr::get($domain, 'label', $key),
            'priority' => max(1, min(10, (int) Arr::get($domain, 'priority', 5))),
            'integration_stage' => $stage,
            'risk' => (string) Arr::get($domain, 'risk', 'medium'),
            'sources' => array_values((array) Arr::get($domain, 'sources', [])),
            'event_prefixes' => array_values((array) Arr::get($domain, 'event_prefixes', [])),
            'capabilities' => array_values(array_unique((array) Arr::get($domain, 'capabilities', []))),
        ];
    }
}
