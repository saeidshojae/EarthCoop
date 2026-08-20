<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\FounderOps\FounderManagedDomainRegistry;
use Tests\TestCase;

class FounderManagedDomainRegistryTest extends TestCase
{
    public function test_registry_exposes_all_major_management_domains(): void
    {
        $domains = app(FounderManagedDomainRegistry::class)->all();
        foreach ([
            'users','support','groups','governance','secretariat','najm_bahar','email','blog','stock',
            'notifications','reports_moderation','invitations','admin_settings',
        ] as $key) {
            $this->assertArrayHasKey($key, $domains, "Missing Founder Ops domain: {$key}");
        }
    }

    public function test_coverage_distinguishes_integrated_from_not_yet_managed_domains(): void
    {
        $coverage = app(FounderManagedDomainRegistry::class)->coverage();

        $this->assertGreaterThan(0, data_get($coverage, 'counts.total', 0));
        $this->assertGreaterThan(0, data_get($coverage, 'counts.observed', 0));
        $this->assertGreaterThan(0, data_get($coverage, 'counts.managed', 0));
        $this->assertGreaterThan(
            0,
            (int) data_get($coverage, 'counts.mapped', 0) + (int) data_get($coverage, 'counts.observed', 0),
            'Founder Ops should still expose domains that are not fully managed.'
        );
        $this->assertIsArray(data_get($coverage, 'next_domains'));
    }

    public function test_rollout_queue_prioritizes_more_mature_unmanaged_domains(): void
    {
        config()->set('najm-hoda-founder-operations.domains', [
            'planned_high' => ['priority' => 10, 'integration_stage' => 'planned'],
            'mapped_medium' => ['priority' => 5, 'integration_stage' => 'mapped'],
            'observed_low' => ['priority' => 1, 'integration_stage' => 'observed'],
            'managed_top' => ['priority' => 10, 'integration_stage' => 'managed'],
        ]);

        $queue = app(FounderManagedDomainRegistry::class)->rolloutQueue();

        $this->assertSame('observed_low', $queue[0]['key']);
        $this->assertSame('mapped_medium', $queue[1]['key']);
        $this->assertSame('planned_high', $queue[2]['key']);
        $this->assertCount(3, $queue);
    }
}
