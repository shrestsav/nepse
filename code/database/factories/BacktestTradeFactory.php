<?php

namespace Database\Factories;

use App\Models\BacktestTrade;
use App\Models\BacktestRun;
use App\Models\Stock;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<BacktestTrade>
 */
class BacktestTradeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $buyDate = Carbon::instance(fake()->dateTimeBetween('-1 year', '-2 months'))->startOfDay();
        $sellDate = $buyDate->copy()->addDays(fake()->numberBetween(3, 45));
        $buyPrice = fake()->randomFloat(2, 80, 750);
        $sellPrice = max(1, $buyPrice + fake()->randomFloat(2, -120, 160));

        return [
            'backtest_run_id' => BacktestRun::factory(),
            'stock_id' => Stock::factory(),
            'symbol' => fake()->lexify('???'),
            'buy_date' => $buyDate->toDateString(),
            'buy_price' => $buyPrice,
            'sell_date' => $sellDate->toDateString(),
            'sell_price' => $sellPrice,
            'stop_loss' => max(1, $buyPrice - fake()->randomFloat(2, 1, 50)),
            'indicator_snapshot' => [
                'ADX_today' => fake()->randomFloat(4, 20, 60),
                'ADX_yesterday' => fake()->randomFloat(4, 18, 55),
            ],
            'exit_reason' => fake()->randomElement(['signal', 'stop_loss', 'forced_close']),
            'percentage_return' => round((($sellPrice - $buyPrice) / $buyPrice) * 100, 4),
            'holding_days' => $buyDate->diffInDays($sellDate),
        ];
    }
}
