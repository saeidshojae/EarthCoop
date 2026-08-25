<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\FounderOperationsDeskController;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminFounderOperationsDailyDeskTest extends TestCase
{
    public function test_founder_operations_index_uses_daily_desk_controller(): void
    {
        $route = Route::getRoutes()->match(request()->create('/admin/najm-hoda/founder-ops', 'GET'));

        $this->assertSame('admin.najm-hoda.founder-ops.index', $route->getName());
        $this->assertSame(FounderOperationsDeskController::class, $route->getActionName());
    }

    public function test_daily_desk_is_persian_and_structured_for_daily_management(): void
    {
        $view = file_get_contents(resource_path('views/admin/najm-hoda/founder-ops/daily-desk.blade.php'));

        $this->assertStringContainsString('میز کار روزانه مدیرکل', $view);
        $this->assertStringContainsString('خلاصه اجرایی نجم هدا', $view);
        $this->assertStringContainsString('کارهای امروز، به ترتیب اولویت', $view);
        $this->assertStringContainsString('تصمیم‌های منتظر شما', $view);
        $this->assertStringContainsString('مکان‌ها، صنف‌ها و تخصص‌ها', $view);
        $this->assertStringContainsString('پشتیبانی کاربران', $view);
        $this->assertStringContainsString('گزارش‌ها و پرونده‌های نظارتی', $view);
        $this->assertStringContainsString('ارتباطات و انتشار', $view);
        $this->assertStringContainsString('نمای سریع وضعیت EarthCoop', $view);
        $this->assertStringContainsString('حوزه‌های عملیاتی آرام هستند؛ چیزی برای رسیدگی روزانه ثبت نشده است.', $view);
        $this->assertStringContainsString('نمایش وضعیت فنی و پوشش قابلیت‌های نجم هدا', $view);

        $this->assertStringNotContainsString("@section('page-title', 'Founder Operations')", $view);
        $this->assertStringNotContainsString('صف تأیید Founder', $view);
        $this->assertStringNotContainsString('Delegation فعال', $view);
        $this->assertStringNotContainsString('Fail-closed', $view);
    }
}
