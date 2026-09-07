<?php

namespace Tests\Feature\NajmHoda;

use App\Http\Controllers\Admin\FounderMinistryChatController;
use Tests\TestCase;

class FounderMinistryPhaseFourReadinessTest extends TestCase
{
    public function test_phase_four_readiness_exposes_backend_agency_without_execution_bypass(): void
    {
        $payload = app(FounderMinistryChatController::class)->readiness()->getData(true);

        $this->assertTrue($payload['success']);
        $this->assertSame('read_only_decision_support', $payload['mode']);
        $this->assertTrue($payload['executive_agency']);
        $this->assertSame('server_side_founder_ops_evidence', $payload['agency_authority_source']);
        $this->assertFalse($payload['typed_execution_inference']);
        $this->assertFalse($payload['approval_bypass']);
        $this->assertSame('existing_founder_ops_approval_authority_lifecycle', $payload['execution_boundary']);
    }
}
