<?php

namespace Tests\Feature\NajmHoda;

use App\Models\User;
use App\Services\NajmHoda\Context\NajmHodaPageContextResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NajmHodaSecretariatPageContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretariat_record_route_is_described_without_exposing_browser_resource_payload(): void
    {
        $actor = User::factory()->create();

        $context = app(NajmHodaPageContextResolver::class)->resolve($actor, [
            'page' => [
                'route_name' => 'secretariat.records.show',
                'module' => 'secretariat',
                'resource_type' => 'secretariat_record',
                'resource_id' => 999999,
                'title' => 'FORGED SECRET TITLE',
                'body' => 'FORGED SECRET BODY',
            ],
        ]);

        $this->assertSame('سند دبیرخانه', $context['page_label']);
        $this->assertSame('secretariat_record', $context['page_kind']);
        $this->assertContains('view_secretariat_record', $context['available_capabilities']);
        $this->assertNull($context['resource_id']);
        $this->assertNull($context['resource']);
        $this->assertStringNotContainsString('FORGED', json_encode($context, JSON_UNESCAPED_UNICODE));
    }

    public function test_secretariat_directory_and_case_routes_have_specific_page_identity(): void
    {
        $resolver = app(NajmHodaPageContextResolver::class);

        $directory = $resolver->resolve(null, [
            'page' => ['route_name' => 'secretariat.directory', 'module' => 'secretariat'],
        ]);
        $case = $resolver->resolve(null, [
            'page' => ['route_name' => 'secretariat.cases.show', 'module' => 'secretariat'],
        ]);

        $this->assertSame('secretariat_directory', $directory['page_kind']);
        $this->assertSame('فهرست دبیرخانه‌ها', $directory['page_label']);
        $this->assertSame('secretariat_case', $case['page_kind']);
        $this->assertSame('پرونده دبیرخانه', $case['page_label']);
    }
}
