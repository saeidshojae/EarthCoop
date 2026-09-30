<?php

namespace Tests\Feature\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Models\CommunicationSenderIdentity;
use App\Models\CommunicationTemplate;
use App\Services\Communication\CommunicationTemplateRenderer;
use App\Services\Communication\CommunicationTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class TemplateVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishing_creates_immutable_incrementing_versions_per_locale(): void
    {
        $sender = CommunicationSenderIdentity::query()->create([
            'key' => 'system',
            'email' => 'system@earthcoop.ir',
            'display_name' => 'EarthCoop',
        ]);
        $template = CommunicationTemplate::query()->create([
            'key' => 'onboarding.welcome',
            'name' => 'Welcome',
            'classification' => CommunicationClassification::Operational,
            'is_active' => true,
        ]);

        $service = app(CommunicationTemplateService::class);
        $v1 = $service->publish($template, 'fa', 'سلام {{first_name}}', '<p>خوش آمدید {{first_name}}</p>', [
            'first_name' => ['required' => true],
        ], $sender);
        $v2 = $service->publish($template, 'fa', 'سلام دوباره {{first_name}}', '<p>راهنمای شروع {{first_name}}</p>', [
            'first_name' => ['required' => true],
        ], $sender);
        $enV1 = $service->publish($template, 'en', 'Hello {{first_name}}', '<p>Welcome {{first_name}}</p>', [
            'first_name' => ['required' => true],
        ], $sender);

        $this->assertSame(1, $v1->version);
        $this->assertSame(2, $v2->version);
        $this->assertSame(1, $enV1->version);
        $this->assertNotNull($v1->published_at);

        $v1->subject = 'Mutated after publication';
        $this->expectException(LogicException::class);
        $v1->save();
    }

    public function test_renderer_requires_declared_required_variables_and_rejects_unknown_context(): void
    {
        $sender = CommunicationSenderIdentity::query()->create([
            'key' => 'system',
            'email' => 'system@earthcoop.ir',
            'display_name' => 'EarthCoop',
        ]);
        $template = CommunicationTemplate::query()->create([
            'key' => 'onboarding.welcome',
            'name' => 'Welcome',
            'classification' => CommunicationClassification::Operational,
            'is_active' => true,
        ]);
        $version = app(CommunicationTemplateService::class)->publish(
            $template,
            'fa',
            'سلام {{first_name}}',
            '<p>{{first_name}}، {{dashboard_url}}</p>',
            [
                'first_name' => ['required' => true],
                'dashboard_url' => ['required' => true],
            ],
            $sender,
        );

        $renderer = app(CommunicationTemplateRenderer::class);
        $rendered = $renderer->render($version, [
            'first_name' => 'سعید',
            'dashboard_url' => 'https://earthcoop.ir/home',
        ]);

        $this->assertSame('سلام سعید', $rendered['subject']);
        $this->assertStringContainsString('https://earthcoop.ir/home', $rendered['body']);
        $this->assertStringNotContainsString('{{', $rendered['subject'] . $rendered['body']);

        try {
            $renderer->render($version, ['first_name' => 'سعید']);
            $this->fail('Missing required variable must fail rendering.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('dashboard_url', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $renderer->render($version, [
            'first_name' => 'سعید',
            'dashboard_url' => 'https://earthcoop.ir/home',
            'raw_sql' => 'select * from users',
        ]);
    }

    public function test_publish_rejects_placeholders_not_declared_in_variable_schema(): void
    {
        $sender = CommunicationSenderIdentity::query()->create([
            'key' => 'system',
            'email' => 'system@earthcoop.ir',
            'display_name' => 'EarthCoop',
        ]);
        $template = CommunicationTemplate::query()->create([
            'key' => 'onboarding.welcome',
            'name' => 'Welcome',
            'classification' => CommunicationClassification::Operational,
        ]);

        $this->expectException(InvalidArgumentException::class);
        app(CommunicationTemplateService::class)->publish(
            $template,
            'fa',
            'سلام {{first_name}}',
            '<p>{{undeclared}}</p>',
            ['first_name' => ['required' => true]],
            $sender,
        );
    }
}
