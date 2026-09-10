<?php

namespace App\Enums\LocationGovernance;

enum LocationProposalStatus: string
{
    case Pending = 'pending';
    case ReadyForReview = 'ready_for_review';
    case NeedsEvidence = 'needs_evidence';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Merged = 'merged';
}
