<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaInstallExperienceTest extends TestCase
{
    public function test_install_entrypoint_is_available_across_welcome_and_application_layouts(): void
    {
        $welcome = file_get_contents(resource_path('views/welcome.blade.php'));
        $this->assertStringContainsString("@include('components.pwa-install-prompt')", $welcome);

        foreach (['master', 'unified', 'chat'] as $layout) {
            $source = file_get_contents(resource_path('views/layouts/'.$layout.'.blade.php'));
            $this->assertIsString($source);
            $this->assertStringContainsString("@include('components.pwa-install-prompt')", $source);
        }

        foreach ([
            'views/components/mobile-navigation-drawer.blade.php',
            'views/components/user-dropdown-unified.blade.php',
            'views/components/navbar.blade.php',
            'views/components/header-unified.blade.php',
        ] as $view) {
            $source = file_get_contents(resource_path($view));
            $this->assertIsString($source);
            $this->assertStringContainsString('data-pwa-install-trigger', $source, $view);
        }
    }

    public function test_manual_install_entrypoint_bypasses_the_automatic_prompt_cooldown(): void
    {
        $source = file_get_contents(resource_path('js/app.js'));
        $this->assertIsString($source);

        $this->assertStringContainsString("document.querySelectorAll('[data-pwa-install-trigger]')", $source);
        $this->assertStringContainsString("const canAutoShow = () =>", $source);
        $this->assertStringContainsString("const openInstallPanel = () =>", $source);
        $this->assertStringContainsString("triggers.forEach((trigger) => trigger.addEventListener('click', openInstallPanel));", $source);
        $this->assertStringContainsString("const showManualInstructions = () =>", $source);
        $this->assertStringContainsString("earthcoop-pwa-dismissed-at", $source);
    }
}
