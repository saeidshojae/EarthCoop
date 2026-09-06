<?php

namespace Tests\Feature\Reputation;

use Tests\TestCase;

class StockParticipationEventIdentityContractTest extends TestCase
{
    public function test_legacy_and_service_bid_placement_use_stable_bid_identity(): void
    {
        $controller = file_get_contents(app_path('Modules/Stock/Controllers/BidController.php'));
        $service = file_get_contents(app_path('Modules/Stock/Services/AuctionService.php'));

        $eventKey = "'bid_placed:bid:' . \$bid->id . ':user:' . \$user->id";

        $this->assertStringContainsString($eventKey, $controller);
        $this->assertStringContainsString($eventKey, $service);
    }

    public function test_winning_and_settlement_awards_share_one_stable_identity_per_bid_and_recipient(): void
    {
        $service = file_get_contents(app_path('Modules/Stock/Services/AuctionService.php'));

        $this->assertGreaterThanOrEqual(
            2,
            substr_count($service, "'bid_won:bid:' . \$bid->id . ':user:' . \$user->id")
        );
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($service, "'successful_settlement:bid:' . \$bid->id . ':user:' . \$user->id")
        );
    }
}
