<?php

namespace Tests\Feature\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Models\CommunicationRule;
use App\Models\CommunicationSenderIdentity;
use App\Models\CommunicationTemplate;
use App\Models\User;
use App\Services\Communication\CommunicationRuleEngine;
use App\Services\Communication\CommunicationTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Tests\TestCase;

class CommunicationRuleMatchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_event_dispatches_active_event_user_rule_once_with_deterministic_dedupe(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $rule = $this->eventRule('welcome.rule', true, ['key' => 'event.user']);
        $payload = [
            'user_id' => $user->id,
            'completed_at' => '2026-09-30T18:00:00+03:30',
            'locale' => 'fa',
        ];

        $engine = app(CommunicationRuleEngine::class);
        $engine->handleEvent('registration.completed', $payload);
        $engine->handleEvent('registration.completed', $payload);

        $this->assertSame(1, $rule->communications()->count());
        $communication = $rule->communications()->firstOrFail();
        $this->assertSame($user->id, $communication->recipients()->firstOrFail()->user_id);
        $this->assertStringContainsString('rule:'.$rule->id, (string) $communication->deduplication_key);
    }

    public function test_disabled_rule_does_not_dispatch(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $rule = $this->eventRule('disabled.welcome', false, ['key' => 'event.user']);

        app(CommunicationRuleEngine::class)->handleEvent('registration.completed', [
            'user_id' => $user->id,
            'completed_at' => now()->toIso8601String(),
            'locale' => 'fa',
        ]);

        $this->assertSame(0, $rule->communications()->count());
    }

    public function test_unknown_event_audience_or_condition_fails_closed(): void
    {
        $engine = app(CommunicationRuleEngine::class);

        try {
            $engine->handleEvent('php.eval', []);
            $this->fail('Unknown event must be rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Unregistered communication event', $e->getMessage());
        }

        $user = User::factory()->create();
        $this->eventRule('bad.audience', true, ['key' => 'raw.sql', 'sql' => 'select * from users']);

        $this->expectException(InvalidArgumentException::class);
        $engine->handleEvent('registration.completed', [
            'user_id' => $user->id,
            'completed_at' => now()->toIso8601String(),
            'locale' => 'fa',
        ]);
    }

    private function eventRule(string $key, bool $active, array $audience): CommunicationRule
    {
        $sender = CommunicationSenderIdentity::query()->firstOrCreate(
            ['key' => 'system'],
            ['email' => 'system@earthcoop.ir', 'display_name' => 'EarthCoop', 'is_active' => true],
        );
        $template = CommunicationTemplate::query()->firstOrCreate(
            ['key' => 'onboarding.welcome'],
            ['name' => 'Welcome', 'classification' => CommunicationClassification::Operational, 'is_active' => true],
        );
        if ($template->versions()->count() === 0) {
            app(CommunicationTemplateService::class)->publish(
                $template, 'fa', 'خوش آمدید', '<p>به ارث‌کوپ خوش آمدید.</p>', [], $sender,
            );
        }

        return CommunicationRule::query()->create([
            'key' => $key,
            'name' => $key,
            'trigger_type' => 'event',
            'event_key' => 'registration.completed',
            'audience_definition' => $audience,
            'communication_template_id' => $template->id,
            'communication_sender_identity_id' => $sender->id,
            'classification' => CommunicationClassification::Operational,
            'priority' => 2,
            'is_active' => $active,
        ]);
    }
}
