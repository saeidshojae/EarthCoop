<?php

namespace App\Services\LocationGovernance;

use App\Models\LocationSchema;

final class IranV2RuntimeState
{
    public const SCHEMA_KEY = 'ir-reference-v2';

    public function isActive(): bool
    {
        $schema = $this->schema();

        return $schema !== null
            && (bool) data_get($schema->metadata, 'runtime_active', false);
    }

    public function schema(): ?LocationSchema
    {
        return LocationSchema::query()
            ->where('key', self::SCHEMA_KEY)
            ->where('country_code', 'IR')
            ->where('version', 'v2')
            ->where('status', 'active')
            ->first();
    }

    public function setActive(bool $active): void
    {
        $schema = $this->schema();
        if ($schema === null) {
            throw new \RuntimeException('Iran v2 schema is missing or inactive.');
        }

        $metadata = $schema->metadata ?? [];
        $metadata['runtime_active'] = $active;
        $metadata['runtime_activated_at'] = $active ? now()->toIso8601String() : null;
        $schema->forceFill(['metadata' => $metadata])->save();
    }
}
