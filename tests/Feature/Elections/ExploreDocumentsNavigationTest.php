<?php

namespace Tests\Feature\Elections;

use Tests\TestCase;

class ExploreDocumentsNavigationTest extends TestCase
{
    public function test_desktop_explore_documents_submenu_exposes_all_canonical_destinations(): void
    {
        $source = file_get_contents(resource_path('views/partials/sidebar-unified.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('کاوش EarthCoop', $source);
        $this->assertStringContainsString('اسناد', $source);
        $this->assertStringContainsString('docs-submenu', $source);
        $this->assertStringContainsString('@mouseenter="docsOpen = true"', $source);
        $this->assertStringContainsString('@focusin="docsOpen = true"', $source);
        $this->assertStringContainsString('.docs-parent-button .support-submenu-label', $source);
        $this->assertStringContainsString('text-align: start !important;', $source);
        $this->assertStringContainsString("route('elections.guideline')", $source);
        $this->assertStringContainsString("route('participation.credit-regulation')", $source);
        $this->assertStringContainsString("config('docs-links')", $source);
        $this->assertStringContainsString("route('terms')", $source);
        $this->assertStringContainsString("route('najm-bahar.agreement')", $source);
        $this->assertStringContainsString('مرکز اسناد', $source);
        $this->assertStringContainsString('اسناد بنیادین', $source);
        $this->assertStringContainsString('اساسنامه', $source);
        $this->assertStringContainsString('توافقنامه مالی', $source);
        $this->assertStringContainsString('شیوه‌نامه انتخابات سیال', $source);
        $this->assertStringContainsString('نظام‌نامه اعتبارات مشارکت', $source);
    }

    public function test_mobile_explore_documents_are_a_tap_accordion_with_same_destinations(): void
    {
        $source = file_get_contents(resource_path('views/components/mobile-navigation-drawer.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('کاوش EarthCoop', $source);
        $this->assertStringContainsString("openDocumentSection", $source);
        $this->assertStringContainsString("@click=\"openDocumentSection = !openDocumentSection\"", $source);
        $this->assertStringContainsString('documents-navigation-toggle', $source);
        $this->assertStringContainsString('.documents-navigation-toggle > span', $source);
        $this->assertStringContainsString('text-align: start !important;', $source);
        $this->assertStringContainsString("route('elections.guideline')", $source);
        $this->assertStringContainsString("route('participation.credit-regulation')", $source);
        $this->assertStringContainsString("config('docs-links')", $source);
        $this->assertStringContainsString("route('terms')", $source);
        $this->assertStringContainsString("route('najm-bahar.agreement')", $source);
        $this->assertStringContainsString('مرکز اسناد', $source);
        $this->assertStringContainsString('اسناد بنیادین', $source);
        $this->assertStringContainsString('اساسنامه', $source);
        $this->assertStringContainsString('توافقنامه مالی', $source);
        $this->assertStringContainsString('شیوه‌نامه انتخابات سیال', $source);
        $this->assertStringContainsString('نظام‌نامه اعتبارات مشارکت', $source);
    }

    public function test_welcome_navigation_links_to_the_documents_section(): void
    {
        $headerSource = file_get_contents(resource_path('views/components/header-unified.blade.php'));
        $documentsSource = file_get_contents(resource_path('views/partials/docs-section.blade.php'));

        $this->assertIsString($headerSource);
        $this->assertIsString($documentsSource);
        $this->assertStringContainsString("['url' => '#documents'", $headerSource);
        $this->assertStringContainsString("__('langWelcome.docs_footer_title')", $headerSource);
        $this->assertStringContainsString('id="documents"', $documentsSource);
    }
}
