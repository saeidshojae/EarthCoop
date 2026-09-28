<?php

namespace App\Services\Push;

class PushTokenInUseException extends \RuntimeException
{
    public function __construct(string $message = 'Push token is already active on another device.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
