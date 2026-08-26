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

    public function test_css_is_a_first_class_vite_entry_instead_of_being_injected_from_javascript(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));
        $css = file_get_contents(resource_path('css/app.css'));
        $vite = file_get_contents(base_path('vite.config.js'));
        $unified = file_get_contents(resource_path('views/layouts/unified.blade.php'));
        $welcome = file_get_contents(resource_path('views/welcome.blade.php'));

        $this->assertStringNotContainsString('bootstrap/dist/css/bootstrap.min.css', $app);
        $this->assertStringNotContainsString('../css/app.css', $app);
        $this->assertStringContainsString('import "./bootstrap";', $app);
        $this->assertStringNotContainsString('import "bootstrap";', $app);

        $this->assertStringContainsString("@import 'bootstrap/dist/css/bootstrap.min.css';", $css);
        $this->assertStringContainsString('"resources/css/app.css"', $vite);
        $this->assertStringContainsString('"resources/js/app.js"', $vite);

        $viteDirective = "@vite(['resources/css/app.css', 'resources/js/app.js'])";
        $this->assertStringContainsString($viteDirective, $unified);
        $this->assertStringContainsString($viteDirective, $welcome);
    }

    public function test_app_entry_has_no_duplicate_header_stylesheet_loading(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertStringNotContainsString('header-mobile-polish.css', $app);
        $this->assertStringNotContainsString('document.createElement(\'link\')', $app);
    }

    public function test_unified_layout_owns_one_canonical_non_vite_header_polish_stylesheet(): void
    {
        $this->assertFileExists(public_path('Css/header-mobile-polish.css'));
        $this->assertFileDoesNotExist(resource_path('css/header-mobile-polish.css'));

        $unified = file_get_contents(resource_path('views/layouts/unified.blade.php'));
        $assetReference = "asset('Css/header-mobile-polish.css')";

        $this->assertSame(1, substr_count($unified, $assetReference));
    }

    public function test_validation_and_production_use_the_same_node_major_and_deploy_the_fresh_vite_build(): void
    {
        $validation = file_get_contents(base_path('.github/workflows/integration-full-validation.yml'));
        $deploy = file_get_contents(base_path('.github/workflows/deploy.yml'));

        $this->assertStringContainsString("node-version: '20'", $validation);
        $this->assertStringContainsString("node-version: '20'", $deploy);
        $this->assertStringContainsString('npm run build', $validation);
        $this->assertStringContainsString('npm run build', $deploy);
        $this->assertStringNotContainsString('**/public/build/**', $deploy, 'Fresh Vite build artifacts must be uploaded with the deployment.');
        $this->assertStringContainsString('test -f public/build/manifest.json', $validation);
        $this->assertStringContainsString('test -f public/build/manifest.json', $deploy);
    }
}
