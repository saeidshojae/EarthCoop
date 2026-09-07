<?php

namespace Tests\Feature\Elections;

use App\Services\Elections\CurrentElectionCenterService;
use Tests\TestCase;

class CurrentElectionCenterServiceContractTest extends TestCase
{
    public function test_current_election_center_service_contract_exists(): void
    {
        $this->assertTrue(
            class_exists(CurrentElectionCenterService::class),
            'CurrentElectionCenterService must exist as the single read-model entry point.'
        );

        $this->assertTrue(
            method_exists(CurrentElectionCenterService::class, 'forUser'),
            'CurrentElectionCenterService must expose forUser(User $user): array.'
        );
    }
}
