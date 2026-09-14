<?php

namespace App\Services\Projects;

use App\Models\GovernanceArea;
use App\Models\Location;
use Illuminate\Validation\ValidationException;

class ProjectLocationScopeResolver
{
    /**
     * Resolve the closest official, active GovernanceArea for an active canonical
     * Location. Exact project geography remains the Location; formal governance
     * scope is derived from the nearest mapped ancestor and is never client-trusted.
     */
    public function resolve(Location $location): GovernanceArea
    {
        if ($location->status !== 'active') {
            throw ValidationException::withMessages([
                'target_location_id' => 'مکان انتخاب‌شده فعال نیست.',
            ]);
        }

        $cursor = $location;

        while ($cursor !== null) {
            $area = $cursor->governanceAreas()
                ->official()
                ->active()
                ->orderByDesc('rank')
                ->orderBy('id')
                ->first();

            if ($area !== null) {
                return $area;
            }

            $cursor = $cursor->parent()->first();
        }

        throw ValidationException::withMessages([
            'target_location_id' => 'برای این مکان هنوز محدوده حکمرانی رسمی و فعال قابل انتسابی وجود ندارد.',
        ]);
    }
}
