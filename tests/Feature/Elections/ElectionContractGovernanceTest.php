<?php

namespace Tests\Feature\Elections;

use App\Models\ElectionResponsibilityContractVersion;
use App\Models\User;
use App\Services\Elections\ElectionResponsibilityContractVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ElectionContractGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_cannot_publish_with_any_required_e0_clause_missing(): void
    {
        $actor = User::factory()->create();
        $clauses = array_fill_keys(ElectionResponsibilityContractVersion::REQUIRED_CLAUSES, 'متن معتبر');
        unset($clauses['conflicts_confidentiality_and_vote_integrity']);
        $this->expectException(InvalidArgumentException::class);
        app(ElectionResponsibilityContractVersionService::class)->publish('manager', $clauses, $actor, 'test');
    }

    public function test_complete_contract_publishes_as_immutable_e0_compliant_version(): void
    {
        $actor = User::factory()->create();
        $clauses = array_fill_keys(ElectionResponsibilityContractVersion::REQUIRED_CLAUSES, 'متن کامل و روشن این بخش');
        $contract = app(ElectionResponsibilityContractVersionService::class)->publish('inspector', $clauses, $actor, 'initial E0 contract');
        $this->assertTrue($contract->e0_compliant);
        $this->assertTrue($contract->hasCompleteE0Manifest());
        $this->assertNotNull($contract->published_at);
        $this->assertStringContainsString('۶. شکایت', $contract->body);
    }
}
