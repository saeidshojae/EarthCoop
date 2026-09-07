<?php

namespace Tests\Feature\Elections;

use Tests\TestCase;

class ElectionHistoryNavigationTest extends TestCase
{
    public function test_unified_sidebar_exposes_current_and_history_election_destinations(): void
    {
        $source = file_get_contents(resource_path('views/partials/sidebar-unified.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString("route('history.election')", $source);
        $this->assertStringContainsString('انتخابات جاری', $source);
        $this->assertStringContainsString("route('history.election-history')", $source);
        $this->assertStringContainsString('تاریخچه انتخابات من', $source);
        $this->assertStringContainsString("request()->routeIs('history.election-history')", $source);
    }
}
