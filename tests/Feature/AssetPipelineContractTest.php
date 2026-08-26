<?php

namespace Tests\Feature;

use Tests\TestCase;

class AssetPipelineContractTest extends TestCase
{
    public function test_tailwind_does_not_apply_global_preflight_over_legacy_bootstrap_views(): void
    {
        $config = file_get_contents(base_path('tailwind.config.js'));

        $this->assertStringContainsString(
            'preflight: false',
            $config,
            'EarthCoop mixes legacy Bootstrap views with Tailwind utilities; global Tailwind Preflight must stay disabled.'
        );
        $this->assertStringNotContainsString('preflight: true', $config);
    }

    public function test_app_entry_has_one_bootstrap_runtime_and_no_duplicate_header_stylesheet_loading(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('import "bootstrap/dist/css/bootstrap.min.css";', $app);
        $this->assertStringContainsString('import "../css/app.css";', $app);
        $this->assertStringContainsString('import "./bootstrap";', $app);

        $this->assertStringNotContainsString('import "bootstrap";', $app);
        $this->assertStringNotContainsString('header-mobile-polish.css', $app);
        $this->assertStringNotContainsString('document.createElement(\'link\')', $app);
    }

    public function test_header_polish_has_one_canonical_source_loaded_directly_by_supported_layouts(): void
    {
        $this->assertFileExists(public_path('Css/header-mobile-polish.css'));
        $this->assertFileDoesNotExist(resource_path('css/header-mobile-polish.css'));

        $unified = file_get_contents(resource_path('views/layouts/unified.blade.php'));
        $welcome = file_get_contents(resource_path('views/welcome.blade.php'));

        $assetReference = "asset('Css/header-mobile-polish.css')";
        $this->assertStringContainsString($assetReference, $unified);
        $this->assertStringContainsString($assetReference, $welcome);
    }
}
