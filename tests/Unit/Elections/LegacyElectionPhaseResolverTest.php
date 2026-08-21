<?php

namespace Tests\Unit\Elections;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Models\Election;
use App\Services\Elections\LegacyElectionPhaseResolver;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class LegacyElectionPhaseResolverTest extends TestCase
{
    private LegacyElectionPhaseResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new LegacyElectionPhaseResolver();
    }

    public function test_future_election_is_scheduled(): void
    {
        $election = $this->legacyElection([
            'starts_at' => '2026-08-22 10:00:00',
            'ends_at' => '2026-08-23 10:00:00',
            'is_closed' => false,
        ]);

        $this->assertSame(
            ElectionLifecycleStatus::Scheduled,
            $this->resolver->resolve($election, CarbonImmutable::parse('2026-08-21 10:00:00')),
        );
    }

    public function test_election_inside_voting_window_is_open(): void
    {
        $election = $this->legacyElection([
            'starts_at' => '2026-08-20 10:00:00',
            'ends_at' => '2026-08-22 10:00:00',
            'is_closed' => false,
        ]);

        $this->assertSame(
            ElectionLifecycleStatus::Open,
            $this->resolver->resolve($election, CarbonImmutable::parse('2026-08-21 10:00:00')),
        );
    }

    public function test_elapsed_voting_window_is_closed_even_before_legacy_flag_is_updated(): void
    {
        $election = $this->legacyElection([
            'starts_at' => '2026-08-19 10:00:00',
            'ends_at' => '2026-08-20 10:00:00',
            'is_closed' => false,
        ]);

        $this->assertSame(
            ElectionLifecycleStatus::Closed,
            $this->resolver->resolve($election, CarbonImmutable::parse('2026-08-21 10:00:00')),
        );
    }

    public function test_legacy_closed_flag_has_priority(): void
    {
        $election = $this->legacyElection([
            'starts_at' => '2026-08-20 10:00:00',
            'ends_at' => '2026-08-22 10:00:00',
            'is_closed' => true,
        ]);

        $this->assertSame(
            ElectionLifecycleStatus::Closed,
            $this->resolver->resolve($election, CarbonImmutable::parse('2026-08-21 10:00:00')),
        );
    }

    public function test_missing_legacy_timestamps_default_to_open_when_not_closed(): void
    {
        $election = $this->legacyElection([
            'is_closed' => false,
        ]);

        $this->assertSame(
            ElectionLifecycleStatus::Open,
            $this->resolver->resolve($election, CarbonImmutable::parse('2026-08-21 10:00:00')),
        );
    }

    private function legacyElection(array $attributes): Election
    {
        $election = new Election();
        $election->setRawAttributes($attributes, true);

        return $election;
    }
}
