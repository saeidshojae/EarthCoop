<?php

namespace App\Services\Communication;

use App\Models\CommunicationSenderIdentity;
use RuntimeException;

class SenderIdentityResolver
{
    /** @return array{key:string,address:string,name:string,reply_to:string,system_identity_key:?string} */
    public function resolve(string $key): array
    {
        $sender = CommunicationSenderIdentity::query()
            ->where('key', $key)
            ->where('is_active', true)
            ->first();

        if (! $sender) {
            throw new RuntimeException("communication_sender_not_available:{$key}");
        }

        return [
            'key' => (string) $sender->key,
            'address' => (string) $sender->email,
            'name' => (string) $sender->display_name,
            'reply_to' => (string) ($sender->reply_to ?: $sender->email),
            'system_identity_key' => $sender->system_identity_key !== null
                ? (string) $sender->system_identity_key
                : null,
        ];
    }
}
