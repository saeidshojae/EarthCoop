<?php

namespace Tests\Feature\NajmHoda;

use Tests\TestCase;

class FounderMinistryChatViewTest extends TestCase
{
    public function test_founder_ministry_phase_two_surface_is_present_in_admin_chat(): void
    {
        $view = file_get_contents(resource_path('views/admin/najm-hoda/chat.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('وزارت هوشمند مدیرکل', $view);
        $this->assertStringContainsString('گفت‌وگوی آزاد', $view);
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
        $this->assertStringContainsString('actionForm(action)', $view);
        $this->assertStringContainsString("route('admin.najm-hoda.founder-ops.ministry.chat')", $view);
        $this->assertStringContainsString("route('admin.najm-hoda.chat.send')", $view);
        $this->assertStringContainsString("runMinistry({intent:'morning_brief'})", $view);
        $this->assertStringContainsString('هیچ approval یا authority را دور نمی‌زند', $view);
    }
}
