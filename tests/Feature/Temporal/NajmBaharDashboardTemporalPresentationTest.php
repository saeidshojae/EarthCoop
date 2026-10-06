<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class NajmBaharDashboardTemporalPresentationTest extends TestCase
{
    public function test_dashboard_uses_temporal_date_labels_and_components(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/NajmBaharDashboardController.php'));
        $view = file_get_contents(resource_path('views/admin/najm-bahar/dashboard.blade.php'));

        $this->assertStringContainsString('TemporalService', $controller);
        $this->assertStringContainsString('TemporalContextResolver', $controller);
        $this->assertStringContainsString('$item->date_label = $this->temporal->date(', $controller);
        $this->assertStringContainsString("'month-day'", $controller);

        $this->assertStringContainsString('<x-temporal.date-time :value="$log->created_at" />', $view);
        $this->assertStringContainsString('<x-temporal.date-time :value="$transaction->created_at" />', $view);
        $this->assertStringContainsString('item.date_label', $view);
        $this->assertStringNotContainsString('Morilog\\Jalali', $view);
        $this->assertStringNotContainsString('Jalalian::', $view);
        $this->assertStringNotContainsString("toLocaleDateString('fa-IR'", $view);
    }
}
