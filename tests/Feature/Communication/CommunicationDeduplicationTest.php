<?php

namespace Tests\Feature\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Models\Communication;
use App\Models\CommunicationPreference;
use App\Models\CommunicationRecipient;
use App\Models\CommunicationSenderIdentity;
use App\Models\CommunicationTemplate;
use App\Models\User;
use App\Services\Communication\CommunicationDispatcher;
use App\Services\Communication\CommunicationTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CommunicationDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_equivalent_dispatches_converge_on_one_logical_communication(): void
    {
        Queue::fake();
        [$user] = $this->seedTemplateAndUser(CommunicationClassification::Operational);
        $dispatcher = app(CommunicationDispatcher::class);
        $options = [
            'deduplication_key' => 'onboarding.welcome:user_'.$user->id.':registration_completion_1',
            'locale' => 'fa',
        ];

        $first = $dispatcher->dispatch(
            'onboarding.welcome',
            ['type' => 'registration', 'id' => 'completion-1'],
            [$user],
            ['first_name' => $user->first_name],
            $options,
        );
        $second = $dispatcher->dispatch(
            'onboarding.welcome',
            ['type' => 'registration', 'id' => 'completion-1'],
            [$user],
            ['first_name' => $user->first_name],
            $options,
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Communication::query()->count());
        $this->assertSame(1, CommunicationRecipient::query()->count());
        $this->assertSame('queued', CommunicationRecipient::query()->firstOrFail()->status->value);
    }

    public function test_dispatch_records_suppressed_and_invalid_recipients_for_audit(): void
    {
        Queue::fake();
        [$allowed] = $this->seedTemplateAndUser(CommunicationClassification::Operational);
        $suppressed = User::factory()->create();
        $missingEmail = User::factory()->create(['email' => null]);
        CommunicationPreference::query()->create([
            'user_id' => $suppressed->id,
            'topic_key' => 'onboarding.welcome',
            'channel' => 'email',
            'preference' => 'off',
        ]);

        $communication = app(CommunicationDispatcher::class)->dispatch(
            'onboarding.welcome',
            ['type' => 'manual-test', 'id' => 'audit-case'],
            [$allowed, $suppressed, $missingEmail],
            ['first_name' => 'کاربر'],
            [
                'deduplication_key' => 'onboarding.welcome:audit-case',
                'locale' => 'fa',
            ],
        );

        $statuses = $communication->recipients()->orderBy('id')->pluck('status')->map(
            fn ($status) => $status instanceof \BackedEnum ? $status->value : (string) $status
        )->all();

        $this->assertSame(['queued', 'suppressed', 'invalid'], $statuses);
        $this->assertSame(3, $communication->recipients()->count());
        $this->assertSame('user_off', $communication->recipients()->where('user_id', $suppressed->id)->firstOrFail()->preference_decision['reason']);
    }

    /** @return array{0:User,1:CommunicationTemplate} */
    private function seedTemplateAndUser(CommunicationClassification $classification): array
    {
        $sender = CommunicationSenderIdentity::query()->create([
            'key' => 'system',
            'email' => 'system@earthcoop.ir',
            'display_name' => 'EarthCoop',
            'is_active' => true,
            'is_default' => true,
        ]);
        $template = CommunicationTemplate::query()->create([
            'key' => 'onboarding.welcome',
            'name' => 'Welcome',
            'classification' => $classification,
            'is_active' => true,
        ]);
        app(CommunicationTemplateService::class)->publish(
            $template,
            'fa',
            'سلام {{first_name}}',
            '<p>خوش آمدید {{first_name}}</p>',
            ['first_name' => ['required' => true]],
            $sender,
        );

        return [User::factory()->create(), $template];
    }
}
