<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class AdminCategoryTemporalPresentationTest extends TestCase
{
    public function test_admin_category_created_date_uses_temporal_date_component(): void
    {
        $path = 'resources/views/admin/system-settings/categories/index.blade.php';
        $contents = file_get_contents(base_path($path));

        $this->assertStringContainsString('<x-temporal.date', $contents);
        $this->assertStringNotContainsString('verta(', $contents);
    }
}
