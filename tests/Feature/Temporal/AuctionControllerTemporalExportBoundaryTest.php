<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class AuctionControllerTemporalExportBoundaryTest extends TestCase
{
    public function test_auction_csv_export_uses_temporal_service_instead_of_verta(): void
    {
        $path = 'app/Modules/Stock/Controllers/AuctionController.php';
        $contents = file_get_contents(base_path($path));

        $this->assertStringContainsString('use App\\Temporal\\Contracts\\TemporalService;', $contents);
        $this->assertStringContainsString('private readonly TemporalService $temporal', $contents);
        $this->assertStringContainsString("\$this->temporal->dateTime(\$auction->start_time, style: 'short')", $contents);
        $this->assertStringContainsString("\$this->temporal->dateTime(\$auction->ends_at, style: 'short')", $contents);
        $this->assertStringContainsString("\$this->temporal->dateTime(\$b->created_at, style: 'short')", $contents);
        $this->assertStringNotContainsString('verta(', $contents);
    }
}
