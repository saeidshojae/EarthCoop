<?php

namespace Tests\Feature\Reputation;

use Tests\TestCase;

class WalletPointTransparencyContractTest extends TestCase
{
    public function test_wallet_distinguishes_social_total_from_convertible_consumed_and_remaining_participation_points(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/NajmBaharController.php'));
        $view = file_get_contents(resource_path('views/najm-bahar/wallet.blade.php'));

        $this->assertStringContainsString('$ledgerConsumedPoints = $pointSummary[\'ledger_consumed_points\'];', $controller);
        $this->assertStringContainsString('$legacyCashedPoints = $pointSummary[\'legacy_cashed_points\'];', $controller);
        $this->assertStringContainsString("'ledgerConsumedPoints'", $controller);
        $this->assertStringContainsString("'legacyCashedPoints'", $controller);

        $this->assertStringContainsString('مجموع امتیاز اعتبار و مشارکت', $view);
        $this->assertStringContainsString('مشارکت قابل تبدیل کسب‌شده', $view);
        $this->assertStringContainsString('مصرف‌شده در تبدیل', $view);
        $this->assertStringContainsString('مشارکت قابل تبدیل باقی‌مانده', $view);
        $this->assertStringNotContainsString('امتیاز پررنگ:', $view);
        $this->assertStringNotContainsString('امتیاز کمرنگ:', $view);
    }
}
