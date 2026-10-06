<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class NajmHodaAutoFixerTemporalPresentationTest extends TestCase
{
    public function test_auto_fixer_uses_server_side_temporal_backup_label(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/NajmHodaController.php'));
        $view = file_get_contents(resource_path('views/admin/najm-hoda/auto-fixer-settings.blade.php'));

        $this->assertStringContainsString("'oldest_backup_label'", $controller);
        $this->assertStringContainsString('->date(', $controller);
        $this->assertStringContainsString('data.stats.oldest_backup_label', $view);
        $this->assertStringNotContainsString("toLocaleDateString('fa-IR'", $view);
    }
}
