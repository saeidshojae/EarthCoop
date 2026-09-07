<?php

namespace Tests\Feature\Reputation;

use Tests\TestCase;

class ReputationAdminPersianAuditContractTest extends TestCase
{
    public function test_reputation_admin_audit_uses_persian_semantic_labels_while_preserving_technical_trace_ids(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/ReputationController.php'));
        $view = file_get_contents(resource_path('views/admin/system-settings/reputation/index.blade.php'));

        $this->assertStringContainsString('dimensionLabels', $controller);
        $this->assertStringContainsString('conversionStatusLabels', $controller);
        $this->assertStringContainsString("'reliability' => 'اعتمادپذیری'", $controller);
        $this->assertStringContainsString("'applied' => 'انجام‌شده'", $controller);
        $this->assertStringContainsString("'pending' => 'در انتظار'", $controller);

        $this->assertStringContainsString('شناسه رویداد', $view);
        $this->assertStringContainsString('منبع / مرجع', $view);
        $this->assertStringContainsString('شناسه تبدیل', $view);
        $this->assertStringContainsString('$faLabels[$event->action]', $view);
        $this->assertStringContainsString('$dimensionLabels[$event->dimension]', $view);
        $this->assertStringContainsString('$conversionStatusLabels[$conversion->status]', $view);
        $this->assertStringContainsString('اعتمادپذیری', $view);

        $this->assertStringNotContainsString('source / reference</th>', $view);
        $this->assertStringNotContainsString('event_key</th>', $view);
        $this->assertStringNotContainsString('conversion_key</th>', $view);
    }
}
