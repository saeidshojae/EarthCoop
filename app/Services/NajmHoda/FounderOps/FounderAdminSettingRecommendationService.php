<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Services\Admin\AdminSettingManagementService;

class FounderAdminSettingRecommendationService
{
    public function __construct(protected AdminSettingManagementService $settings) {}

    /** @return array<string,mixed> */
    public function recommend(string $key, mixed $value): array
    {
        return $this->settings->recommend($key, $value);
    }
}
