<?php

namespace Tests\Feature\NajmHoda;

use Tests\TestCase;

class FounderOperationsDailyDeskViewTest extends TestCase
{
    public function test_daily_desk_is_action_first_and_persian(): void
    {
        $view = file_get_contents(resource_path('views/admin/najm-hoda/founder-ops/daily-desk.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('میز کار روزانه مدیرکل', $view);
        $this->assertStringContainsString('خلاصه اجرایی نجم هدا', $view);
        $this->assertStringContainsString('کارهای امروز، به ترتیب اولویت', $view);
        $this->assertStringContainsString('تصمیم‌های منتظر شما', $view);
        $this->assertStringContainsString('تأیید و ارسال', $view);
        $this->assertStringContainsString('تأیید و انتشار', $view);
        $this->assertStringContainsString('نمایش وضعیت فنی و پوشش قابلیت‌های نجم هدا', $view);
    }

    public function test_sensitive_founder_routes_have_a_strict_boundary_separate_from_generic_admin(): void
    {
        $provider = file_get_contents(app_path('Providers/RouteServiceProvider.php'));
        $generic = file_get_contents(app_path('Http/Middleware/AdminMiddleware.php'));
        $founder = file_get_contents(app_path('Http/Middleware/FounderOperationsMiddleware.php'));

        $this->assertStringContainsString('FounderOperationsMiddleware::class', $provider);
        $this->assertStringContainsString("roles()->exists()", $generic);
        $this->assertStringContainsString("hasRole('super-admin')", $founder);
        $this->assertStringNotContainsString("roles()->exists()", $founder);
    }
}
