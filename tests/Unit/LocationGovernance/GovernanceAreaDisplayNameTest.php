<?php

namespace Tests\Unit\LocationGovernance;

use App\Models\GovernanceArea;
use App\Support\GovernanceAreaDisplayName;
use Tests\TestCase;

final class GovernanceAreaDisplayNameTest extends TestCase
{
    public function test_prefers_requested_locale_and_falls_back_to_language_then_canonical(): void
    {
        $area = new GovernanceArea([
            'key' => 'reference-test-area',
            'canonical_name' => 'Reference Village Without Neighborhood',
            'localized_names' => [
                'fa' => 'روستای مرجع بدون محله',
                'en' => 'Reference Village Without Neighborhood',
            ],
        ]);

        $this->assertSame('روستای مرجع بدون محله', GovernanceAreaDisplayName::for($area, 'fa-IR'));
        $this->assertSame('Reference Village Without Neighborhood', GovernanceAreaDisplayName::for($area, 'en'));
        $this->assertSame('Reference Village Without Neighborhood', GovernanceAreaDisplayName::for($area, 'de'));
        $this->assertSame('—', GovernanceAreaDisplayName::for(null));
    }

    public function test_empty_localized_names_do_not_hide_canonical_name(): void
    {
        $area = new GovernanceArea([
            'key' => 'fallback',
            'canonical_name' => 'Approved Canonical Area',
            'localized_names' => ['fa' => ''],
        ]);
        $this->assertSame('Approved Canonical Area', GovernanceAreaDisplayName::for($area, 'fa'));
    }
}
