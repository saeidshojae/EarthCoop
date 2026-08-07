<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\BaseAgent;
use RuntimeException;
use Tests\TestCase;

class BaseAgentHardeningTest extends TestCase
{
    public function test_missing_api_key_does_not_silently_return_mock_when_mock_mode_is_off(): void
    {
        config([
            'najm-hoda.provider.api_key' => null,
            'najm-hoda.mock_mode' => false,
        ]);

        $response = (new TestBaseAgent())->ask('hello');

        $this->assertStringContainsString('قادر به پاسخگویی نیستم', $response);
        $this->assertStringNotContainsString('حالت آزمایشی', $response);
    }

    public function test_mock_response_requires_explicit_mock_mode(): void
    {
        config([
            'najm-hoda.provider.api_key' => null,
            'najm-hoda.mock_mode' => true,
        ]);

        $response = (new TestBaseAgent())->ask('hello');

        $this->assertStringContainsString('حالت آزمایشی', $response);
    }

    public function test_cost_uses_cost_tracking_configuration_path(): void
    {
        config([
            'najm-hoda.provider.model' => 'test-model',
            'najm-hoda.cost_tracking.enabled' => true,
            'najm-hoda.cost_tracking.cost_per_1k_tokens.test-model' => 0.02,
        ]);

        $agent = new TestBaseAgent();

        $this->assertEqualsWithDelta(0.01, $agent->exposeCalculateCost(500), 0.000001);
    }

    public function test_cost_is_zero_when_cost_tracking_is_disabled(): void
    {
        config([
            'najm-hoda.provider.model' => 'test-model',
            'najm-hoda.cost_tracking.enabled' => false,
            'najm-hoda.cost_tracking.cost_per_1k_tokens.test-model' => 10,
        ]);

        $agent = new TestBaseAgent();

        $this->assertSame(0.0, $agent->exposeCalculateCost(1000));
    }

    public function test_provider_payload_requires_non_empty_content(): void
    {
        $this->expectException(RuntimeException::class);

        (new TestBaseAgent())->exposeExtractResponseContent([
            'choices' => [
                ['message' => ['content' => '']],
            ],
        ]);
    }
}

class TestBaseAgent extends BaseAgent
{
    protected string $role = 'engineer';

    public function getSystemPrompt(): string
    {
        return 'test system prompt';
    }

    public function exposeCalculateCost(int $tokens): float
    {
        return $this->calculateCost($tokens);
    }

    public function exposeExtractResponseContent(array $result): string
    {
        return $this->extractResponseContent($result, 'TestProvider');
    }
}
