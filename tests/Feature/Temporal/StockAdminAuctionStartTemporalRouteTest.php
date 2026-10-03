<?php

namespace Tests\Feature\Temporal;

use App\Modules\Stock\Controllers\CanonicalAdminAuctionLifecycleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StockAdminAuctionStartTemporalRouteTest extends TestCase
{
    public function test_admin_auction_start_route_uses_temporal_safe_lifecycle_controller(): void
    {
        $route = Route::getRoutes()->match(Request::create('/admin/auctions/123/start', 'POST'));

        $this->assertSame(
            CanonicalAdminAuctionLifecycleController::class.'@start',
            $route->getActionName(),
        );
    }
}
