<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
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

    public function test_css_is_a_first_class_vite_entry_and_never_runtime_injected_from_app_js(): void
    {
        $vite = file_get_contents(base_path('vite.config.js'));
        $app = file_get_contents(resource_path('js/app.js'));
        $cssEntry = file_get_contents(resource_path('css/vite.css'));

        $this->assertStringContainsString('"resources/css/vite.css"', $vite);
        $this->assertStringContainsString('"resources/js/app.js"', $vite);

        $this->assertStringContainsString('@import "bootstrap/dist/css/bootstrap.min.css";', $cssEntry);
        $this->assertStringContainsString('@import "./app.css";', $cssEntry);
        $this->assertStringContainsString('@import "select2/dist/css/select2.min.css";', $cssEntry);

        $this->assertStringNotContainsString('bootstrap/dist/css/bootstrap.min.css', $app);
        $this->assertStringNotContainsString('../css/app.css', $app);
        $this->assertStringNotContainsString('select2/dist/css/select2.min.css', $app);
        $this->assertStringNotContainsString('style[data-vite-dev-id]', $app);
        $this->assertStringNotContainsString('normalizeViteDevStyleOrder', $app);
        $this->assertStringNotContainsString('new MutationObserver', $app);
        $this->assertStringContainsString('import "./bootstrap";', $app);
        $this->assertStringNotContainsString('import "bootstrap";', $app);
    }

    public function test_every_blade_view_loading_app_js_loads_the_css_entry_first(): void
    {
        $root = resource_path('views');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        $offenders = [];

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if (! str_contains($contents, 'resources/js/app.js')) {
                continue;
            }

            $cssPosition = strpos($contents, 'resources/css/vite.css');
            $jsPosition = strpos($contents, 'resources/js/app.js');

            if ($cssPosition === false || $cssPosition > $jsPosition) {
                $offenders[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Every Blade view that loads app.js must load resources/css/vite.css first. Offenders:\n" . implode("\n", $offenders)
        );
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
