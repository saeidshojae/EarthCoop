<?php

namespace Tests\Feature;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class UnifiedLayoutContractTest extends TestCase
{
    public function test_legacy_app_layout_is_removed_and_no_blade_view_extends_it(): void
    {
        $legacyLayout = resource_path('views/layouts/app.blade.php');

        $this->assertFileDoesNotExist($legacyLayout, 'Legacy layouts.app must be removed after migration to layouts.unified.');

        $offenders = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(resource_path('views'), FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            if (str_contains($contents, "layouts.app")) {
                $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertSame([], $offenders, 'Blade views still referencing layouts.app: '.implode(', ', $offenders));
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
