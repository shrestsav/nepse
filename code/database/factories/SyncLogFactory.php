<?php

namespace Database\Factories;

use App\Enums\SyncMode;
use App\Enums\SyncStatus;
use App\Models\SyncLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SyncLog>
 */
class SyncLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = CarbonImmutable::instance(fake()->dateTimeBetween('-30 days', '-1 hour'));
        $processedStocks = fake()->numberBetween(0, 50);

        return [
            'type' => fake()->randomElement(SyncMode::cases()),
            'status' => SyncStatus::Completed,
            'batch_id' => fake()->uuid(),
            'start' => $start,
            'end' => $start->addMinutes(fake()->numberBetween(1, 30)),
            'total_time' => fake()->numberBetween(60, 1_800),
            'total_synced' => $processedStocks,
            'total_stocks' => $processedStocks,
            'processed_stocks' => $processedStocks,
            'error_summary' => null,
        ];
    }

    public function running(): static
    {
        return $this->state(fn (): array => [
            'status' => SyncStatus::Running,
            'end' => null,
            'total_time' => null,
        ]);
    }
}
