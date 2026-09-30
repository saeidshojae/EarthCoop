<?php

namespace Tests\Feature\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Jobs\Communication\EvaluateDelayedCommunicationCondition;
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

class ConditionalRuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CommunicationRule::query()
            ->where('key', 'onboarding.welcome.rule')
            ->update(['is_active' => false]);
    }

    public function test_delayed_registered_condition_dispatches_only_when_true(): void
    {
        Queue::fake();
        $verified = User::factory()->create(['email_verified_at' => now()]);
        $unverified = User::factory()->unverified()->create();
        $rule = $this->conditionalRule('user.email_verified');
        $engine = app(CommunicationRuleEngine::class);

        foreach ([$verified, $unverified] as $user) {
            $engine->handleEvent('registration.completed', [
                'user_id' => $user->id,
                'completed_at' => '2026-09-30T18:30:00+03:30',
                'locale' => 'fa',
            ]);
        }

        Queue::assertPushed(EvaluateDelayedCommunicationCondition::class, 2);

        $verifiedJob = new EvaluateDelayedCommunicationCondition($rule->id, [
            'user_id' => $verified->id,
            'completed_at' => '2026-09-30T18:30:00+03:30',
            'locale' => 'fa',
        ]);
        $verifiedJob->handle(
            app(\App\Services\Communication\CommunicationConditionRegistry::class),
            app(\App\Services\Communication\CommunicationAudienceRegistry::class),
            app(\App\Services\Communication\CommunicationDispatcher::class),
        );

        $unverifiedJob = new EvaluateDelayedCommunicationCondition($rule->id, [
            'user_id' => $unverified->id,
            'completed_at' => '2026-09-30T18:30:00+03:30',
            'locale' => 'fa',
        ]);
        $unverifiedJob->handle(
            app(\App\Services\Communication\CommunicationConditionRegistry::class),
            app(\App\Services\Communication\CommunicationAudienceRegistry::class),
            app(\App\Services\Communication\CommunicationDispatcher::class),
        );

        $this->assertSame(1, $rule->communications()->count());
        $this->assertSame($verified->id, $rule->communications()->firstOrFail()->recipients()->firstOrFail()->user_id);
    }

    public function test_arbitrary_executable_condition_definition_is_rejected(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $rule = $this->conditionalRule('user.email_verified');
        $rule->update([
            'condition_definition' => [
                'key' => 'php.eval',
                'expression' => 'system("id")',
            ],
        ]);

        app(CommunicationRuleEngine::class)->handleEvent('registration.completed', [
            'user_id' => $user->id,
            'completed_at' => now()->toIso8601String(),
            'locale' => 'fa',
        ]);

        $job = new EvaluateDelayedCommunicationCondition($rule->id, [
            'user_id' => $user->id,
            'completed_at' => now()->toIso8601String(),
            'locale' => 'fa',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $job->handle(
            app(\App\Services\Communication\CommunicationConditionRegistry::class),
            app(\App\Services\Communication\CommunicationAudienceRegistry::class),
            app(\App\Services\Communication\CommunicationDispatcher::class),
        );
    }

    private function conditionalRule(string $conditionKey): CommunicationRule
    {
        $sender = CommunicationSenderIdentity::query()->create([
            'key' => 'system',
            'email' => 'system@earthcoop.ir',
            'display_name' => 'EarthCoop',
            'is_active' => true,
        ]);
        $template = CommunicationTemplate::query()->create([
            'key' => 'onboarding.verified-followup',
            'name' => 'Verified follow-up',
            'classification' => CommunicationClassification::Operational,
            'is_active' => true,
        ]);
        app(CommunicationTemplateService::class)->publish(
            $template, 'fa', 'تکمیل ثبت‌نام', '<p>ثبت‌نام شما تکمیل شد.</p>', [], $sender,
        );

        return CommunicationRule::query()->create([
            'key' => 'onboarding.verified-followup.rule',
            'name' => 'Verified follow-up',
            'trigger_type' => 'event',
            'event_key' => 'registration.completed',
            'condition_definition' => ['key' => $conditionKey],
            'audience_definition' => ['key' => 'event.user'],
            'communication_template_id' => $template->id,
            'communication_sender_identity_id' => $sender->id,
            'classification' => CommunicationClassification::Operational,
            'delay_seconds' => 300,
            'is_active' => true,
        ]);
    }
}
