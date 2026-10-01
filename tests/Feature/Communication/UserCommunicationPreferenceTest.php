<?php

namespace Tests\Feature\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Models\CommunicationPreference;
use App\Models\CommunicationTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserCommunicationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_preferences_page_shows_required_as_non_disableable_and_operational_optional_as_configurable(): void
    {
        $user = User::factory()->create();
        $this->seedTemplates();

        $this->actingAs($user)
            ->get('/profile/communication-preferences')
            ->assertOk()
            ->assertSee('security.required.test')
            ->assertSee('غیرقابل غیرفعال‌سازی')
            ->assertSee('reports.operational.test')
            ->assertSee('news.optional.test');
    }

    public function test_user_can_turn_operational_and_optional_email_topics_on_or_off(): void
    {
        $user = User::factory()->create();
        $this->seedTemplates();

        $this->actingAs($user)
            ->put('/profile/communication-preferences', [
                'preferences' => [
                    'reports.operational.test' => 'off',
                    'news.optional.test' => 'on',
                ],
            ])
            ->assertRedirect('/profile/communication-preferences');

        $this->assertDatabaseHas('communication_preferences', [
            'user_id' => $user->id,
            'topic_key' => 'reports.operational.test',
            'channel' => 'email',
            'preference' => 'off',
        ]);
        $this->assertDatabaseHas('communication_preferences', [
            'user_id' => $user->id,
            'topic_key' => 'news.optional.test',
            'channel' => 'email',
            'preference' => 'on',
        ]);
    }

    public function test_required_topic_cannot_be_disabled_by_profile_update(): void
    {
        $user = User::factory()->create();
        $this->seedTemplates();

        $this->actingAs($user)
            ->putJson('/profile/communication-preferences', [
                'preferences' => ['security.required.test' => 'off'],
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('communication_preferences', [
            'user_id' => $user->id,
            'topic_key' => 'security.required.test',
            'preference' => 'off',
        ]);
    }

    public function test_preference_update_is_scoped_to_authenticated_user_only(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->seedTemplates();

        CommunicationPreference::query()->create([
            'user_id' => $other->id,
            'topic_key' => 'news.optional.test',
            'channel' => 'email',
            'preference' => 'on',
        ]);

        $this->actingAs($user)
            ->put('/profile/communication-preferences', [
                'user_id' => $other->id,
                'preferences' => ['news.optional.test' => 'off'],
            ])
            ->assertRedirect('/profile/communication-preferences');

        $this->assertDatabaseHas('communication_preferences', [
            'user_id' => $other->id,
            'topic_key' => 'news.optional.test',
            'preference' => 'on',
        ]);
        $this->assertDatabaseHas('communication_preferences', [
            'user_id' => $user->id,
            'topic_key' => 'news.optional.test',
            'preference' => 'off',
        ]);
    }

    private function seedTemplates(): void
    {
        foreach ([
            ['security.required.test', CommunicationClassification::Required],
            ['reports.operational.test', CommunicationClassification::Operational],
            ['news.optional.test', CommunicationClassification::Optional],
        ] as [$key, $classification]) {
            CommunicationTemplate::query()->create([
                'key' => $key,
                'name' => $key,
                'category' => 'test',
                'classification' => $classification,
                'is_active' => true,
            ]);
        }
    }
}
