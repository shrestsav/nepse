<?php

use App\Enums\BacktestRunStatus;
use App\Enums\BacktestStrategy;
use App\Jobs\RunBacktest;
use App\Models\BacktestRun;
use App\Models\BacktestTrade;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests are redirected from the backtesting page', function () {
    $this->get('/dashboard/backtesting')->assertRedirect(route('login'));
});

test('authenticated users can view the backtesting page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard/backtesting')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('nepse/Backtesting')
            ->has('strategies')
            ->has('recentRuns')
        );
});

test('backtests can be queued', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/dashboard/backtesting', [
            'strategy' => BacktestStrategy::RsiAdx->value,
            'start_date' => '2024-01-01',
            'end_date' => '2024-03-01',
        ])
        ->assertRedirect('/dashboard/backtesting');

    $run = BacktestRun::query()->first();

    expect($run)->not()->toBeNull();
    expect($run?->status)->toBe(BacktestRunStatus::Queued);

    Queue::assertPushed(RunBacktest::class, fn (RunBacktest $job) => $job->backtestRunId === $run?->id);
});

test('backtest validation rejects inverted dates', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/dashboard/backtesting')
        ->post('/dashboard/backtesting', [
            'strategy' => BacktestStrategy::RsiAdx->value,
            'start_date' => '2024-03-01',
            'end_date' => '2024-01-01',
        ])
        ->assertRedirect('/dashboard/backtesting')
        ->assertSessionHasErrors(['start_date', 'end_date']);
});

test('backtest run detail page renders persisted trades', function () {
    $user = User::factory()->create();
    $stock = Stock::factory()->create([
        'symbol' => 'TEST',
        'company_name' => 'Test Company',
    ]);
    $run = BacktestRun::factory()->create();
    BacktestTrade::factory()->for($run, 'run')->for($stock)->create([
        'symbol' => $stock->symbol,
    ]);

    $this->actingAs($user)
        ->get("/dashboard/backtesting/{$run->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('nepse/BacktestingShow')
            ->where('run.id', $run->id)
            ->where('trades.data.0.symbol', 'TEST')
        );
});
