<?php

namespace App\Services\Communication;

final readonly class PreferenceDecision
{
    public function __construct(
        public bool $allowed,
        public string $reason,
    ) {
    }

    /** @return array{allowed:bool,reason:string} */
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'reason' => $this->reason,
        ];
    }
}
