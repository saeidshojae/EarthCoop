<?php

namespace Tests\Unit\Elections;

use App\Models\Candidate;
use App\Models\Election;
use PHPUnit\Framework\TestCase;

class LegacyElectionWriteCompatibilityTest extends TestCase
{
    public function test_pending_legacy_candidate_status_dual_writes_canonical_pending(): void
    {
        $candidate = new Candidate();
        $candidate->accept_status = 1;

        $this->assertSame('1', (string) $candidate->getAttributes()['accept_status']);
        $this->assertSame('pending', $candidate->getAttributes()['acceptance_status']);
    }

    public function test_accepted_legacy_candidate_status_dual_writes_canonical_accepted(): void
    {
        $candidate = new Candidate();
        $candidate->accept_status = 2;

        $this->assertSame('accepted', $candidate->getAttributes()['acceptance_status']);
    }

    public function test_bare_legacy_zero_is_not_guessed_as_declined(): void
    {
        $candidate = new Candidate();
        $candidate->accept_status = 0;

        $this->assertArrayNotHasKey('acceptance_status', $candidate->getAttributes());
    }

    public function test_legacy_zero_after_pending_is_provably_declined(): void
    {
        $candidate = new Candidate();
        $candidate->accept_status = 1;
        $candidate->accept_status = 0;

        $this->assertSame('declined', $candidate->getAttributes()['acceptance_status']);
    }

    public function test_legacy_close_dual_writes_canonical_closed(): void
    {
        $election = new Election();
        $election->is_closed = 1;

        $this->assertSame('closed', $election->getAttributes()['lifecycle_status']);
    }

    public function test_legacy_open_flag_does_not_guess_scheduled_vs_open(): void
    {
        $election = new Election();
        $election->is_closed = 0;

        $this->assertArrayNotHasKey('lifecycle_status', $election->getAttributes());
    }
}
