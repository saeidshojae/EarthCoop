<?php

namespace App\Services\Communication;

use App\Models\CommunicationSenderIdentity;
use RuntimeException;

class SenderIdentityResolver
{
    public function resolve(string $key): CommunicationSenderIdentity
    {
        $sender = CommunicationSenderIdentity::query()
            ->where('key', $key)
            ->where('is_active', true)
            ->first();

        if (! $sender) {
            throw new RuntimeException("communication_sender_not_available:{$key}");
        }

        return $sender;
    }
}
