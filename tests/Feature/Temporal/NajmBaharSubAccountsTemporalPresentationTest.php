<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class NajmBaharSubAccountsTemporalPresentationTest extends TestCase
{
    public function test_sub_account_views_use_shared_temporal_datetime_components(): void
    {
        $index = file_get_contents(base_path('resources/views/najm-bahar/sub-accounts/index.blade.php'));
        $show = file_get_contents(base_path('resources/views/najm-bahar/sub-accounts/show.blade.php'));

        $this->assertStringContainsString('<x-temporal.date-time :value="$transfer->created_at" />', $index);
        $this->assertStringContainsString('<x-temporal.date-time :value="$subAccount->created_at" />', $show);

        $this->assertStringNotContainsString('Morilog\\Jalali', $index);
        $this->assertStringNotContainsString('Jalalian::', $index);
        $this->assertStringNotContainsString('Morilog\\Jalali', $show);
        $this->assertStringNotContainsString('Jalalian::', $show);
    }
}
