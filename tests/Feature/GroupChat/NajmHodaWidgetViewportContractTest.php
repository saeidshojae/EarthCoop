<?php

namespace Tests\Feature\GroupChat;

use Tests\TestCase;

class NajmHodaWidgetViewportContractTest extends TestCase
{
    public function test_widget_layout_runtime_is_loaded_only_when_najm_hoda_widget_exists(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString("import(\"./najm-hoda-widget-layout.js\")", $app);
        $this->assertStringContainsString("document.querySelector('#najm-hoda-widget')", $app);
    }

    public function test_widget_layout_runtime_keeps_panel_inside_visual_viewport_and_clear_of_group_composer(): void
    {
        $path = resource_path('js/najm-hoda-widget-layout.js');
        $this->assertFileExists($path);

        $runtime = file_get_contents($path);

        $this->assertStringContainsString('window.visualViewport', $runtime);
        $this->assertStringContainsString('ResizeObserver', $runtime);
        $this->assertStringContainsString("document.querySelector('.telegram-style-input')", $runtime);
        $this->assertStringContainsString("position', 'fixed', 'important'", $runtime);
        $this->assertStringContainsString("min-height', '0'", $runtime);
        $this->assertStringContainsString("group-chat:composer-replaced", $runtime);
        $this->assertStringContainsString('env(safe-area-inset-top)', $runtime);
    }
}
