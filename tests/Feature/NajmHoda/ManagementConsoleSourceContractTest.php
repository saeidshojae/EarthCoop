<?php

namespace Tests\Feature\NajmHoda;

use Tests\TestCase;

class ManagementConsoleSourceContractTest extends TestCase
{
    public function test_management_console_is_header_integrated_and_keeps_execution_guards(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));
        $console = file_get_contents(resource_path('js/najm-hoda-management-console-v2.js'));

        $this->assertStringContainsString('import "./najm-hoda-management-console-v2.js";', $app);
        $this->assertStringNotContainsString('import "./najm-hoda-management-console.js";', $app);
        $this->assertStringContainsString('GroupChatConfig?.canManageSession', $console);
        $this->assertStringContainsString('[2, 3].includes', $console);
        $this->assertStringContainsString("fetch('/api/najm-hoda/chat'", $console);
        $this->assertStringContainsString("send(panel,widget,'تأیید'", $console);
        $this->assertStringContainsString("send(panel,widget,'لغو'", $console);
        $this->assertStringContainsString('nh-management-header-button', $console);
        $this->assertStringContainsString('کنسول خدمات مدیریتی', $console);
        $this->assertStringContainsString('sessionParticipation?.showAdmin', $console);
        $this->assertStringContainsString('جلسه رسمی تنظیم کن', $console);
        $this->assertStringContainsString('تصمیمات صورتجلسه را استخراج کن', $console);
        $this->assertStringContainsString('موارد اقدام صورتجلسه را استخراج کن', $console);
        $this->assertStringContainsString('صف اقدام گروه را نشان بده', $console);
        $this->assertStringContainsString('ویرایش هدایت‌شده مورد اقدام', $console);
        $this->assertStringContainsString('action_status', $console);
        $this->assertStringContainsString('action_assignee', $console);
        $this->assertStringContainsString('action_due', $console);
        $this->assertStringContainsString('action_priority', $console);
        $this->assertStringContainsString('@media(max-width:420px)', $console);
    }
}
