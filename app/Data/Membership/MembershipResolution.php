<?php

namespace App\Data\Membership;

use Illuminate\Support\Collection;

class MembershipResolution
{
    public function __construct(
        public readonly Collection $officialGovernanceAreas,
        public readonly Collection $communityAreas,
        public readonly Collection $materializableIntents,
        public readonly Collection $suppressedIntents,
        public readonly string $auditFingerprint,
    ) {
    }

    public function canonical(): array
    {
        return [
            'official_governance_areas' => $this->officialGovernanceAreas->values()->all(),
            'community_areas' => $this->communityAreas->values()->all(),
            'materializable_intents' => $this->materializableIntents->map(fn (MembershipIntent $intent) => $intent->canonical())->values()->all(),
            'suppressed_intents' => $this->suppressedIntents->map(fn (MembershipIntent $intent) => $intent->canonical())->values()->all(),
            'audit_fingerprint' => $this->auditFingerprint,
        ];
    }
}
