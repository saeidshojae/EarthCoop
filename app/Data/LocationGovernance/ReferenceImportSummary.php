<?php

namespace App\Data\LocationGovernance;

final class ReferenceImportSummary
{
    public function __construct(
        public readonly int $creates = 0,
        public readonly int $updates = 0,
        public readonly int $deactivates = 0,
        public readonly int $conflicts = 0,
        public readonly int $unchanged = 0,
    ) {
    }

    public function toArray(): array
    {
        return [
            'creates' => $this->creates,
            'updates' => $this->updates,
            'deactivates' => $this->deactivates,
            'conflicts' => $this->conflicts,
            'unchanged' => $this->unchanged,
        ];
    }
}
