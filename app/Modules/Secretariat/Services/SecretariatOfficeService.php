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
        $code = trim((string) ($attributes['code'] ?? ''));
        $name = trim((string) ($attributes['name'] ?? ''));
        $numberingPolicy = $attributes['numbering_policy'] ?? null;

        if ($code === '') {
            throw ValidationException::withMessages(['code' => 'A Secretariat office requires a code.']);
        }
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'A Secretariat office requires a name.']);
        }
        if (! in_array($type, self::TYPES, true)) {
            throw ValidationException::withMessages(['office_type' => 'Unsupported Secretariat office type.']);
        }
        if (! in_array($confidentiality, self::CONFIDENTIALITIES, true)) {
            throw ValidationException::withMessages(['default_confidentiality' => 'Unsupported confidentiality level.']);
        }

        if ($type === 'central' && ($scopeType !== null || $scopeId !== null)) {
            throw ValidationException::withMessages(['scope_type' => 'The central Secretariat office must not carry a scoped owner.']);
        }
        if ($type !== 'central' && ($scopeType === null || $scopeId === null)) {
            throw ValidationException::withMessages(['scope_type' => 'A non-central Secretariat office requires a scope.']);
        }
        if ($type === 'group' && $scopeType !== 'group') {
            throw ValidationException::withMessages(['scope_type' => 'A group Secretariat office must use the group scope token.']);
        }
        if ($type === 'project' && $scopeType !== 'najm_bahar_project') {
            throw ValidationException::withMessages(['scope_type' => 'A project Secretariat office must use the Najm Bahar project scope token.']);
        }

        $this->validateNumberingPolicy($numberingPolicy);

        if ($scopeType !== null) {
            $class = Relation::getMorphedModel((string) $scopeType);
            if ($class === null) {
                throw ValidationException::withMessages(['scope_type' => 'Unknown or unmapped Secretariat scope token.']);
            }
            if (! $class::query()->whereKey($scopeId)->exists()) {
                throw ValidationException::withMessages(['scope_id' => 'Secretariat office scope does not exist.']);
            }
        }

        if (in_array($type, ['group', 'project'], true)) {
            $duplicate = SecretariatOffice::query()
                ->where('office_type', $type)
                ->where('scope_type', $scopeType)
                ->where('scope_id', $scopeId)
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages(['scope_id' => 'This scope already has its canonical Secretariat office.']);
            }
        }

        return SecretariatOffice::query()->create([
            'code' => $code,
            'name' => $name,
            'office_type' => $type,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'status' => $attributes['status'] ?? 'active',
            'numbering_policy' => $numberingPolicy,
            'default_confidentiality' => $confidentiality,
            'metadata' => $attributes['metadata'] ?? null,
        ]);
    }

    private function validateNumberingPolicy(mixed $policy): void
    {
        if ($policy === null) {
            return;
        }
        if (! is_array($policy)) {
            throw ValidationException::withMessages(['numbering_policy' => 'Numbering policy must be an object/array.']);
        }

        $format = (string) ($policy['format'] ?? '{OFFICE}/{YEAR}/{FAMILY}/{SEQ}');
        if (! str_contains($format, '{SEQ}')) {
            throw ValidationException::withMessages(['numbering_policy' => 'Registry number format must contain {SEQ}.']);
        }

        if (isset($policy['sequence_width'])) {
            $width = (int) $policy['sequence_width'];
            if ($width < 1 || $width > 12) {
                throw ValidationException::withMessages(['numbering_policy' => 'sequence_width must be between 1 and 12.']);
            }
        }
    }
}
