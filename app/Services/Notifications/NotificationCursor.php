<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class NotificationCursor
{
    public function encode(int $sequence): string
    {
        if ($sequence < 1) {
            throw new \InvalidArgumentException('Notification sync sequence must be positive.');
        }

        return Crypt::encryptString(json_encode([
            'version' => 1,
            'sequence' => $sequence,
        ], JSON_THROW_ON_ERROR));
    }

    public function decode(string $cursor): int
    {
        try {
            $decoded = json_decode(Crypt::decryptString($cursor), true, flags: JSON_THROW_ON_ERROR);
            $sequence = is_array($decoded) ? ($decoded['sequence'] ?? null) : null;
            if (($decoded['version'] ?? null) !== 1 || ! is_int($sequence) || $sequence < 1) {
                throw new \UnexpectedValueException('Invalid cursor payload.');
            }

            return $sequence;
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'page.cursor' => ['Invalid notification cursor.'],
            ]);
        }
    }
}
