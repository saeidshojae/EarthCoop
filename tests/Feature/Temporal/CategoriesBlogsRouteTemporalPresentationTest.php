<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class CategoriesBlogsRouteTemporalPresentationTest extends TestCase
{
    public function test_category_blogs_route_uses_temporal_datetime_formatting(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));

        $this->assertStringContainsString('->dateTime(', $routes);
        $this->assertStringContainsString("'short'", $routes);
        $this->assertStringNotContainsString('verta(', $routes);
    }
}
