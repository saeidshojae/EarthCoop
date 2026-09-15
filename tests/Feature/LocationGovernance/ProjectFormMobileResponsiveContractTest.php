<?php

namespace Tests\Feature\LocationGovernance;

use Tests\TestCase;

class ProjectFormMobileResponsiveContractTest extends TestCase
{
    /** @dataProvider projectFormViews */
    public function test_project_forms_are_touch_first_without_mobile_overflow(string $view): void
    {
        $source = file_get_contents(resource_path("views/najm-bahar/projects/{$view}.blade.php"));

        $this->assertIsString($source);
        $this->assertStringContainsString('project-form-page', $source);
        $this->assertStringContainsString('project-form-shell', $source);
        $this->assertStringContainsString('project-form-section', $source);
        $this->assertStringContainsString('project-form-actions', $source);
        $this->assertMatchesRegularExpression('/\.project-form-page[^{]*\{[^}]*min-width:\s*0/s', $source);
        $this->assertMatchesRegularExpression('/\.project-form-shell[^{]*\{[^}]*min-width:\s*0/s', $source);
        $this->assertMatchesRegularExpression('/\.project-form-section[^{]*\{[^}]*min-width:\s*0/s', $source);
        $this->assertMatchesRegularExpression('/@media\s*\(max-width:\s*640px\)[\s\S]*?\.project-form-actions[^{]*\{[^}]*position:\s*sticky/s', $source);
        $this->assertMatchesRegularExpression('/@media\s*\(max-width:\s*640px\)[\s\S]*?\.project-form-actions[^}]*bottom:/s', $source);
        $this->assertMatchesRegularExpression('/@media\s*\(max-width:\s*640px\)[\s\S]*?\.project-form-actions[^}]*button[^}]*min-height:\s*44px/s', $source);
        $this->assertStringNotContainsString('min-width: 700px', $source);
        $this->assertStringNotContainsString('min-width: 880px', $source);
    }

    /** @dataProvider projectFormViews */
    public function test_project_scope_picker_has_mobile_safe_hierarchy_and_state_surface(string $view): void
    {
        $source = file_get_contents(resource_path("views/najm-bahar/projects/{$view}.blade.php"));

        $this->assertIsString($source);
        $this->assertStringContainsString('project-scope-mobile-surface', $source);
        $this->assertMatchesRegularExpression('/\.project-scope-mobile-surface[^{]*\{[^}]*min-width:\s*0/s', $source);
        $this->assertMatchesRegularExpression('/\.project-scope-mobile-surface[^}]*select[^{]*\{[^}]*min-height:\s*44px/s', $source);
        $this->assertMatchesRegularExpression('/@media\s*\(max-width:\s*640px\)[\s\S]*?\.project-scope-mobile-surface[^}]*overflow-wrap:\s*anywhere/s', $source);
    }

    public static function projectFormViews(): array
    {
        return [
            'create' => ['create'],
            'edit' => ['edit'],
        ];
    }
}
