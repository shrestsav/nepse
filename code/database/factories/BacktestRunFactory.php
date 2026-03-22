<?php

namespace Database\Factories;

use App\Enums\BacktestRunStatus;
use App\Enums\BacktestStrategy;
use App\Models\BacktestRun;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<BacktestRun>
 */
class BacktestRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = Carbon::instance(fake()->dateTimeBetween('-2 years', '-1 year'))->startOfDay();
        $endDate = $startDate->copy()->addDays(fake()->numberBetween(60, 365));

        return [
            'strategy' => fake()->randomElement(BacktestStrategy::cases()),
            'status' => BacktestRunStatus::Completed,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'started_at' => $startDate,
            'finished_at' => $endDate->copy()->addMinutes(fake()->numberBetween(1, 180)),
            'eligible_stock_count' => fake()->numberBetween(10, 80),
            'total_trades' => fake()->numberBetween(0, 30),
            'wins' => fake()->numberBetween(0, 20),
            'losses' => fake()->numberBetween(0, 20),
            'average_profit_rate' => fake()->randomFloat(4, 0.5, 25),
            'average_loss_rate' => fake()->randomFloat(4, -15, -0.5),
            'success_rate' => fake()->randomFloat(4, 0, 100),
            'error_summary' => null,
        ];
    }
}
