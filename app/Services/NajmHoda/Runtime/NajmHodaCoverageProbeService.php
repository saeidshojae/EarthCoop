<?php

namespace App\Services\NajmHoda\Runtime;

use Illuminate\Support\Str;

class NajmHodaCoverageProbeService
{
    public function __construct(
        protected RuntimeEventBus $eventBus
    ) {
    }

    /**
     * @return array<int, array{domain:string,event:string,scope:string}>
     */
    public function definitions(): array
    {
        return [
            [
                'domain' => 'support',
                'event' => 'najm_hoda.input.support.service.coverage_probe.succeeded',
                'scope' => 'support',
            ],
            [
                'domain' => 'auth',
                'event' => 'najm_hoda.input.auth.service.coverage_probe.succeeded',
                'scope' => 'auth',
            ],
            [
                'domain' => 'content',
                'event' => 'najm_hoda.input.content.service.coverage_probe.succeeded',
                'scope' => 'content',
            ],
            [
                'domain' => 'najm_bahar',
                'event' => 'najm_hoda.input.najm_bahar.service.coverage_probe.succeeded',
                'scope' => 'economy:najm-bahar',
            ],
            [
                'domain' => 'groups',
                'event' => 'najm_hoda.input.group_probe.succeeded',
                'scope' => 'group',
            ],
        ];
    }

    /**
     * @return array<int, array{domain:string,event:string,scope:string,action:string}>
     */
    public function emit(bool $dryRun = false): array
    {
        $items = $this->definitions();
        $result = [];

        foreach ($items as $item) {
            if (!$dryRun) {
                $this->eventBus->emit((string) $item['event'], [
                    'domain' => (string) $item['domain'],
                    'probe' => true,
                    'scope' => (string) $item['scope'],
                    'risk' => 'low',
                    'actor_id' => 'system',
                    'request_id' => (string) Str::uuid(),
                    'correlation_id' => (string) Str::uuid(),
                ]);
            }

            $result[] = [
                'domain' => (string) $item['domain'],
                'event' => (string) $item['event'],
                'scope' => (string) $item['scope'],
                'action' => $dryRun ? 'skipped' : 'emitted',
            ];
        }

        return $result;
    }
}

