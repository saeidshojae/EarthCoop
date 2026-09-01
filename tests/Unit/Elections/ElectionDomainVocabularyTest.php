<?php

namespace Tests\Unit\Elections;

use App\Enums\Elections\ElectionAcceptanceStatus;
use App\Enums\Elections\ElectionLifecycleStatus;
use App\Enums\Elections\ElectionPosition;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ElectionDomainVocabularyTest extends TestCase
{
    public function test_legacy_vote_positions_map_to_canonical_positions(): void
    {
        $this->assertSame(ElectionPosition::Inspector, ElectionPosition::fromLegacyVotePosition(0));
        $this->assertSame(ElectionPosition::Manager, ElectionPosition::fromLegacyVotePosition(1));
        $this->assertSame(ElectionPosition::Inspector, ElectionPosition::fromLegacyVotePosition('inspector'));
        $this->assertSame(ElectionPosition::Manager, ElectionPosition::fromLegacyVotePosition('manager'));

        $this->assertSame(0, ElectionPosition::Inspector->legacyVotePosition());
        $this->assertSame(1, ElectionPosition::Manager->legacyVotePosition());
    }

    public function test_invalid_legacy_vote_position_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ElectionPosition::fromLegacyVotePosition(99);
    }

    public function test_legacy_candidate_statuses_are_mapped_without_hiding_zero_ambiguity(): void
    {
        $this->assertSame(ElectionAcceptanceStatus::Pending, ElectionAcceptanceStatus::fromLegacyCandidateStatus(1));
        $this->assertSame(ElectionAcceptanceStatus::Accepted, ElectionAcceptanceStatus::fromLegacyCandidateStatus(2));
        $this->assertNull(ElectionAcceptanceStatus::fromLegacyCandidateStatus(0));
        $this->assertSame(
            ElectionAcceptanceStatus::Declined,
            ElectionAcceptanceStatus::fromLegacyCandidateStatus(0, wasOffered: true),
        );

        $this->assertSame(
            ElectionAcceptanceStatus::Pending,
            ElectionAcceptanceStatus::fromLegacyCandidateStatus('pending'),
        );
        $this->assertSame(
            ElectionAcceptanceStatus::Accepted,
            ElectionAcceptanceStatus::fromLegacyCandidateStatus('accepted'),
        );
        $this->assertSame(
            ElectionAcceptanceStatus::Declined,
            ElectionAcceptanceStatus::fromLegacyCandidateStatus('declined'),
        );
        $this->assertSame(
            ElectionAcceptanceStatus::Expired,
            ElectionAcceptanceStatus::fromLegacyCandidateStatus('expired'),
        );
    }

    public function test_invalid_legacy_candidate_status_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ElectionAcceptanceStatus::fromLegacyCandidateStatus('unknown');
    }

    public function test_only_terminal_lifecycle_states_are_terminal(): void
    {
        $terminal = [
            ElectionLifecycleStatus::Filled,
            ElectionLifecycleStatus::Exhausted,
            ElectionLifecycleStatus::Cancelled,
        ];

        foreach (ElectionLifecycleStatus::cases() as $status) {
            $this->assertSame(
                in_array($status, $terminal, true),
                $status->isTerminal(),
                "Unexpected terminal classification for {$status->value}",
            );
        }
    }
}
