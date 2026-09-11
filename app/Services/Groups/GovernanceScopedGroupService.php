<?php

namespace App\Services\Groups;

use App\Data\Membership\MembershipIntent;
use App\Models\GovernanceArea;
use App\Models\Group;

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
        if ($area === null || $area->status !== 'active') {
            return null;
        }

        return Group::query()->firstOrCreate(
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
    }

    private function nameFor(MembershipIntent $intent, GovernanceArea $area): string
    {
        $areaName = $area->canonical_name ?: $area->key;

        return match ($intent->dimensionKey) {
            'public' => "مجمع عمومی {$areaName}",
            'profession' => "مجمع صنفی {$intent->valueKey} در {$areaName}",
            'specialty' => "مجمع تخصصی {$intent->valueKey} در {$areaName}",
            'age' => "مجمع سنی {$intent->valueKey} در {$areaName}",
            'gender' => "مجمع جنسیتی {$intent->valueKey} در {$areaName}",
            default => "گروه {$intent->valueKey} در {$areaName}",
        };
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
