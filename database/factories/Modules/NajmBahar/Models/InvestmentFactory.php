<?php

namespace Database\Factories\Modules\NajmBahar\Models;

use App\Models\User;
use App\Modules\NajmBahar\Models\Investment;
use App\Modules\NajmBahar\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvestmentFactory extends Factory
{
    protected $model = Investment::class;

    public function definition(): array
    {
        $amount = $this->faker->numberBetween(1000000, 50000000); // 10K - 500K گل
        $profitPercentage = $this->faker->numberBetween(10, 25);
        
        return [
            'investor_type' => User::class,
            'investor_id' => User::factory(),
            'project_id' => Project::factory()->approved(),
            'amount' => $amount,
            'agreed_profit_percentage' => $profitPercentage,
            'expected_return' => $amount + ($amount * $profitPercentage / 100),
            'status' => 'pending',
            'transaction_id' => null,
            'notes' => null,
            'invested_at' => null,
            'maturity_date' => null,
            'completed_at' => null,
            'metadata' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'invested_at' => now()->subDays(5),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'invested_at' => now()->subDays(10),
            'metadata' => ['activated_at' => now()->subDays(8)->toDateTimeString()],
        ]);
    }

    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $amount = $attributes['amount'] ?? 10000000;
            $profitPercentage = $attributes['agreed_profit_percentage'] ?? 15;
            
            return [
                'status' => 'completed',
                'invested_at' => now()->subMonths(12),
                'completed_at' => now()->subDays(1),
                'metadata' => ['actual_return' => $amount + ($amount * $profitPercentage / 100)],
            ];
        });
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'notes' => 'لغو شده توسط سرمایه‌گذار',
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'refunded',
            'invested_at' => now()->subDays(3),
            'notes' => 'بازگشت وجه',
        ]);
    }
}
