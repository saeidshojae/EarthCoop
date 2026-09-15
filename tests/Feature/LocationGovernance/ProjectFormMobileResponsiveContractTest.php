<?php

namespace Tests\Feature\LocationGovernance;

use Tests\TestCase;

class ProjectFormMobileResponsiveContractTest extends TestCase
{
    public function test_project_form_mobile_enhancer_defines_touch_first_layout_contract(): void
    {
        $source = file_get_contents(resource_path('js/project-form-mobile.js'));

        $this->assertIsString($source);
        $this->assertStringContainsString('project-form-page', $source);
        $this->assertStringContainsString('project-form-shell', $source);
        $this->assertStringContainsString('project-form-section', $source);
        $this->assertStringContainsString('project-form-actions', $source);
        $this->assertStringContainsString('project-scope-mobile-surface', $source);
        $this->assertStringContainsString('data-location-selector-context="project-scope"', $source);
        $this->assertStringContainsString('min-width: 0', $source);
        $this->assertStringContainsString('@media (max-width: 640px)', $source);
        $this->assertStringContainsString('position: sticky', $source);
        $this->assertStringContainsString('min-height: 44px', $source);
        $this->assertStringContainsString('overflow-wrap: anywhere', $source);
    }

    public function test_app_runtime_loads_mobile_enhancer_only_for_project_create_and_edit(): void
    {
        $source = file_get_contents(resource_path('js/app.js'));

        $this->assertIsString($source);
        $this->assertStringContainsString('project-form-mobile.js', $source);
        $this->assertStringContainsString("/najm-bahar/projects/create", $source);
        $this->assertStringContainsString("/edit", $source);
    }
}
