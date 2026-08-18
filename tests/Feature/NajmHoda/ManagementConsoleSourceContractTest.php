<?php

namespace Tests\Feature\NajmHoda;

use Tests\TestCase;

class ManagementConsoleSourceContractTest extends TestCase
{
    public function test_management_console_is_loaded_and_keeps_role_and_confirmation_guards(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));
        $console = file_get_contents(resource_path('js/najm-hoda-management-console.js'));

        $this->assertStringContainsString('import "./najm-hoda-management-console.js";', $app);
        $this->assertStringContainsString('config.canManageSession', $console);
        $this->assertStringContainsString('[2, 3].includes(Number(config.yourRole))', $console);
        $this->assertStringContainsString("fetch('/api/najm-hoda/chat'", $console);
        $this->assertStringContainsString("sendManagementMessage(panel, widget, 'تأیید'", $console);
        $this->assertStringContainsString("sendManagementMessage(panel, widget, 'لغو'", $console);
        $this->assertStringContainsString('window.GroupChat.sessionParticipation.showAdmin()', $console);
        $this->assertStringContainsString('جلسه رسمی تنظیم کن', $console);
        $this->assertStringContainsString('تصمیمات صورتجلسه را استخراج کن', $console);
        $this->assertStringContainsString('موارد اقدام صورتجلسه را استخراج کن', $console);
        $this->assertStringContainsString('صف اقدام گروه را نشان بده', $console);
        $this->assertStringContainsString('الان چه چیزهایی نیاز به توجه من دارد؟', $console);
    }
}
