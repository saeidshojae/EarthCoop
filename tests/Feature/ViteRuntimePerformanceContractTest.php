<?php

namespace Tests\Feature;

use Tests\TestCase;

class ViteRuntimePerformanceContractTest extends TestCase
{
    public function test_global_app_entry_does_not_eager_load_page_specific_feature_bundles(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));

        $forbiddenEagerImports = [
            'import "./najm-bahar.js";',
            'import "./najm-bahar-membership-source.js";',
            'import "./najm-hoda-context.js";',
            'import "./najm-hoda-management-console-v2.js";',
            'import "./najm-hoda-management-content-tools.js";',
            'import "./najm-hoda-management-native-tools.js";',
            'import "./najm-hoda-management-live-attention.js";',
            'import "./najm-hoda-attention-panel.js";',
            'import "./group-chat/index.js";',
            'import "./group-comment-form-fallback.js";',
            'import { register } from "swiper/element/bundle";',
        ];

        foreach ($forbiddenEagerImports as $import) {
            $this->assertStringNotContainsString(
                $import,
                $app,
                "Page-specific runtime must not be eagerly loaded by the global Vite entry: {$import}"
            );
        }

        $this->assertStringNotContainsString('import("./group-chat/index.js")', $app);
        $this->assertStringNotContainsString('meta[name="group-chat-id"]', $app);
        $this->assertStringContainsString('import("swiper/element/bundle")', $app);
        $this->assertStringContainsString('swiper-container', $app);
        $this->assertStringContainsString('import("./najm-hoda-context.js")', $app);
        $this->assertStringContainsString('#najm-hoda-widget', $app);
        $this->assertStringContainsString('import("./najm-bahar.js")', $app);
    }

    public function test_group_chat_uses_a_dedicated_vite_entry_instead_of_global_dynamic_import(): void
    {
        $viteConfig = file_get_contents(base_path('vite.config.js'));
        $resolver = file_get_contents(app_path('Support/EarthCoopVite.php'));
        $entry = file_get_contents(resource_path('js/group-chat-page.js'));

        $this->assertStringContainsString('resources/js/group-chat-page.js', $viteConfig);
        $this->assertStringContainsString("public const GROUP_CHAT_ENTRY = 'resources/js/group-chat-page.js';", $resolver);
        $this->assertStringContainsString("request()->is('groups/chat/*')", $resolver);
        $this->assertStringContainsString('import "./group-chat/index.js";', $entry);
    }

    public function test_najm_bahar_runtime_is_not_triggered_by_generic_card_classes(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertStringNotContainsString('.nb-card', $app);
        $this->assertStringNotContainsString('.nb-stat', $app);
        $this->assertStringContainsString("window.location.pathname.startsWith('/najm-bahar')", $app);
        $this->assertStringContainsString("document.querySelector('#membershipFeeModal, #payMembershipForm')", $app);
        $this->assertStringContainsString("document.querySelector('#najm-bahar-sidebar')", $app);
    }

    public function test_global_entry_keeps_only_shared_navigation_and_foundation_runtime_eager(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('import "./bootstrap";', $app);
        $this->assertStringContainsString('import "./site-navigation-history.js";', $app);
    }
}
