<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

class BootstrapCompatibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'client-compatibility.android.minimum_version' => '1.2.0',
            'client-compatibility.android.latest_version' => '1.10.0',
            'client-compatibility.ios.minimum_version' => '2.0.0',
            'client-compatibility.ios.latest_version' => '2.4.0',
        ]);
    }

    public function test_version_below_minimum_requires_update_without_authentication(): void
    {
        $this->getJson('/api/v1/bootstrap?platform=android&version=1.1.9')
            ->assertOk()
            ->assertJsonPath('data.api.version', 'v1')
            ->assertJsonPath('data.client.platform', 'android')
            ->assertJsonPath('data.client.minimum_version', '1.2.0')
            ->assertJsonPath('data.client.latest_version', '1.10.0')
            ->assertJsonPath('data.client.update_required', true)
            ->assertJsonPath('data.client.update_recommended', true);
    }

    public function test_supported_but_older_version_is_only_recommended_to_update(): void
    {
        $this->getJson('/api/v1/bootstrap?platform=android&version=1.9.0')
            ->assertOk()
            ->assertJsonPath('data.client.update_required', false)
            ->assertJsonPath('data.client.update_recommended', true);
    }

    public function test_semantic_version_comparison_does_not_use_lexical_order(): void
    {
        $this->getJson('/api/v1/bootstrap?platform=android&version=1.10.0')
            ->assertOk()
            ->assertJsonPath('data.client.update_required', false)
            ->assertJsonPath('data.client.update_recommended', false);
    }

    public function test_unknown_platform_and_malformed_version_fail_with_stable_validation_error(): void
    {
        $this->getJson('/api/v1/bootstrap?platform=windows&version=1.0.0')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->getJson('/api/v1/bootstrap?platform=android&version=not-a-version')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }
}
