<?php

namespace Tests\Feature;

use Tests\TestCase;

class PrivateMessagingUiContractTest extends TestCase
{
    public function test_conversation_list_is_mobile_first_and_uses_private_conversation_language(): void
    {
        $view = file_get_contents(resource_path('views/private-chats/index.blade.php'));

        $this->assertStringContainsString('گفتگوهای خصوصی', $view);
        $this->assertStringContainsString('data-private-messaging-list', $view);
        $this->assertStringContainsString('data-unread-count', $view);
        $this->assertStringContainsString('unread_count', $view);
        $this->assertStringContainsString('@media (min-width: 769px)', $view);
        $this->assertStringNotContainsString('چت‌های خصوصی', $view);
        $this->assertStringNotContainsString('list-group-item-action private-chat-card mb-3', $view);
    }

    public function test_request_inbox_uses_single_mobile_first_received_sent_flow(): void
    {
        $view = file_get_contents(resource_path('views/chat-requests/partials/body.blade.php'));

        $this->assertStringContainsString('data-private-messaging-shell', $view);
        $this->assertStringContainsString('گفتگوها', $view);
        $this->assertStringContainsString('درخواست‌ها', $view);
        $this->assertStringContainsString('دریافتی', $view);
        $this->assertStringContainsString('ارسالی', $view);
        $this->assertStringContainsString("'box' => 'received'", $view);
        $this->assertStringContainsString("'box' => 'sent'", $view);
        $this->assertStringContainsString('@media (min-width: 769px)', $view);
        $this->assertStringNotContainsString('col-lg-6', $view);
        $this->assertStringNotContainsString('درخواست‌های چت', $view);
    }
}
