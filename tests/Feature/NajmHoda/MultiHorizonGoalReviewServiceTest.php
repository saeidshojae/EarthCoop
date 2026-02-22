<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaMultiHorizonGoalEngineService;
use App\Services\NajmHoda\Runtime\NajmHodaMultiHorizonGoalReviewService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MultiHorizonGoalReviewServiceTest extends TestCase
{
    public function test_review_marks_regressing_when_backlog_growth_exceeds_threshold(): void
    {
        Cache::put('najm_hoda:multi_horizon_goals:last_snapshot', [
            'backlog' => [
                ['id' => 't1', 'priority' => 'high'],
            ],
            'horizons' => [
                'daily' => ['a'],
                'weekly' => [],
                'monthly' => [],
            ],
        ], now()->addHour());

        $engine = \Mockery::mock(NajmHodaMultiHorizonGoalEngineService::class);
        $engine->shouldReceive('buildBacklog')->once()->andReturn([
            'scope' => 'global',
            'backlog' => [
                ['id' => 't1', 'priority' => 'high'],
                ['id' => 't2', 'priority' => 'high'],
                ['id' => 't3', 'priority' => 'medium'],
            ],
            'horizons' => [
                'daily' => ['a', 'b'],
                'weekly' => ['w1'],
                'monthly' => ['m1'],
            ],
        ]);

        $service = new NajmHodaMultiHorizonGoalReviewService(new InMemoryRuntimeEventBus(200), $engine);
        $review = $service->review([
            'scope' => 'global',
            'window_hours' => 24,
            'event_limit' => 500,
        ]);

        $this->assertSame('regressing', (string) ($review['status'] ?? ''));
        $this->assertSame(2, (int) data_get($review, 'comparison.backlog_delta'));
        $this->assertSame(1, (int) data_get($review, 'comparison.high_priority_delta'));
    }

    public function test_review_marks_improving_when_high_priority_tasks_drop(): void
    {
        Cache::put('najm_hoda:multi_horizon_goals:last_snapshot', [
            'backlog' => [
                ['id' => 't1', 'priority' => 'high'],
                ['id' => 't2', 'priority' => 'high'],
            ],
            'horizons' => [
                'daily' => ['a', 'b'],
                'weekly' => [],
                'monthly' => [],
            ],
        ], now()->addHour());

        $engine = \Mockery::mock(NajmHodaMultiHorizonGoalEngineService::class);
        $engine->shouldReceive('buildBacklog')->once()->andReturn([
            'scope' => 'global',
            'backlog' => [
                ['id' => 't2', 'priority' => 'medium'],
            ],
            'horizons' => [
                'daily' => ['a'],
                'weekly' => ['w1'],
                'monthly' => ['m1'],
            ],
        ]);

        $service = new NajmHodaMultiHorizonGoalReviewService(new InMemoryRuntimeEventBus(200), $engine);
        $review = $service->review([
            'scope' => 'global',
            'window_hours' => 24,
            'event_limit' => 500,
        ]);

        $this->assertSame('improving', (string) ($review['status'] ?? ''));
        $this->assertSame(-1, (int) data_get($review, 'comparison.backlog_delta'));
        $this->assertSame(-2, (int) data_get($review, 'comparison.high_priority_delta'));
    }
}

