<?php

namespace App\Services\Notifications;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class NotificationCursor
{
    public function encode(Carbon|string $createdAt, string $id): string
    {
        $time = $createdAt instanceof Carbon ? $createdAt : Carbon::parse($createdAt);

        return Crypt::encryptString(json_encode([
            'created_at' => $time->utc()->format('Y-m-d H:i:s'),
            'id' => $id,
        ], JSON_THROW_ON_ERROR));
    }

    public function decode(string $cursor): array
    {
        try {
            $decoded = json_decode(Crypt::decryptString($cursor), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($decoded) || empty($decoded['created_at']) || empty($decoded['id'])) {
                throw new \UnexpectedValueException('Invalid cursor payload.');
            }

            $createdAt = Carbon::createFromFormat('Y-m-d H:i:s', (string) $decoded['created_at'], 'UTC');
            if (! $createdAt) {
                throw new \UnexpectedValueException('Invalid cursor timestamp.');
            }

            return [
                'created_at' => $createdAt->format('Y-m-d H:i:s'),
                'id' => (string) $decoded['id'],
            ];
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'page.cursor' => ['Invalid notification cursor.'],
            ]);
        }
    }
}
