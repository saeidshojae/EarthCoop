<?php

namespace App\Services\NajmHoda\Runtime;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class RuntimeEventEnvelope
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function normalize(string $event, array $payload): array
    {
        $requestId = self::stringOrNull($payload, 'request_id');
        if ($requestId === null || $requestId === '') {
            $requestId = (string) Str::uuid();
        }

        $correlationId = self::stringOrNull($payload, 'correlation_id');
        if ($correlationId === null || $correlationId === '') {
            $correlationId = $requestId;
        }

        $actorId = self::stringOrNull($payload, 'actor_id');
        if ($actorId === null) {
            $actorId = self::stringOrNull($payload, 'user_id') ?? 'system';
        }

        $scope = self::stringOrNull($payload, 'scope');
        if ($scope === null || $scope === '') {
            $scope = self::inferScope($event, $payload);
        }

        $risk = self::stringOrNull($payload, 'risk');
        if ($risk === null || $risk === '') {
            $risk = 'unknown';
        }

        $eventVersion = isset($payload['event_version']) ? max(1, (int) $payload['event_version']) : 1;
        $emittedAt = self::stringOrNull($payload, 'emitted_at') ?? CarbonImmutable::now()->toIso8601String();

        return array_merge($payload, [
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
            'actor_id' => $actorId,
            'scope' => $scope,
            'risk' => $risk,
            'event_version' => $eventVersion,
            'emitted_at' => $emittedAt,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected static function inferScope(string $event, array $payload): string
    {
        $groupId = isset($payload['group_id']) ? (int) $payload['group_id'] : 0;
        if ($groupId > 0) {
            return "group:{$groupId}";
        }

        if (str_contains($event, '.ops.')) {
            return 'ops';
        }

        if (str_contains($event, '.autonomy.')) {
            return 'autonomy';
        }

        if (str_contains($event, '.input.group_')) {
            return 'group';
        }

        return 'global';
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected static function stringOrNull(array $payload, string $key): ?string
    {
        if (!array_key_exists($key, $payload) || $payload[$key] === null) {
            return null;
        }

        $value = trim((string) $payload[$key]);
        return $value === '' ? null : $value;
    }
}

