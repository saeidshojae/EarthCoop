<?php

namespace Tests\Feature\Reputation;

use Tests\TestCase;

class ReputationAdminAuditReadModelContractTest extends TestCase
{
    public function test_admin_reputation_page_exposes_read_only_event_and_conversion_audit_data(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/ReputationController.php'));
        $view = file_get_contents(resource_path('views/admin/system-settings/reputation/index.blade.php'));

        $this->assertStringContainsString('UserPointTransaction::query()', $controller);
        $this->assertStringContainsString('UserPointConversion::query()', $controller);
        $this->assertStringContainsString("withSum('consumptions as consumed_points_total', 'points_consumed')", $controller);
        $this->assertStringContainsString("'recentPointEvents'", $controller);
        $this->assertStringContainsString("'recentConversions'", $controller);

        $this->assertStringContainsString('ممیزی رویدادهای امتیاز', $view);
        $this->assertStringContainsString('دفتر مصرف و تبدیل مشارکت', $view);
        $this->assertStringContainsString('event_key', $view);
        $this->assertStringContainsString('conversion_key', $view);
        $this->assertStringContainsString('فقط‌خواندنی', $view);
        $this->assertStringNotContainsString('name="event_key', $view);
        $this->assertStringNotContainsString('name="conversion_key', $view);
    }
}
