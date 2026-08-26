<?php

namespace Tests\Feature;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class UnifiedLayoutContractTest extends TestCase
{
    public function test_only_unified_general_layout_remains_and_no_blade_view_references_retired_layouts(): void
    {
        $retiredLayouts = ['app', 'master', 'admin', 'chat'];

        foreach ($retiredLayouts as $layout) {
            $this->assertFileDoesNotExist(
                resource_path("views/layouts/{$layout}.blade.php"),
                "Retired layouts.{$layout} must not exist after consolidation onto layouts.unified."
            );
        }

        $offenders = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(resource_path('views'), FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            foreach ($retiredLayouts as $layout) {
                if (str_contains($contents, "layouts.{$layout}")) {
                    $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname())." -> layouts.{$layout}";
                }
            }
        }

        $this->assertSame([], $offenders, 'Blade views still referencing retired layouts: '.implode(', ', $offenders));
    }

    public function test_unified_layout_does_not_use_a_wildcard_header_class_selector(): void
    {
        $contents = file_get_contents(resource_path('views/layouts/unified.blade.php'));

        $this->assertStringNotContainsString(
            '[class*="header"]',
            $contents,
            'The unified layout must not globally restyle every class containing "header".'
        );
    }
}
