<?php

namespace Database\Factories;

use App\Models\Sector;
use App\Models\Stock;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Stock>
 */
class StockFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sector_id' => Sector::factory(),
            'symbol' => Str::upper(fake()->unique()->lexify('???')),
            'company_name' => fake()->company(),
        ];
    }
}
