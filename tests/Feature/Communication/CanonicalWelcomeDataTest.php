<?php

namespace Tests\Feature\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Models\CommunicationRule;
use App\Models\CommunicationSenderIdentity;
use App\Models\CommunicationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CanonicalWelcomeDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_welcome_sender_template_version_and_rule_are_installed_by_migrations(): void
    {
        $sender = CommunicationSenderIdentity::query()
            ->where('key', 'onboarding')
            ->firstOrFail();

        $this->assertSame('welcome@earthcoop.ir', $sender->email);
        $this->assertSame('EarthCoop', $sender->display_name);
        $this->assertTrue($sender->is_active);

        $template = CommunicationTemplate::query()
            ->where('key', 'onboarding.welcome')
            ->firstOrFail();

        $this->assertSame(CommunicationClassification::Operational, $template->classification);
        $this->assertTrue($template->is_active);

        $version = $template->versions()
            ->where('locale', 'fa')
            ->whereNotNull('published_at')
            ->orderByDesc('version')
            ->firstOrFail();

        $this->assertSame(1, (int) $version->version);
        $this->assertSame($sender->id, $version->communication_sender_identity_id);
        $this->assertSame(
            ['display_name', 'email', 'profile_url'],
            array_keys($version->variables_schema ?? [])
        );

        $rule = CommunicationRule::query()
            ->where('key', 'onboarding.welcome.rule')
            ->firstOrFail();

        $this->assertSame('event', $rule->trigger_type);
        $this->assertSame('registration.completed', $rule->event_key);
        $this->assertSame(['key' => 'event.user'], $rule->audience_definition);
        $this->assertSame($template->id, $rule->communication_template_id);
        $this->assertSame($sender->id, $rule->communication_sender_identity_id);
        $this->assertSame(CommunicationClassification::Operational, $rule->classification);
        $this->assertSame(2, (int) $rule->priority);
        $this->assertTrue($rule->is_active);
    }
}
