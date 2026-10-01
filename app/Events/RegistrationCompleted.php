<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class RegistrationCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly string $completedAt,
        public readonly string $locale,
    ) {
    }
}
