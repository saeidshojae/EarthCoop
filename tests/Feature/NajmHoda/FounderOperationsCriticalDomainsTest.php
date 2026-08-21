<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\FounderOps\FounderManagedDomainRegistry;
use Tests\TestCase;

class FounderOperationsCriticalDomainsTest extends TestCase
{
    public function test_content_secretariat_and_najm_bahar_are_observed(): void
    {
        $domains = app(FounderManagedDomainRegistry::class)->all();

        foreach (['content', 'secretariat', 'najm_bahar'] as $key) {
            $this->assertSame('observed', $domains[$key]['integration_stage']);
            $this->assertContains('observe', $domains[$key]['capabilities']);
            $this->assertContains('summarize', $domains[$key]['capabilities']);
            $this->assertContains('triage', $domains[$key]['capabilities']);
        }
    }

    public function test_high_risk_financial_domain_does_not_claim_safe_action(): void
    {
        $domain = app(FounderManagedDomainRegistry::class)->get('najm_bahar');

        $this->assertSame('high', $domain['risk']);
        $this->assertNotContains('safe_action', $domain['capabilities']);
        $this->assertNotContains('act', $domain['capabilities']);
    }

    public function test_secretariat_proposal_capability_does_not_imply_direct_mutation(): void
    {
        $domain = app(FounderManagedDomainRegistry::class)->get('secretariat');

        $this->assertContains('propose', $domain['capabilities']);
        $this->assertNotContains('safe_action', $domain['capabilities']);
        $this->assertNotContains('act', $domain['capabilities']);
    }
}
