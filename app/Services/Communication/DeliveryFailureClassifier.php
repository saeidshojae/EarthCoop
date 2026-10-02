<?php

namespace App\Services\Communication;

use InvalidArgumentException;
use Throwable;

class DeliveryFailureClassifier
{
    public const TRANSIENT = 'transient';
    public const PERMANENT = 'permanent';

    public function classify(Throwable $exception): string
    {
        if ($exception instanceof InvalidArgumentException) {
            return self::PERMANENT;
        }

        $message = mb_strtolower($exception->getMessage());

        foreach (['invalid address', 'invalid destination', 'mailbox does not exist', 'unknown recipient', 'recipient rejected'] as $marker) {
            if (str_contains($message, $marker)) {
                return self::PERMANENT;
            }
        }

        // Infrastructure/provider failures are retried by default. The queue retry budget is bounded.
        return self::TRANSIENT;
    }
}
