<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class StockControllerTemporalBoundaryTest extends TestCase
{
    public function test_stock_chart_month_labels_use_temporal_service_boundary(): void
    {
        $path = 'app/Modules/Stock/Controllers/StockController.php';
        $contents = file_get_contents(base_path($path));

        $this->assertStringContainsString('use App\\Temporal\\Contracts\\TemporalService;', $contents);
        $this->assertStringContainsString('private readonly TemporalService $temporal', $contents);
        $this->assertStringContainsString("substr(\$this->temporal->date(\$date, style: 'short'), 0, 7)", $contents);
        $this->assertStringNotContainsString('Jalalian::', $contents);
        $this->assertStringNotContainsString('Morilog\\Jalali', $contents);
    }
}
