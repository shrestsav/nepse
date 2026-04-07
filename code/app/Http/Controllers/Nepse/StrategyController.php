<?php

namespace App\Http\Controllers\Nepse;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShowBrokerNetFlowStrategyRequest;
use App\Services\Nepse\BrokerNetFlowImbalanceStrategyService;
use Inertia\Inertia;
use Inertia\Response;

class StrategyController extends Controller
{
    private const BROKER_NET_FLOW_SLUG = 'broker-net-flow-imbalance-momentum';

    private const DEFAULT_MIN_TURNOVER = 5000000;

    private const DEFAULT_LIMIT = 25;

    private const NET_FLOW_RATIO_THRESHOLD = 0.15;

    private const DOMINANCE_RATIO_THRESHOLD = 0.45;

    public function index(): Response
    {
        return Inertia::render('nepse/Strategies', [
            'strategies' => [[
                'slug' => self::BROKER_NET_FLOW_SLUG,
                'name' => 'Broker Net-Flow Imbalance Momentum',
                'summary' => 'Ranks symbols by concentrated broker net-flow imbalance using aggregated floorsheet trades.',
                'url' => route('dashboard.strategies.show', ['slug' => self::BROKER_NET_FLOW_SLUG], absolute: false),
            ]],
        ]);
    }

    public function show(
        ShowBrokerNetFlowStrategyRequest $request,
        string $slug,
        BrokerNetFlowImbalanceStrategyService $service,
    ): Response {
        abort_unless($slug === self::BROKER_NET_FLOW_SLUG, 404);

        $validated = $request->validated();
        $selectedDate = $service->resolveTradeDate($validated['date'] ?? null);
        $analysis = $selectedDate === null
            ? [
                'summary' => [
                    'symbolsScanned' => 0,
                    'symbolsPassingTurnover' => 0,
                    'buyCandidates' => 0,
                    'sellCandidates' => 0,
                    'neutral' => 0,
                ],
                'rows' => [],
            ]
            : $service->analyze(
                $selectedDate,
                (float) ($validated['minTurnover'] ?? self::DEFAULT_MIN_TURNOVER),
                (int) ($validated['limit'] ?? self::DEFAULT_LIMIT),
                self::NET_FLOW_RATIO_THRESHOLD,
                self::DOMINANCE_RATIO_THRESHOLD,
            );

        return Inertia::render('nepse/StrategyShow', [
            'strategy' => [
                'slug' => self::BROKER_NET_FLOW_SLUG,
                'name' => 'Broker Net-Flow Imbalance Momentum',
                'summary' => 'On-demand ranking of symbols using net broker flows from the selected trade date.',
                'thesis' => 'When net buying pressure is concentrated among a handful of brokers, short-term continuation probability can increase. Conversely, concentrated net selling can signal downside continuation.',
                'howComputed' => [
                    'Use aggregated floorsheet rows for a single trade date.',
                    'Compute symbol turnover as SUM(total_amount).',
                    'For each broker, compute buy_amount, sell_amount, and net_amount.',
                    'Rank brokers by ABS(net_amount), then sum signed top-5 net values.',
                    'Derive netFlowRatio = top5Net / turnover and dominanceRatio = max ABS(net) / turnover.',
                ],
                'entryRules' => [
                    'BUY when netFlowRatio >= 0.15, changePercent > 0, dominanceRatio <= 0.45.',
                    'SELL when netFlowRatio <= -0.15, changePercent < 0, dominanceRatio <= 0.45.',
                    'Otherwise classify as NEUTRAL.',
                ],
                'riskControls' => [
                    'Exclude low-liquidity symbols by minimum turnover filter.',
                    'Use dominance ratio cap to avoid one-broker distortion.',
                    'Compare signals only within the same trade date snapshot.',
                ],
                'backtestPlan' => [
                    'Start with single-day signal classification, then replay next-day/next-3-day returns.',
                    'Track hit rate, average return, and turnover-filter sensitivity.',
                    'Stress-test threshold combinations for netFlowRatio and dominanceRatio.',
                ],
            ],
            'selectedDate' => $selectedDate?->toDateString(),
            'dateBounds' => [
                'min' => $service->earliestTradeDate()?->toDateString(),
                'max' => $service->latestTradeDate()?->toDateString(),
            ],
            'filters' => [
                'date' => $validated['date'] ?? null,
                'minTurnover' => (float) ($validated['minTurnover'] ?? self::DEFAULT_MIN_TURNOVER),
                'limit' => (int) ($validated['limit'] ?? self::DEFAULT_LIMIT),
            ],
            'summary' => $analysis['summary'],
            'rows' => $analysis['rows'],
        ]);
    }
}
