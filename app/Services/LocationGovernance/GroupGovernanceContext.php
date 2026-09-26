<?php

namespace App\Services\LocationGovernance;

use App\Models\GovernanceArea;
use App\Models\Group;
use RuntimeException;

final class GroupGovernanceContext
{
    public function level(Group $group): string
    {
        $area = $this->canonicalArea($group);
        if ($area !== null) {
            return strtolower(trim((string) $area->governance_type));
        }

        return strtolower(trim((string) ($group->getAttributes()['location_level'] ?? '')));
    }

    /**
     * Return the mature legacy presentation vocabulary for a group while making
     * canonical GovernanceArea the authority whenever canonical groups are on.
     * This is a read-only compatibility adapter for UI/announcement consumers;
     * it never writes the legacy location_level column.
     */
    public function legacyCompatibleLevel(Group $group): string
    {
        $level = $this->level($group);

        return match ($level) {
            'local' => 'neighborhood',
            'urban_region' => 'region',
            'rural_district' => 'rural',
            default => $level,
        };
    }

    public function officialElectionLevel(Group $group): string
    {
        if ($this->canonicalElectionsEnabled()) {
            $area = $this->canonicalArea($group);
            if ($area === null || $area->area_kind !== 'official' || $area->status !== 'active') {
                throw new RuntimeException("Formal election scope requires an active official governance area for group [{$group->id}].");
            }

            $level = strtolower(trim((string) $area->governance_type));
            if ($level === '') {
                throw new RuntimeException("Governance area [{$area->id}] does not define a governance type.");
            }

            // Conflict-policy rows predate the canonical governance vocabulary.
            // Map canonical types to the existing versioned policy keys rather
            // than silently falling through to the default "allowed" decision.
            return match ($level) {
                'local' => 'neighborhood',
                'urban_region' => 'region',
                'rural_district' => 'rural',
                default => $level,
            };
        }

        $level = strtolower(trim((string) ($group->getAttributes()['location_level'] ?? '')));
        if ($level === '') {
            throw new RuntimeException("Legacy election group [{$group->id}] does not define location_level.");
        }

        return $level;
    }

    public function electionDomain(Group $group): string
    {
        if ($this->canonicalElectionsEnabled()) {
            return match (strtolower(trim((string) ($group->getAttributes()['dimension_key'] ?? '')))) {
                'public' => 'public',
                'profession' => 'job',
                'specialty' => 'experience',
                'age' => 'age',
                'gender' => 'gender',
                default => throw new RuntimeException(
                    "Unsupported canonical election dimension for group [{$group->id}]."
                ),
            };
        }

        if ($group->specialty_id !== null) return 'job';
        if ($group->experience_id !== null) return 'experience';
        if ($group->age_group_id !== null) return 'age';
        if ($group->gender !== null) return 'gender';

        return 'public';
    }

    public function stableScopeKey(Group $group): string
    {
        $area = $this->canonicalArea($group);
        if ($area !== null && $this->canonicalGroupsEnabled()) {
            return implode(':', [
                'governance',
                (int) $area->id,
                'dimension',
                (string) ($group->getAttributes()['dimension_key'] ?? 'public'),
                (string) ($group->getAttributes()['dimension_value_key'] ?? 'public'),
            ]);
        }

        return implode(':', [
            'legacy',
            $this->level($group),
            'address',
            (string) ($group->getAttributes()['address_id'] ?? 'global'),
        ]);
    }

    public function canonicalArea(Group $group): ?GovernanceArea
    {
        if (! $this->canonicalGroupsEnabled()) {
            return null;
        }

        if ($group->relationLoaded('governanceArea')) {
            $area = $group->getRelation('governanceArea');

            return $area instanceof GovernanceArea ? $area : null;
        }

        $areaId = $group->getAttributes()['governance_area_id'] ?? null;

        return $areaId === null ? null : GovernanceArea::query()->find((int) $areaId);
    }

    private function canonicalGroupsEnabled(): bool
    {
        return (bool) config('location-governance.groups_enabled', false)
            || (bool) config('location-governance.elections_enabled', false);
    }

    private function canonicalElectionsEnabled(): bool
    {
        return (bool) config('location-governance.elections_enabled', false);
    }
}
