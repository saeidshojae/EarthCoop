<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\FounderOps\FounderExecutiveConnectivityService;
use Tests\TestCase;

class ExecutiveConnectivityCompletenessTest extends TestCase
{
    public function test_every_executable_founder_action_is_connected_or_explicitly_blocked(): void
    {
        $report = app(FounderExecutiveConnectivityService::class)->report();

        $this->assertSame(0, (int) data_get($report, 'summary.missing_executable_actions', -1),
            'Founder Ops contains executable actions that are neither connected nor explicitly blocked.');

        foreach ((array) data_get($report, 'rollout_queue', []) as $item) {
            $this->assertFalse((bool) ($item['actionable_now'] ?? true),
                'Rollout queue still contains an actionable missing adapter for domain ' . (string) ($item['domain'] ?? 'unknown'));
            $this->assertSame([], (array) ($item['missing_actions'] ?? []));
        }
    }

    public function test_remaining_rollout_queue_items_name_their_dependencies(): void
    {
        $report = app(FounderExecutiveConnectivityService::class)->report();

        foreach ((array) data_get($report, 'rollout_queue', []) as $item) {
            foreach ((array) ($item['blocked_actions'] ?? []) as $blocked) {
                $this->assertNotSame('', trim((string) ($blocked['reason'] ?? '')));
                $this->assertNotSame('', trim((string) ($blocked['dependency'] ?? '')));
            }
        }
    }
}
