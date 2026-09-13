<?php

namespace App\Data\Membership;

class MembershipIntent
{
    public function __construct(
        public readonly string $dimensionKey,
        public readonly string $valueKey,
        public readonly ?int $governanceAreaId,
        public readonly string $mode,
        public readonly ?int $threshold = null,
        public readonly ?string $policyVersion = null,
        public readonly ?string $suppressionReason = null,
    ) {
    }

    public function canonical(): array
    {
        return [
            'dimension_key' => $this->dimensionKey,
            'value_key' => $this->valueKey,
            'governance_area_id' => $this->governanceAreaId,
            'mode' => $this->mode,
            'threshold' => $this->threshold,
            'policy_version' => $this->policyVersion,
            'suppression_reason' => $this->suppressionReason,
        ];
    }
}
