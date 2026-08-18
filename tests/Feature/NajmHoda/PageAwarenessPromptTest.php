<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\BaseAgent;
use Tests\TestCase;

class PageAwarenessPromptTest extends TestCase
{
    public function test_agent_prompt_requires_current_page_answers_to_use_validated_context(): void
    {
        $agent = new class extends BaseAgent {
            protected string $role = 'steward';

            public function getSystemPrompt(): string
            {
                return 'Test steward prompt';
            }

            public function exposeBuildMessages(string $prompt, array $context): array
            {
                return $this->buildMessages($prompt, $context);
            }
        };

        $messages = $agent->exposeBuildMessages('من الان در چه صفحه‌ای هستم؟', [
            'page_context' => [
                'route_name' => 'home',
                'module' => 'home',
                'page_label' => 'خانه ارثکوپ',
                'page_kind' => 'home',
                'available_capabilities' => ['navigation'],
            ],
        ]);

        $contextMessage = collect($messages)
            ->first(fn (array $message) => $message['role'] === 'system' && str_contains($message['content'], 'PAGE-AWARENESS RULE'));

        $this->assertNotNull($contextMessage);
        $this->assertStringContainsString('page_context.page_label', $contextMessage['content']);
        $this->assertStringContainsString('Never guess the current page', $contextMessage['content']);
        $this->assertStringContainsString('خانه ارثکوپ', $contextMessage['content']);
    }
}
