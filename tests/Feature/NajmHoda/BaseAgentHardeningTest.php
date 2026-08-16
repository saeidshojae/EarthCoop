<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\BaseAgent;
use Illuminate\Support\Facades\Http;
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

    public function test_page_guidance_prompt_requires_capability_contract_grounding(): void
    {
        $messages = (new TestBaseAgent())->exposeBuildMessages('چطور نظرسنجی بسازم؟', [
            'page_context' => [
                'page_label' => 'گفتگوی گروه',
                'page_kind' => 'group_chat',
                'available_capabilities' => ['create_poll'],
                'capability_contracts' => [[
                    'id' => 'create_poll',
                    'ui' => ['steps' => ['از منوی پیوست، ایجاد نظرسنجی را انتخاب کنید.']],
                ]],
            ],
        ]);

        $systemContext = (string) ($messages[1]['content'] ?? '');

        $this->assertStringContainsString('page_context.capability_contracts', $systemContext);
        $this->assertStringContainsString('MUST come only from those contracts', $systemContext);
        $this->assertStringContainsString('do not invent buttons', $systemContext);
        $this->assertStringContainsString('create_poll', $systemContext);
    }

    public function test_openrouter_403_uses_free_router_fallback_in_testing(): void
    {
        config([
            'najm-hoda.provider.type' => 'openrouter',
            'najm-hoda.provider.api_key' => 'test-key',
            'najm-hoda.provider.model' => 'openai/gpt-oss-20b:free',
            'najm-hoda.provider.retry_count' => 0,
            'najm-hoda.mock_mode' => false,
        ]);

        Http::fakeSequence()
            ->push(['error' => ['message' => 'Primary free model unavailable']], 403)
            ->push(['choices' => [['message' => ['content' => 'fallback-ok']]]], 200);

        $response = (new TestBaseAgent())->ask('hello');

        $this->assertSame('fallback-ok', $response);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request['model'] === 'openai/gpt-oss-20b:free');
        Http::assertSent(fn ($request) => $request['model'] === 'openrouter/free');
    }

    public function test_openrouter_401_does_not_hide_invalid_credentials_with_fallback(): void
    {
        config([
            'najm-hoda.provider.type' => 'openrouter',
            'najm-hoda.provider.api_key' => 'invalid-key',
            'najm-hoda.provider.model' => 'openai/gpt-oss-20b:free',
            'najm-hoda.provider.retry_count' => 0,
            'najm-hoda.mock_mode' => false,
        ]);

        Http::fake([
            '*' => Http::response(['error' => ['message' => 'Unauthorized']], 401),
        ]);

        $response = (new TestBaseAgent())->ask('hello');

        $this->assertStringContainsString('قادر به پاسخگویی نیستم', $response);
        Http::assertSentCount(1);
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

    public function exposeBuildMessages(string $prompt, array $context): array
    {
        return $this->buildMessages($prompt, $context);
    }
}
