<?php

namespace Tests\Feature\LocationGovernance;

use Tests\TestCase;

class ProjectFormMobileUatPolishContractTest extends TestCase
{
    public function test_mobile_project_form_repairs_choice_rows_and_dense_text_found_in_uat(): void
    {
        $source = file_get_contents(resource_path('js/project-form-mobile.js'));

        $this->assertIsString($source);
        $this->assertStringContainsString('project-form-choice-row', $source);
        $this->assertStringContainsString('project-form-choice-copy', $source);
        $this->assertStringContainsString('project-form-investment-option', $source);
        $this->assertStringContainsString('input[name="investment_method"]', $source);
        $this->assertStringContainsString('input[type="checkbox"]', $source);
        $this->assertStringContainsString('grid-template-columns: auto minmax(0, 1fr)', $source);
        $this->assertStringContainsString('min-height: 48px', $source);
        $this->assertStringContainsString('font-size: 0.875rem', $source);
    }

    public function test_mobile_project_form_keeps_safe_space_for_hoda_and_sticky_actions(): void
    {
        $source = file_get_contents(resource_path('js/project-form-mobile.js'));

        $this->assertIsString($source);
        $this->assertStringContainsString('padding-bottom: calc(5.5rem + env(safe-area-inset-bottom))', $source);
        $this->assertStringContainsString('project-form-hoda-safe', $source);
        $this->assertStringContainsString('#najm-hoda-widget', $source);
    }
}
