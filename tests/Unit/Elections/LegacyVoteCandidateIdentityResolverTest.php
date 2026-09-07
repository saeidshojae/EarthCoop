<?php

namespace Tests\Unit\Elections;

use App\Services\Elections\LegacyVoteCandidateIdentityResolver;
use PHPUnit\Framework\TestCase;

class LegacyVoteCandidateIdentityResolverTest extends TestCase
{
    private LegacyVoteCandidateIdentityResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new LegacyVoteCandidateIdentityResolver();
    }

    public function test_active_legacy_user_id_is_preserved_when_unambiguous(): void
    {
        $this->assertSame(42, $this->resolver->resolve(
            legacyId: 42,
            directUserExists: true,
            candidateUserId: null,
            candidateUserExists: false,
            candidateMatchesElection: false,
        ));
    }

    public function test_candidate_record_id_can_be_mapped_to_its_user_when_user_id_interpretation_does_not_exist(): void
    {
        $this->assertSame(99, $this->resolver->resolve(
            legacyId: 7,
            directUserExists: false,
            candidateUserId: 99,
            candidateUserExists: true,
            candidateMatchesElection: true,
        ));
    }

    public function test_conflicting_user_and_candidate_meanings_are_left_unresolved(): void
    {
        $this->assertNull($this->resolver->resolve(
            legacyId: 7,
            directUserExists: true,
            candidateUserId: 99,
            candidateUserExists: true,
            candidateMatchesElection: true,
        ));
    }

    public function test_candidate_from_another_election_is_never_used_for_backfill(): void
    {
        $this->assertNull($this->resolver->resolve(
            legacyId: 7,
            directUserExists: false,
            candidateUserId: 99,
            candidateUserExists: true,
            candidateMatchesElection: false,
        ));
    }

    public function test_missing_candidate_user_is_never_guessed(): void
    {
        $this->assertNull($this->resolver->resolve(
            legacyId: 7,
            directUserExists: false,
            candidateUserId: 99,
            candidateUserExists: false,
            candidateMatchesElection: true,
        ));
    }

    public function test_invalid_legacy_id_is_rejected(): void
    {
        $this->assertNull($this->resolver->resolve(
            legacyId: 0,
            directUserExists: true,
            candidateUserId: null,
            candidateUserExists: false,
            candidateMatchesElection: false,
        ));
    }
}
