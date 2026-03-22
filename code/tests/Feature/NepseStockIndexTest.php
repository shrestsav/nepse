<?php

use App\Models\Sector;
use App\Models\Stock;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('stocks index shows all matching stocks and supports sector filtering and search', function () {
    $banking = Sector::factory()->create(['name' => 'Commercial Bank']);
    $hydropower = Sector::factory()->create(['name' => 'Hydro Power']);

    $matchingStock = Stock::factory()->for($banking)->create([
        'symbol' => 'NABIL',
        'company_name' => 'Nabil Bank Limited',
    ]);

    Stock::factory()->for($banking)->create([
        'symbol' => 'ADBL',
        'company_name' => 'Agricultural Development Bank Limited',
    ]);

    Stock::factory()->for($hydropower)->create([
        'symbol' => 'UPPER',
        'company_name' => 'Upper Tamakoshi Hydropower Limited',
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard.stocks', [
            'sector' => $banking->id,
            'search' => 'nabil',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('nepse/Stocks')
            ->where('filters.search', 'nabil')
            ->where('filters.sector', $banking->id)
            ->where('summary.totalStocks', 3)
            ->where('summary.filteredStocks', 1)
            ->has('stocks', 1)
            ->where('stocks.0.id', $matchingStock->id)
            ->where('stocks.0.symbol', 'NABIL')
            ->has('sectors', 2),
        );
});

test('stocks index returns all stocks on one page', function () {
    $sector = Sector::factory()->create(['name' => 'Commercial Bank']);

    Stock::factory()->count(30)->for($sector)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard.stocks'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('nepse/Stocks')
            ->where('summary.totalStocks', 30)
            ->where('summary.filteredStocks', 30)
            ->has('stocks', 30),
        );
});

test('sectors page links sector coverage', function () {
    $banking = Sector::factory()->create(['name' => 'Commercial Bank']);
    $hydropower = Sector::factory()->create(['name' => 'Hydro Power']);

    Stock::factory()->count(2)->for($banking)->create();
    Stock::factory()->for($hydropower)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard.sectors'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('nepse/Sectors')
            ->has('sectors', 2)
            ->where('sectors.0.name', 'Commercial Bank')
            ->where('sectors.0.stockCount', 2),
        );
});
