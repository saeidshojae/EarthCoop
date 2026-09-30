<?php

namespace Tests\Feature\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Models\CommunicationSenderIdentity;
use App\Models\CommunicationTemplate;
use App\Services\Communication\CommunicationTemplateRenderer;
use App\Services\Communication\CommunicationTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class TemplateRendererStructuredContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_declared_structured_metadata_may_remain_in_context_when_not_interpolated(): void
    {
        $version = $this->publish(
            '<p>{{display_name}}</p>',
            [
                'display_name' => ['type' => 'string', 'required' => true],
                'managed_group_ids' => ['type' => 'array', 'required' => true],
            ],
        );

        $rendered = app(CommunicationTemplateRenderer::class)->render($version, [
            'display_name' => 'مدیر آزمایشی',
            'managed_group_ids' => [10, 20],
        ]);

        $this->assertStringContainsString('مدیر آزمایشی', $rendered['body']);
        $this->assertStringNotContainsString('10', $rendered['body']);
    }

    public function test_structured_value_still_fails_if_template_attempts_to_interpolate_it(): void
    {
        $version = $this->publish(
            '<p>{{managed_group_ids}}</p>',
            ['managed_group_ids' => ['type' => 'array', 'required' => true]],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('managed_group_ids');

        app(CommunicationTemplateRenderer::class)->render($version, [
            'managed_group_ids' => [10, 20],
        ]);
    }

    private function publish(string $body, array $schema)
    {
        $sender = CommunicationSenderIdentity::query()->create([
            'key' => 'structured-context-test',
            'email' => 'structured-context-test@earthcoop.ir',
            'display_name' => 'EarthCoop Test',
            'is_active' => true,
        ]);
        $template = CommunicationTemplate::query()->create([
            'key' => 'test.structured-context',
            'name' => 'Structured context test',
            'classification' => CommunicationClassification::Operational,
            'is_active' => true,
        ]);

        return app(CommunicationTemplateService::class)->publish(
            $template,
            'fa',
            'Structured context test',
            $body,
            $schema,
            $sender,
        );
    }
}
