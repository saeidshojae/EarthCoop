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

        $this->assertFileDoesNotExist($legacyLayout, 'Legacy layouts.app must be removed after migration away from layouts.app.');

        $offenders = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(resource_path('views'), FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            if (str_contains($contents, 'layouts.app')) {
                $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertSame([], $offenders, 'Blade views still referencing layouts.app: '.implode(', ', $offenders));
    }

    public function test_specialized_layouts_required_by_live_views_still_exist(): void
    {
        $this->assertFileExists(resource_path('views/layouts/chat.blade.php'));
        $this->assertFileExists(resource_path('views/layouts/admin.blade.php'));
        $this->assertFileExists(resource_path('views/layouts/master.blade.php'));

        $groupChat = file_get_contents(resource_path('views/groups/chat.blade.php'));
        $adminDashboard = file_get_contents(resource_path('views/admin/dashboard.blade.php'));
        $adminNajmPage = file_get_contents(resource_path('views/admin/najm/index.blade.php'));

        $this->assertStringContainsString("@extends('layouts.chat')", $groupChat);
        $this->assertStringContainsString("@extends('layouts.admin')", $adminDashboard);
        $this->assertStringContainsString("@extends('layouts.admin')", $adminNajmPage);
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
