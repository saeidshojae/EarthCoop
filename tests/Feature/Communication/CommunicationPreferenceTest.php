<?php

namespace Tests\Feature\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Models\CommunicationPreference;
use App\Models\User;
use App\Services\Communication\CommunicationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_required_communication_cannot_be_suppressed_by_user_preference(): void
    {
        $user = User::factory()->create();
        CommunicationPreference::query()->create([
            'user_id' => $user->id,
            'topic_key' => 'security.password_changed',
            'channel' => 'email',
            'preference' => 'off',
        ]);

        $decision = app(CommunicationPreferenceService::class)->allows(
            $user,
            'security.password_changed',
            CommunicationClassification::Required,
        );

        $this->assertTrue($decision->allowed);
        $this->assertSame('required_bypass', $decision->reason);
    }

    public function test_operational_is_default_on_but_explicit_off_is_respected(): void
    {
        $user = User::factory()->create();
        $service = app(CommunicationPreferenceService::class);

        $defaultDecision = $service->allows(
            $user,
            'onboarding.welcome',
            CommunicationClassification::Operational,
        );
        $this->assertTrue($defaultDecision->allowed);
        $this->assertSame('operational_default_on', $defaultDecision->reason);

        CommunicationPreference::query()->create([
            'user_id' => $user->id,
            'topic_key' => 'onboarding.welcome',
            'channel' => 'email',
            'preference' => 'off',
        ]);

        $offDecision = $service->allows(
            $user->fresh(),
            'onboarding.welcome',
            CommunicationClassification::Operational,
        );
        $this->assertFalse($offDecision->allowed);
        $this->assertSame('user_off', $offDecision->reason);
    }

    public function test_optional_is_default_off_and_explicit_on_is_respected(): void
    {
        $user = User::factory()->create();
        $service = app(CommunicationPreferenceService::class);

        $defaultDecision = $service->allows(
            $user,
            'news.platform_updates',
            CommunicationClassification::Optional,
        );
        $this->assertFalse($defaultDecision->allowed);
        $this->assertSame('optional_default_off', $defaultDecision->reason);

        CommunicationPreference::query()->create([
            'user_id' => $user->id,
            'topic_key' => 'news.platform_updates',
            'channel' => 'email',
            'preference' => 'on',
        ]);

        $onDecision = $service->allows(
            $user->fresh(),
            'news.platform_updates',
            CommunicationClassification::Optional,
        );
        $this->assertTrue($onDecision->allowed);
        $this->assertSame('user_on', $onDecision->reason);
    }
}
