<?php

namespace App\Modules\NajmBahar\Services\Api;

use RuntimeException;

class NajmBaharActivationException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus,
    ) {
        parent::__construct($message);
    }
}
