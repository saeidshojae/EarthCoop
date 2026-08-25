<?php

namespace Tests\Feature\NajmHoda;

use Tests\TestCase;

class FounderMinistryChatViewTest extends TestCase
{
    public function test_founder_ministry_phase_three_separates_current_report_from_conversation(): void
    {
        $view = file_get_contents(resource_path('views/admin/najm-hoda/chat.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('وزارت هوشمند مدیرکل', $view);
        $this->assertStringContainsString('گفت‌وگوی آزاد', $view);
        $this->assertStringContainsString('id="executiveReport"', $view);
        $this->assertStringContainsString('id="reportTitle"', $view);
        $this->assertStringContainsString('id="reportAssessment"', $view);
        $this->assertStringContainsString('id="reportAction"', $view);
        $this->assertStringContainsString('id="conversationLabel"', $view);
        $this->assertStringContainsString('گفت‌وگو با وزیر', $view);
        $this->assertStringContainsString('دکمه‌های بالا فقط گزارش جاری را عوض می‌کنند', $view);
        $this->assertStringContainsString('data-global="urgent"', $view);
        $this->assertStringContainsString('data-global="founder_decisions"', $view);
        $this->assertStringContainsString('data-global="prepared"', $view);
        $this->assertStringContainsString('data-global="information"', $view);

        foreach ([
            'morning_brief', 'urgent_items', 'pending_approvals', 'communications', 'system_health', 'end_of_day',
            'users_registration', 'reference_data', 'support_moderation', 'groups', 'governance',
            'najm_bahar', 'stock', 'secretariat', 'authority',
        ] as $intent) {
            $this->assertStringContainsString('data-intent="'.$intent.'"', $view);
        }

        $this->assertStringContainsString('رسیدگی / جزئیات', $view);
        $this->assertStringContainsString('function renderReport(data)', $view);
        $this->assertStringContainsString('function renderItems(items)', $view);
        $this->assertStringContainsString('function switchPane(pane)', $view);
        $this->assertStringContainsString("el.classList.toggle('active',el.id===pane)", $view);
        $this->assertStringContainsString("conversationLabel.textContent=ministry?", $view);
        $this->assertStringContainsString("route('admin.najm-hoda.founder-ops.ministry.chat')", $view);
        $this->assertStringContainsString("route('admin.najm-hoda.chat.send')", $view);
        $this->assertStringContainsString("runMinistry({intent:'morning_brief'})", $view);
        $this->assertStringContainsString('فرمان حساس از متن آزاد استنباط نمی‌شود', $view);
        $this->assertStringContainsString('background:#1d4ed8!important;color:#fff!important', $view);
        $this->assertStringContainsString('گفت‌وگوی آزاد — مستقل از پنل گزارش مدیریتی', $view);
    }
}
