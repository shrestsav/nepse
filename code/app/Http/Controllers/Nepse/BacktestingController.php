<?php

namespace App\Http\Controllers\Nepse;

use App\Enums\BacktestRunStatus;
use App\Enums\BacktestStrategy;
use App\Http\Controllers\Controller;
use App\Http\Requests\StartBacktestRequest;
use App\Jobs\RunBacktest;
use App\Models\BacktestRun;
use App\Models\BacktestTrade;
use App\Services\Nepse\BacktestingService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BacktestingController extends Controller
{
    public function index(BacktestingService $backtestingService): Response
    {
        $latestTradingDate = $backtestingService->latestTradingDate();

        return Inertia::render('nepse/Backtesting', [
            'currentRun' => $this->runData(
                BacktestRun::query()
                    ->whereIn('status', [BacktestRunStatus::Queued->value, BacktestRunStatus::Running->value])
                    ->latest('created_at')
                    ->first(),
            ),
            'latestCompletedRun' => $this->runData(
                BacktestRun::query()
                    ->where('status', BacktestRunStatus::Completed->value)
                    ->latest('created_at')
                    ->first(),
            ),
            'recentRuns' => BacktestRun::query()
                ->latest('created_at')
                ->limit(10)
                ->get()
                ->map(fn (BacktestRun $run): array => $this->runData($run))
                ->all(),
            'strategies' => collect(BacktestStrategy::cases())->map(fn (BacktestStrategy $strategy): array => [
                'value' => $strategy->value,
                'label' => $strategy->label(),
            ])->all(),
            'defaults' => [
                'startDate' => $backtestingService->defaultStartDate($latestTradingDate)?->toDateString(),
                'endDate' => $latestTradingDate?->toDateString(),
            ],
            'dateBounds' => [
                'min' => $backtestingService->earliestTradingDate()?->toDateString(),
                'max' => $latestTradingDate?->toDateString(),
            ],
        ]);
    }

    public function store(StartBacktestRequest $request): RedirectResponse
    {
        $activeRun = BacktestRun::query()
            ->whereIn('status', [BacktestRunStatus::Queued->value, BacktestRunStatus::Running->value])
            ->exists();

        if ($activeRun) {
            return to_route('dashboard.backtesting')->with('error', 'A backtest is already in progress.');
        }

        $strategy = $request->enum('strategy', BacktestStrategy::class);

        $run = BacktestRun::query()->create([
            'strategy' => $strategy,
            'status' => BacktestRunStatus::Queued,
            'start_date' => $request->date('start_date'),
            'end_date' => $request->date('end_date'),
        ]);

        RunBacktest::dispatch($run->id);

        return to_route('dashboard.backtesting')->with('success', "{$strategy->label()} backtest queued.");
    }

    public function show(Request $request, BacktestRun $run): Response
    {
        $trades = $run->trades()
            ->with('stock')
            ->orderByDesc('sell_date')
            ->paginate(25)
            ->through(fn (BacktestTrade $trade): array => [
                'id' => $trade->id,
                'stockId' => $trade->stock_id,
                'symbol' => $trade->symbol,
                'companyName' => $trade->stock?->company_name,
                'buyDate' => $trade->buy_date?->toDateString(),
                'buyPrice' => $trade->buy_price,
                'sellDate' => $trade->sell_date?->toDateString(),
                'sellPrice' => $trade->sell_price,
                'stopLoss' => $trade->stop_loss,
                'exitReason' => $trade->exit_reason,
                'percentageReturn' => $trade->percentage_return,
                'holdingDays' => $trade->holding_days,
                'indicatorSnapshot' => $trade->indicator_snapshot ?? [],
            ])
            ->withQueryString();

        return Inertia::render('nepse/BacktestingShow', [
            'run' => $this->runData($run->fresh()),
            'trades' => $trades,
            'filters' => [
                'page' => (int) $request->integer('page', 1),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function runData(?BacktestRun $run): ?array
    {
        if (! $run instanceof BacktestRun) {
            return null;
        }

        $durationSeconds = null;

        if ($run->started_at && $run->finished_at) {
            $durationSeconds = $run->finished_at->diffInSeconds($run->started_at);
        }

        return [
            'id' => $run->id,
            'strategy' => $run->strategy?->value,
            'strategyLabel' => $run->strategy?->label(),
            'status' => $run->status?->value,
            'statusLabel' => $run->status?->label(),
            'startDate' => $run->start_date?->toDateString(),
            'endDate' => $run->end_date?->toDateString(),
            'startedAt' => $run->started_at?->toIso8601String(),
            'finishedAt' => $run->finished_at?->toIso8601String(),
            'durationSeconds' => $durationSeconds,
            'eligibleStockCount' => $run->eligible_stock_count,
            'totalTrades' => $run->total_trades,
            'wins' => $run->wins,
            'losses' => $run->losses,
            'averageProfitRate' => $run->average_profit_rate,
            'averageLossRate' => $run->average_loss_rate,
            'successRate' => $run->success_rate,
            'errorSummary' => $run->error_summary,
            'isRunning' => $run->isRunning(),
        ];
    }
}
