<?php

namespace App\Services\LocationGovernance;

use App\Models\GovernanceArea;
use App\Models\Group;
use App\Models\Poll;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\Secretariat\Models\SecretariatOffice;
use Illuminate\Database\Eloquent\Model;

class GovernanceResourceScopeResolver
{
    public function __construct(
        private readonly GovernanceCapabilityResolver $capabilityResolver,
    ) {}

    public function areaFor(Model $resource): ?GovernanceArea
    {
        if ($resource instanceof Group) {
            return $this->activeArea((int) ($resource->governance_area_id ?? 0));
        }

        if ($resource instanceof Poll) {
            $group = $resource->relationLoaded('group') ? $resource->group : $resource->group()->first();

            return $group instanceof Group ? $this->areaFor($group) : null;
        }

        if ($resource instanceof Project) {
            $direct = $this->activeArea((int) ($resource->governance_area_id ?? 0));
            if ($direct !== null) {
                return $direct;
            }

            $owner = $resource->relationLoaded('owner') ? $resource->owner : $resource->owner()->first();

            return $owner instanceof Group ? $this->areaFor($owner) : null;
        }

        if ($resource instanceof SecretariatOffice) {
            $scope = $resource->relationLoaded('scope') ? $resource->scope : $resource->scope()->first();

            return $scope instanceof Model ? $this->areaFor($scope) : null;
        }

        return null;
    }

    public function capabilitiesFor(Model $resource): ?\App\Data\LocationGovernance\GovernanceCapabilities
    {
        $area = $this->areaFor($resource);

        return $area !== null ? $this->capabilityResolver->capabilities($area) : null;
    }

    private function activeArea(int $areaId): ?GovernanceArea
    {
        if ($areaId <= 0) {
            return null;
        }

        return GovernanceArea::query()
            ->whereKey($areaId)
            ->where('status', 'active')
            ->first();
    }
}
