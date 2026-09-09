<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ReferenceGeographyImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_diff_without_writing_and_reference_dataset_covers_required_iran_paths(): void
    {
        $before = Location::count();

        $exit = Artisan::call('location:reference-import', [
            'country' => 'IR',
            '--version' => 'v1',
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exit);
        $this->assertSame($before, Location::count(), 'Dry-run must not persist reference geography.');

        $output = Artisan::output();
        $this->assertStringContainsString('create', strtolower($output));
        $this->assertStringContainsString('update', strtolower($output));
        $this->assertStringContainsString('deactivate', strtolower($output));
        $this->assertStringContainsString('conflict', strtolower($output));

        $dataset = file_get_contents(database_path('reference/ir/v1/locations.jsonl'));
        $this->assertNotFalse($dataset);
        $this->assertStringContainsString('Sari', $dataset);
        $this->assertStringContainsString('Chahardangeh', $dataset);
        $this->assertStringContainsString('rural_district', $dataset);
        $this->assertStringContainsString('village', $dataset);
        $this->assertStringContainsString('without-neighborhood', $dataset);
    }
}
