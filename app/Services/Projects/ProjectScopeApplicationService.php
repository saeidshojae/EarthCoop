<?php

namespace App\Services\Projects;

use App\Models\GovernanceArea;
use App\Models\Location;
use Illuminate\Validation\ValidationException;

class ProjectScopeApplicationService
{
    public function __construct(private readonly ProjectLocationScopeResolver $resolver) {}

    public function normalize(array $data): array
    {
        if (! (bool) config('location-governance.projects_enabled', false)) {
            return $data;
        }

        if (! empty($data['target_location_id'])) {
            $location = Location::query()->find((int) $data['target_location_id']);
            if (! $location || $location->status !== 'active') {
                throw ValidationException::withMessages([
                    'target_location_id' => ['مکان انتخاب‌شده فعال نیست.'],
                ]);
            }

            $data['target_location_id'] = (int) $location->id;
            $data['governance_area_id'] = (int) $this->resolver->resolve($location)->id;

            return $data;
        }

        if (! empty($data['governance_area_id'])) {
            $valid = GovernanceArea::query()
                ->whereKey((int) $data['governance_area_id'])
                ->where('area_kind', 'official')
                ->where('status', 'active')
                ->exists();
            if (! $valid) {
                throw ValidationException::withMessages([
                    'governance_area_id' => ['محدوده حکمرانی انتخاب‌شده رسمی و فعال نیست.'],
                ]);
            }
            $data['governance_area_id'] = (int) $data['governance_area_id'];
        }

        $data['target_location_id'] = $data['target_location_id'] ?? null;

        return $data;
    }
}
