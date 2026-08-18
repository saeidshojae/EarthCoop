<?php

namespace App\Modules\Secretariat\Services;

use App\Modules\Secretariat\Models\SecretariatOffice;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\ValidationException;

class SecretariatOfficeService
{
    private const TYPES = ['central', 'group', 'project', 'legal_entity', 'committee', 'other'];
    private const CONFIDENTIALITIES = ['public', 'office_members', 'leadership', 'restricted', 'confidential'];

    public function create(array $attributes): SecretariatOffice
    {
        SecretariatMorphMap::register();

        $type = (string) ($attributes['office_type'] ?? '');
        $scopeType = $attributes['scope_type'] ?? null;
        $scopeId = $attributes['scope_id'] ?? null;
        $confidentiality = (string) ($attributes['default_confidentiality'] ?? 'office_members');

        if (! in_array($type, self::TYPES, true)) {
            throw ValidationException::withMessages(['office_type' => 'Unsupported Secretariat office type.']);
        }

        if (! in_array($confidentiality, self::CONFIDENTIALITIES, true)) {
            throw ValidationException::withMessages(['default_confidentiality' => 'Unsupported confidentiality level.']);
        }

        if ($type !== 'central' && ($scopeType === null || $scopeId === null)) {
            throw ValidationException::withMessages(['scope_type' => 'A non-central Secretariat office requires a scope.']);
        }

        if ($scopeType !== null) {
            $class = Relation::getMorphedModel((string) $scopeType);
            if ($class === null) {
                throw ValidationException::withMessages(['scope_type' => 'Unknown or unmapped Secretariat scope token.']);
            }
            if (! $class::query()->whereKey($scopeId)->exists()) {
                throw ValidationException::withMessages(['scope_id' => 'Secretariat office scope does not exist.']);
            }
        }

        return SecretariatOffice::query()->create([
            ...$attributes,
            'default_confidentiality' => $confidentiality,
            'status' => $attributes['status'] ?? 'active',
        ]);
    }
}
