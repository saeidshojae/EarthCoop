<?php

namespace Tests\Feature\Reputation;

use Tests\TestCase;

class ParticipationConversionLedgerContractTest extends TestCase
{
    public function test_conversion_consumes_only_whole_ratio_multiples_and_preserves_remainder(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/ReputationConversionController.php'));

        $this->assertStringContainsString('$convertiblePoints = intdiv($pointsToConvert, $ratio) * $ratio;', $source);
        $this->assertStringContainsString('$amountInGol = intdiv($convertiblePoints, $ratio);', $source);
        $this->assertStringContainsString("'points_converted' => \$convertiblePoints", $source);
    }

    public function test_conversion_records_partial_consumption_instead_of_cashing_entire_source_transaction(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/ReputationConversionController.php'));

        $this->assertStringContainsString('UserPointConsumption::create([', $source);
        $this->assertStringContainsString("'user_point_transaction_id' => \$tx->id", $source);
        $this->assertStringContainsString("'points_consumed' => \$toConsume", $source);
        $this->assertStringNotContainsString('$tx->is_cashed = true;', $source);
    }

    public function test_available_convertible_points_are_calculated_from_policy_snapshot_minus_consumption(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/ReputationConversionController.php'));

        $this->assertStringContainsString("->where('convertible', true)", $source);
        $this->assertStringContainsString("->where('dimension', 'participation')", $source);
        $this->assertStringContainsString('consumptions_sum_points_consumed', $source);
    }
}
