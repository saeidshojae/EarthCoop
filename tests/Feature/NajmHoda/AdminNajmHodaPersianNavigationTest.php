<?php

namespace Tests\Feature\NajmHoda;

use Tests\TestCase;

class AdminNajmHodaPersianNavigationTest extends TestCase
{
    public function test_najm_hoda_sidebar_uses_persian_expandable_submenu_labels(): void
    {
        $sidebar = file_get_contents(resource_path('views/admin/partials/sidebar.blade.php'));

        $this->assertIsString($sidebar);
        $this->assertStringContainsString('نجم هُدی', $sidebar);
        $this->assertStringContainsString('داشبورد نجم هُدی', $sidebar);
        $this->assertStringContainsString('مرکز مدیریت کل', $sidebar);
        $this->assertStringContainsString("route('admin.najm-hoda.founder-ops.index')", $sidebar);
        $this->assertStringContainsString("request()->routeIs('admin.najm-hoda.founder-ops.*')", $sidebar);
        $this->assertStringContainsString('حکمرانی خودمختار', $sidebar);
        $this->assertStringContainsString('مدیریت n8n', $sidebar);
        $this->assertStringContainsString('x-data="{ open:', $sidebar);
        $this->assertStringNotContainsString('Governance / Autonomy', $sidebar);
    }

    public function test_governance_dashboard_is_persian_first_and_guided(): void
    {
        $view = file_get_contents(resource_path('views/admin/najm-hoda/governance-dashboard.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('حکمرانی خودمختار نجم هُدی', $view);
        $this->assertStringContainsString('راهنمای این صفحه', $view);
        $this->assertStringContainsString('کنترل‌های ایمنی و خودمختاری', $view);
        $this->assertStringContainsString('توقف اضطراری', $view);
        $this->assertStringContainsString('فقط پیشنهاد', $view);
        $this->assertStringNotContainsString('Najm Hoda Governance Dashboard', $view);
    }
}
