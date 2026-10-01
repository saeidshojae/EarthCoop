<?php

namespace Tests\Feature\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Models\CommunicationTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class UnsubscribeSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_valid_signed_unsubscribe_turns_off_optional_topic_only(): void
    {
        $user = User::factory()->create();
        $this->template('news.optional.unsubscribe', CommunicationClassification::Optional);

        $url = URL::temporarySignedRoute(
            'communications.unsubscribe',
            now()->addHour(),
            ['user' => $user->id, 'topic' => 'news.optional.unsubscribe'],
        );

        $this->get($url)->assertOk()->assertSee('لغو دریافت');

        $this->assertDatabaseHas('communication_preferences', [
            'user_id' => $user->id,
            'topic_key' => 'news.optional.unsubscribe',
            'channel' => 'email',
            'preference' => 'off',
        ]);
    }

    public function test_unsigned_or_tampered_unsubscribe_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->template('news.optional.unsigned', CommunicationClassification::Optional);

        $this->get('/communications/unsubscribe/'.$user->id.'?topic=news.optional.unsigned')
            ->assertForbidden();

        $this->assertDatabaseMissing('communication_preferences', [
            'user_id' => $user->id,
            'topic_key' => 'news.optional.unsigned',
            'preference' => 'off',
        ]);
    }

    public function test_expired_signed_unsubscribe_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->template('news.optional.expired', CommunicationClassification::Optional);

        $url = URL::temporarySignedRoute(
            'communications.unsubscribe',
            now()->subMinute(),
            ['user' => $user->id, 'topic' => 'news.optional.expired'],
        );

        $this->get($url)->assertForbidden();
    }

    public function test_signed_unsubscribe_cannot_disable_required_or_security_topic(): void
    {
        $user = User::factory()->create();
        $this->template('security.required.unsubscribe', CommunicationClassification::Required);

        $url = URL::temporarySignedRoute(
            'communications.unsubscribe',
            now()->addHour(),
            ['user' => $user->id, 'topic' => 'security.required.unsubscribe'],
        );

        $this->get($url)->assertStatus(422);

        $this->assertDatabaseMissing('communication_preferences', [
            'user_id' => $user->id,
            'topic_key' => 'security.required.unsubscribe',
            'preference' => 'off',
        ]);
    }

    private function template(string $key, CommunicationClassification $classification): CommunicationTemplate
    {
        return CommunicationTemplate::query()->create([
            'key' => $key,
            'name' => $key,
            'category' => 'test',
            'classification' => $classification,
            'is_active' => true,
        ]);
    }
}
