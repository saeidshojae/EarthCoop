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

        $this->assertStringContainsString('import("./group-chat/index.js")', $app);
        $this->assertStringContainsString('meta[name="group-chat-id"]', $app);
        $this->assertStringContainsString('import("swiper/element/bundle")', $app);
        $this->assertStringContainsString('swiper-container', $app);
        $this->assertStringContainsString('import("./najm-hoda-context.js")', $app);
        $this->assertStringContainsString('#najm-hoda-widget', $app);
        $this->assertStringContainsString('import("./najm-bahar.js")', $app);
    }

    public function test_global_entry_keeps_only_shared_navigation_and_foundation_runtime_eager(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('import "./bootstrap";', $app);
        $this->assertStringContainsString('import "./site-navigation-history.js";', $app);
    }
}
