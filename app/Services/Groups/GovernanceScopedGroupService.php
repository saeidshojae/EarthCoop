<?php

namespace App\Services\Groups;

use App\Data\Membership\MembershipIntent;
use App\Models\AgeGroup;
use App\Models\ExperienceField;
use App\Models\GovernanceArea;
use App\Models\Group;
use App\Models\OccupationalField;

class GovernanceScopedGroupService
{
    public function materialize(MembershipIntent $intent): ?Group
    {
        if ($intent->suppressionReason !== null || $intent->mode !== 'automatic') {
            return null;
        }

        if ($intent->governanceAreaId === null) {
            return null;
        }

        $area = GovernanceArea::query()->find($intent->governanceAreaId);
        if ($area === null || $area->area_kind !== 'official' || $area->status !== 'active') {
            return null;
        }

        $group = Group::query()->firstOrCreate(
            [
                'governance_area_id' => $intent->governanceAreaId,
                'dimension_key' => $intent->dimensionKey,
                'dimension_value_key' => $intent->valueKey,
            ],
            [
                'name' => $this->nameFor($intent, $area),
                'group_type' => $this->legacyTypeFor($intent->dimensionKey),
                'is_open' => 1,
            ]
        );

        $expectedName = $this->nameFor($intent, $area);
        if ($group->name !== $expectedName) {
            $group->forceFill(['name' => $expectedName])->save();
        }

        return $group;
    }

    public function pendingNameFor(string $dimensionKey, string $valueKey, string $areaName, ?string $governanceType = null): string
    {
        $intent = new MembershipIntent(
            dimensionKey: $dimensionKey,
            valueKey: $valueKey,
            governanceAreaId: null,
            mode: 'automatic',
        );
        $valueLabel = $this->valueLabel($intent);
        $areaName = $this->qualifiedAreaLabel($areaName, $governanceType);

        return match ($dimensionKey) {
            'public' => "مجمع عمومی {$areaName}",
            'profession' => "مجمع صنفی {$valueLabel} در {$areaName}",
            'specialty' => "مجمع تخصصی {$valueLabel} در {$areaName}",
            'age' => "مجمع سنی {$valueLabel} در {$areaName}",
            'gender' => "مجمع جنسیتی {$valueLabel} در {$areaName}",
            default => "گروه {$valueLabel} در {$areaName}",
        };
    }

    private function nameFor(MembershipIntent $intent, GovernanceArea $area): string
    {
        return $this->pendingNameFor(
            $intent->dimensionKey,
            $intent->valueKey,
            $this->areaLabel($area),
            $area->governance_type,
        );
    }

    private function qualifiedAreaLabel(string $areaName, ?string $governanceType): string
    {
        $label = match ($governanceType) {
            'global' => 'جهان',
            'continent' => 'قاره',
            'country' => 'کشور',
            'province' => 'استان',
            'county' => 'شهرستان',
            'section' => 'بخش',
            'city' => 'شهر',
            'rural_district' => 'دهستان',
            'urban_region' => 'منطقه',
            'village' => 'روستا',
            'local', 'neighborhood' => 'محله',
            default => null,
        };

        if ($label === null) return $areaName;
        if ($governanceType === 'global') return 'جهان';

        $normalized = trim($areaName);
        // Persian village names conventionally begin with «روستای», not «روستا».
        // Never turn «روستای مرجع» into «روستا روستای مرجع».
        if ($governanceType === 'village' && str_starts_with($normalized, 'روستای ')) {
            return $normalized;
        }

        return str_starts_with($normalized, $label.' ') || $normalized === $label
            ? $normalized
            : $label.' '.$normalized;
    }

    private function areaLabel(GovernanceArea $area): string
    {
        $localized = $area->localized_names ?? [];
        $locale = app()->getLocale();

        return (string) ($localized[$locale]
            ?? $localized['fa']
            ?? $area->canonical_name
            ?? $area->key);
    }

    private function valueLabel(MembershipIntent $intent): string
    {
        if ($intent->dimensionKey === 'profession' && str_starts_with($intent->valueKey, 'occupational_field:')) {
            $id = (int) substr($intent->valueKey, strlen('occupational_field:'));

            return (string) (OccupationalField::query()->find($id)?->name ?? $intent->valueKey);
        }

        if ($intent->dimensionKey === 'specialty' && str_starts_with($intent->valueKey, 'experience_field:')) {
            $id = (int) substr($intent->valueKey, strlen('experience_field:'));

            return (string) (ExperienceField::query()->find($id)?->name ?? $intent->valueKey);
        }

        if ($intent->dimensionKey === 'age' && str_starts_with($intent->valueKey, 'age_group:')) {
            $id = (int) substr($intent->valueKey, strlen('age_group:'));

            return (string) (AgeGroup::query()->find($id)?->title ?? $intent->valueKey);
        }

        if ($intent->dimensionKey === 'gender' && str_starts_with($intent->valueKey, 'gender:')) {
            return match (substr($intent->valueKey, strlen('gender:'))) {
                'male' => 'مردان',
                'female' => 'زنان',
                default => 'دیگران',
            };
        }

        return $intent->valueKey;
    }

    private function legacyTypeFor(string $dimensionKey): string
    {
        return match ($dimensionKey) {
            'public' => '0',
            'profession' => '1',
            'specialty' => '2',
            'age' => '3',
            'gender' => '4',
            default => '0',
        };
    }
}
