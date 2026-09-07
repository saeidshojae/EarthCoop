<?php

namespace Tests\Feature\Reputation;

use Tests\TestCase;

class ParticipationConversionUiIdempotencyContractTest extends TestCase
{
    public function test_najm_bahar_runtime_attaches_one_stable_key_to_conversion_form_submissions(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));
        $runtime = file_get_contents(resource_path('js/najm-bahar-conversion-idempotency.js'));

        $this->assertStringContainsString('najm-bahar-conversion-idempotency.js', $app);
        $this->assertStringContainsString("form#conversionForm", $runtime);
        $this->assertStringContainsString("name = 'idempotency_key'", $runtime);
        $this->assertStringContainsString('crypto.randomUUID', $runtime);
        $this->assertStringContainsString("form.querySelector('input[name=\"idempotency_key\"]')", $runtime);
    }

    public function test_conversion_controller_accepts_the_browser_form_key_as_the_request_identity(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/ReputationConversionController.php'));

        $this->assertStringContainsString("input('idempotency_key'", $source);
        $this->assertStringContainsString("header('Idempotency-Key'", $source);
    }
}
