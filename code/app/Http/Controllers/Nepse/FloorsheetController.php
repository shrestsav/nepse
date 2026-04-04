<?php

namespace App\Http\Controllers\Nepse;

use App\Http\Controllers\Controller;
use App\Models\Broker;
use App\Models\Floorsheet;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FloorsheetController extends Controller
{
    public function index(Request $request): Response
    {
        $minTradeDate = Floorsheet::query()->min('trade_date');
        $latestTradeDate = Floorsheet::query()->max('trade_date');
        $defaultTradeDate = $latestTradeDate !== null
            ? CarbonImmutable::parse($latestTradeDate)->toDateString()
            : CarbonImmutable::today((string) config('app.timezone'))->toDateString();

        $filters = $request->validate([
            'date' => ['nullable', 'date'],
            'symbol' => ['nullable', 'string', 'max:20'],
            'buyer' => ['nullable', 'string', 'max:20'],
            'seller' => ['nullable', 'string', 'max:20'],
            'quantityRange' => ['nullable', 'string', 'in:all,0-10,10-100,100-1k'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $tradeDate = CarbonImmutable::parse((string) ($filters['date'] ?? $defaultTradeDate))->toDateString();
        $symbol = strtoupper(trim((string) ($filters['symbol'] ?? '')));
        $buyer = trim((string) ($filters['buyer'] ?? ''));
        $seller = trim((string) ($filters['seller'] ?? ''));
        $quantityRange = (string) ($filters['quantityRange'] ?? 'all');

        $query = Floorsheet::query()
            ->with(['buyerBroker:id,broker_no,broker_name', 'sellerBroker:id,broker_no,broker_name'])
            ->whereDate('trade_date', $tradeDate)
            ->when(
                $symbol !== '',
                fn ($builder) => $builder->where('symbol', 'like', "%{$symbol}%"),
            )
            ->when(
                $buyer !== '',
                fn ($builder) => $builder->where('buyer_broker_no', $buyer),
            )
            ->when(
                $seller !== '',
                fn ($builder) => $builder->where('seller_broker_no', $seller),
            );

        $this->applyQuantityRange($query, $quantityRange);

        $paginatedFloorsheets = $query
            ->orderByDesc('transaction')
            ->paginate(50)
            ->withQueryString();

        $matchingRows = $paginatedFloorsheets->total();

        return Inertia::render('nepse/Floorsheet', [
            'floorsheets' => $paginatedFloorsheets->through(fn (Floorsheet $floorsheet): array => [
                'id' => $floorsheet->id,
                'transaction' => $floorsheet->transaction,
                'symbol' => $floorsheet->symbol,
                'buyerBrokerNo' => $floorsheet->buyer_broker_no !== '' ? $floorsheet->buyer_broker_no : null,
                'buyerBrokerName' => $floorsheet->buyerBroker?->broker_name,
                'sellerBrokerNo' => $floorsheet->seller_broker_no !== '' ? $floorsheet->seller_broker_no : null,
                'sellerBrokerName' => $floorsheet->sellerBroker?->broker_name,
                'quantity' => $floorsheet->quantity,
                'rate' => $floorsheet->rate,
                'amount' => $floorsheet->amount,
            ]),
            'brokers' => Broker::query()
                ->get()
                ->sortBy([
                    fn (Broker $broker): int => (int) $broker->broker_no,
                    fn (Broker $broker): string => $broker->broker_no,
                ])
                ->values()
                ->map(fn (Broker $broker): array => [
                    'brokerNo' => $broker->broker_no,
                    'brokerName' => $broker->broker_name,
                ])
                ->all(),
            'filters' => [
                'date' => $tradeDate,
                'symbol' => $symbol !== '' ? $symbol : null,
                'buyer' => $buyer !== '' ? $buyer : null,
                'seller' => $seller !== '' ? $seller : null,
                'quantityRange' => $quantityRange,
            ],
            'dateBounds' => [
                'min' => $minTradeDate !== null
                    ? CarbonImmutable::parse((string) $minTradeDate)->toDateString()
                    : null,
                'max' => $latestTradeDate !== null
                    ? CarbonImmutable::parse((string) $latestTradeDate)->toDateString()
                    : null,
            ],
            'summary' => [
                'matchingRows' => $matchingRows,
                'shownRows' => $paginatedFloorsheets->count(),
            ],
        ]);
    }

    private function applyQuantityRange($query, string $quantityRange): void
    {
        match ($quantityRange) {
            '0-10' => $query->whereBetween('quantity', [0, 10]),
            '10-100' => $query->where('quantity', '>', 10)->where('quantity', '<=', 100),
            '100-1k' => $query->where('quantity', '>', 100)->where('quantity', '<=', 1000),
            default => null,
        };
    }
}
