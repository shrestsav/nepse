<?php

namespace Database\Factories;

use App\Models\PriceHistory;
use App\Models\Stock;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<PriceHistory>
 */
class PriceHistoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $closingPrice = fake()->randomFloat(2, 90, 600);
        $maxPrice = $closingPrice + fake()->randomFloat(2, 0, 15);
        $minPrice = max(1, $closingPrice - fake()->randomFloat(2, 0, 15));
        $previousClosing = max(1, $closingPrice - fake()->randomFloat(2, -10, 10));

        return [
            'stock_id' => Stock::factory(),
            'date' => Carbon::instance(fake()->dateTimeBetween('-1 year', 'now'))->toDateString(),
            'closing_price' => $closingPrice,
            'max_price' => $maxPrice,
            'min_price' => $minPrice,
            'change' => round($closingPrice - $previousClosing, 2),
            'change_percent' => round((($closingPrice - $previousClosing) / $previousClosing) * 100, 2),
            'previous_closing' => $previousClosing,
            'traded_shares' => fake()->numberBetween(1_000, 100_000),
            'traded_amount' => fake()->numberBetween(100_000, 5_000_000),
            'total_quantity' => fake()->numberBetween(1_000, 100_000),
            'total_transaction' => fake()->numberBetween(10, 3_000),
            'total_amount' => fake()->randomFloat(2, 100_000, 50_000_000),
            'no_of_transactions' => fake()->numberBetween(10, 5_000),
        ];
    }
}
