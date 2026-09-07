<?php

namespace Tests\Feature\NajmHoda;

use Tests\TestCase;

class NajmHodaWidgetAvatarContractTest extends TestCase
{
    public function test_widget_uses_the_najm_hoda_avatar_asset_for_its_visible_identity(): void
    {
        $widget = file_get_contents(resource_path('views/components/najm-hoda-widget.blade.php'));

        $this->assertStringContainsString("data-avatar-url=\"{{ asset('images/najm-hoda/avatar.webp') }}\"", $widget);
        $this->assertStringContainsString('class="najm-hoda-toggle-avatar"', $widget);
        $this->assertStringContainsString('class="najm-hoda-header-avatar"', $widget);
        $this->assertStringContainsString('class="najm-hoda-message-avatar-image"', $widget);
        $this->assertStringNotContainsString('<i class="fas fa-robot"></i>', $widget);
    }

    public function test_dynamic_assistant_messages_reuse_the_same_avatar_instead_of_agent_emoji(): void
    {
        $widget = file_get_contents(resource_path('views/components/najm-hoda-widget.blade.php'));

        $this->assertStringContainsString('getAvatarUrl()', $widget);
        $this->assertStringContainsString('role === \'assistant\'', $widget);
        $this->assertStringContainsString('najm-hoda-message-avatar-image', $widget);
        $this->assertStringContainsString('this.addMessage(data.message, \'assistant\'', $widget);
    }
}
