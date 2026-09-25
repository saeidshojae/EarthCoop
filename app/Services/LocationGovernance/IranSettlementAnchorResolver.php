<?php

namespace App\Services\LocationGovernance;

use App\Models\Location;
use App\Models\LocationExternalId;
use App\Models\ReferenceSettlement;
use Illuminate\Validation\ValidationException;

final class IranSettlementAnchorResolver
{
    public function resolve(ReferenceSettlement $settlement): Location
    {
        $directIdentity = LocationExternalId::query()
            ->where('source', 'earthcoop-reference')
            ->where('dataset_version', 'v2')
            ->where('external_id', $settlement->parent_external_id)
            ->with('location')
            ->first();

        $directLocation = $directIdentity?->location;
        if ($directLocation instanceof Location
            && $directLocation->status === 'active'
            && $directLocation->country_code === 'IR') {
            return $directLocation;
        }

        $candidate = collect((array) config('iran_v1_v2_crosswalk.mappings', []))
            ->first(function (array $mapping) use ($settlement): bool {
                return ($mapping['v2'] ?? null) === $settlement->parent_external_id
                    && ($mapping['status'] ?? null) === 'verified_identity';
            });

        if (! is_array($candidate)) {
            throw ValidationException::withMessages([
                'reference_settlement_external_id' => 'والد این آبادی هنوز به‌عنوان مکان canonical فعال در منبع ۱۴۰۴ موجود نیست.',
            ]);
        }

        $v1ExternalId = collect((array) config('iran_v1_v2_crosswalk.mappings', []))
            ->search(fn (array $mapping): bool =>
                ($mapping['v2'] ?? null) === $settlement->parent_external_id
                && ($mapping['status'] ?? null) === 'verified_identity'
            );

        $identity = LocationExternalId::query()
            ->where('source', config('iran_v1_v2_crosswalk.source', 'earthcoop-reference'))
            ->where('dataset_version', config('iran_v1_v2_crosswalk.v1_dataset_version', 'v1'))
            ->where('external_id', $v1ExternalId)
            ->with('location')
            ->first();

        $location = $identity?->location;
        if (! $location instanceof Location || $location->status !== 'active' || $location->country_code !== 'IR') {
            throw ValidationException::withMessages([
                'reference_settlement_external_id' => 'والد canonical این آبادی در دیتابیس فعلی فعال و قابل اتکا نیست.',
            ]);
        }

        return $location;
    }
}
