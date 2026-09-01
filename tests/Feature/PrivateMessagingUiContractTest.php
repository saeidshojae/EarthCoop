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

    public function test_private_messaging_page_has_mobile_first_professional_shell(): void
    {
        $view = file_get_contents(resource_path('views/chat-requests/index.blade.php'));

        $this->assertStringContainsString("@section('title', 'گفتگوهای خصوصی", $view);
        $this->assertStringContainsString('data-private-messaging-page', $view);
        $this->assertStringContainsString('@media (min-width: 769px)', $view);
        $this->assertStringNotContainsString('پنل چت خصوصی', $view);
        $this->assertStringNotContainsString("@section('title', 'چت خصوصی", $view);
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

    public function test_unified_sidebar_uses_private_conversation_language(): void
    {
        $sidebar = file_get_contents(resource_path('views/partials/sidebar-unified.blade.php'));

        $this->assertStringContainsString('گفتگوهای خصوصی', $sidebar);
        $this->assertStringNotContainsString('درخواست‌های چت', $sidebar);
    }

    public function test_conversation_screen_is_mobile_viewport_first_and_text_only(): void
    {
        $view = file_get_contents(resource_path('views/private-chats/show.blade.php'));

        $this->assertStringContainsString('data-private-conversation', $view);
        $this->assertStringContainsString('100dvh', $view);
        $this->assertStringContainsString('data-conversation-header', $view);
        $this->assertStringContainsString('data-conversation-composer', $view);
        $this->assertStringContainsString('data-read-receipt', $view);
        $this->assertStringContainsString('@media (min-width: 769px)', $view);
        $this->assertStringNotContainsString('max-height: 500px', $view);
        $this->assertStringNotContainsString('max-height: 400px', $view);
        $this->assertStringNotContainsString('در حال بررسی...', $view);
        $this->assertStringNotContainsString('type="file"', $view);
        $this->assertStringNotContainsString('fa-microphone', $view);
    }

    public function test_reaction_picker_is_viewport_clamped_and_cannot_create_horizontal_scroll(): void
    {
        $runtime = file_get_contents(resource_path('js/private-messaging-reaction-picker.js'));
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('positionReactionPicker', $runtime);
        $this->assertStringContainsString('getBoundingClientRect()', $runtime);
        $this->assertStringContainsString('document.documentElement.clientWidth', $runtime);
        $this->assertStringContainsString('document.body.appendChild(picker)', $runtime);
        $this->assertStringContainsString('private-messaging-reaction-picker.js', $app);
    }

    public function test_profile_request_flow_is_state_aware_and_mobile_bottom_sheet_ready(): void
    {
        $profileAction = file_get_contents(resource_path('views/chat-requests/partials/profile-action.blade.php'));
        $legacyPartial = file_get_contents(resource_path('views/chat_request.blade.php'));

        $this->assertStringContainsString('data-private-request-action', $profileAction);
        $this->assertStringContainsString('شروع گفتگو', $profileAction);
        $this->assertStringContainsString('درخواست ارسال شده', $profileAction);
        $this->assertStringContainsString('مشاهده درخواست', $profileAction);
        $this->assertStringContainsString('ادامه گفتگو', $profileAction);
        $this->assertStringContainsString('درخواست مجدد گفتگو', $profileAction);
        $this->assertStringContainsString('role="dialog"', $profileAction);
        $this->assertStringContainsString('data-private-request-sheet', $profileAction);
        $this->assertStringContainsString('@media (min-width: 769px)', $profileAction);
        $this->assertStringContainsString('document.body.appendChild(sheet)', $profileAction);
        $this->assertStringContainsString('document.body.classList.add', $profileAction);
        $this->assertStringNotContainsString('document.documentElement.style.overflow', $profileAction);
        $this->assertStringNotContainsString('.btn {', $legacyPartial);
        $this->assertStringNotContainsString('.list-group-item {', $legacyPartial);
    }

    public function test_private_message_read_receipts_have_realtime_and_polling_runtime(): void
    {
        $runtime = file_get_contents(resource_path('js/private-messaging-read-receipts.js'));
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('.private-messages.read', $runtime);
        $this->assertStringContainsString('.private-message.created', $runtime);
        $this->assertStringContainsString('/info', $runtime);
        $this->assertStringContainsString('document.hidden', $runtime);
        $this->assertStringContainsString('private-messaging-read-receipts.js', $app);
    }
}
