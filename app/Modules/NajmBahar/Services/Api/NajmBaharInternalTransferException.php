<?php

namespace App\Modules\NajmBahar\Services\Api;

use RuntimeException;

class NajmBaharInternalTransferException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus = 409,
    ) {
        parent::__construct($message);
    }
}
