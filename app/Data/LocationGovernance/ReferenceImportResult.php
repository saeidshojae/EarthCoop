<?php

namespace App\Data\LocationGovernance;

final class ReferenceImportResult
{
    public function __construct(
        public readonly int $creates,
        public readonly int $updates,
        public readonly int $deactivates,
        public readonly int $conflicts,
        public readonly int $unchanged,
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
