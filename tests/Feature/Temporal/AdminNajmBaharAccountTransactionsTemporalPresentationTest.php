<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class AdminNajmBaharAccountTransactionsTemporalPresentationTest extends TestCase
{
    public function test_admin_najm_bahar_account_transactions_use_temporal_datetime_component(): void
    {
        $path = 'resources/views/admin/najm-bahar/accounts/transactions.blade.php';
        $contents = file_get_contents(base_path($path));

        $this->assertStringContainsString('<x-temporal.date-time', $contents);
        $this->assertStringNotContainsString('Morilog\\Jalali', $contents);
        $this->assertStringNotContainsString('Jalalian::', $contents);
    }
}
