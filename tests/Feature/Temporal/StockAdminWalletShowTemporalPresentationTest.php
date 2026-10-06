<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class StockAdminWalletShowTemporalPresentationTest extends TestCase
{
    public function test_admin_wallet_show_uses_shared_temporal_datetime_component(): void
    {
        $view = file_get_contents(base_path('resources/views/Stock/admin_wallet_show.blade.php'));

        $this->assertSame(1, substr_count($view, '<x-temporal.date-time :value="$createdAt" />'));
        $this->assertStringNotContainsString('Morilog\\Jalali', $view);
        $this->assertStringNotContainsString('Jalalian::', $view);
    }
}
