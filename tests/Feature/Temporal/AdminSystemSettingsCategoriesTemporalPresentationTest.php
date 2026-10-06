<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class AdminSystemSettingsCategoriesTemporalPresentationTest extends TestCase
{
    public function test_categories_index_uses_shared_temporal_date_component(): void
    {
        $view = file_get_contents(base_path('resources/views/admin/system-settings/categories/index.blade.php'));

        $this->assertSame(
            1,
            substr_count($view, '<x-temporal.date :value="$category->created_at" style="short" />'),
        );
        $this->assertStringNotContainsString('verta(', $view);
    }
}
