<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaProactiveRecommendationService;
use Tests\TestCase;

class ProactiveRecommendationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.autonomy.recommendations.enabled' => true,
            'najm-hoda.runtime.autonomy.recommendations.max_items' => 5,
            'najm-hoda.runtime.autonomy.recommendations.min_confidence' => 0.4,
        ]);
    }

    public function test_recommendation_engine_generates_ranked_explainable_items(): void
    {
        $bus = new InMemoryRuntimeEventBus(200);
        $service = new NajmHodaProactiveRecommendationService($bus);

        $recommendations = $service->generate(
            ['stabilize_operations', 'improve_user_experience'],
            [
                'error_rate_percent' => 22.0,
                'unresolved_requests' => 6,
                'modules' => [
                    'chat' => ['messages_recent' => 40],
                    'assignments' => ['open' => 18, 'overdue' => 5],
                ],
            ]
        );

        $this->assertNotEmpty($recommendations);
        $this->assertNotEmpty((string) data_get($recommendations, '0.key', ''));
        $this->assertNotEmpty((string) data_get($recommendations, '0.reason', ''));
        $this->assertGreaterThanOrEqual(0.4, (float) data_get($recommendations, '0.confidence', 0));

        $events = $bus->recent('najm_hoda.autonomy.recommendations.generated', 1);
        $this->assertNotEmpty($events);
        $this->assertSame(count($recommendations), (int) data_get($events[0], 'payload.count', -1));
    }
}
