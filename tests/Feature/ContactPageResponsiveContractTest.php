<?php

namespace Tests\Feature;

use Tests\TestCase;

class ContactPageResponsiveContractTest extends TestCase
{
    public function test_contact_page_has_mobile_first_layout_and_centered_hero_actions(): void
    {
        $source = file_get_contents(resource_path('views/pages/templates/contact.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('contact-page-container', $source);
        $this->assertStringContainsString('contact-hero-grid', $source);
        $this->assertStringContainsString('contact-hero-actions', $source);
        $this->assertStringContainsString('justify-content: center;', $source);
        $this->assertStringContainsString('grid-template-columns: 1fr;', $source);
        $this->assertStringContainsString('@media (min-width: 1024px)', $source);
        $this->assertStringContainsString('grid-template-columns: minmax(0, 1.2fr) minmax(18rem, .8fr)', $source);
    }

    public function test_contact_page_protects_narrow_screens_from_overflow_and_keeps_touch_targets_usable(): void
    {
        $source = file_get_contents(resource_path('views/pages/templates/contact.blade.php'));

        $this->assertStringContainsString('width: min(100% - 1.25rem, 72rem);', $source);
        $this->assertStringContainsString('overflow-x: clip;', $source);
        $this->assertStringContainsString('overflow-wrap: anywhere;', $source);
        $this->assertStringContainsString('min-height: 48px;', $source);
        $this->assertStringContainsString('font-size: 16px;', $source);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $source);
    }

    public function test_contact_phone_destination_is_consistent_across_primary_contact_surfaces(): void
    {
        $source = file_get_contents(resource_path('views/pages/templates/contact.blade.php'));

        $this->assertSame(3, substr_count($source, 'tel:+989394765289'));
        $this->assertStringNotContainsString('tel:+982112345678', $source);
        $this->assertStringNotContainsString('+98 9394865289', $source);
    }
}
