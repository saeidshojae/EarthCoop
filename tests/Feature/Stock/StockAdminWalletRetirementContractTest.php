<?php

namespace Tests\Feature\Stock;

use Tests\TestCase;

class StockAdminWalletRetirementContractTest extends TestCase
{
    public function test_admin_stock_dashboard_resolves_canonical_module_view_and_has_no_wallet_or_rial_surface(): void
    {
        $provider = file_get_contents(app_path('Providers/StockExternalCapitalServiceProvider.php'));
        $controllerPath = app_path('Modules/Stock/Controllers/CanonicalStockAdminController.php');
        $view = file_get_contents(app_path('Modules/Stock/Views/admin_stock_info.blade.php'));

        $this->assertFileExists($controllerPath);
        $controller = file_get_contents($controllerPath);

        $this->assertStringContainsString('CanonicalStockAdminController::class', $provider);
        $this->assertStringContainsString("view('Stock::admin_stock_info'", $controller);
        $this->assertStringNotContainsString("view('stock.admin_stock_info'", $controller);

        $this->assertStringNotContainsString("route('admin.wallet.index')", $view);
        $this->assertStringNotContainsString('مدیریت کیف پول', $view);
        $this->assertStringNotContainsString('ریال', $view);
        $this->assertStringNotContainsString('تومان', $view);
        $this->assertStringContainsString('ارزش‌گذاری کل', $view);
        $this->assertStringContainsString('گل', $view);
        $this->assertStringContainsString('بهار', $view);
    }
}
