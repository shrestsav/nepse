<?php

namespace App\Services\Nepse;

use App\Models\Sector;
use App\Models\Stock;

class MeroLaganiCatalogImporter
{
    public function __construct(
        private readonly MeroLaganiMarketClient $marketClient,
    ) {
    }

    public function sync(): int
    {
        $rows = collect($this->marketClient->fetchMarketRows())
            ->filter(fn (array $row): bool => filled($row['symbol']))
            ->unique('symbol')
            ->values();

        $existingStocks = Stock::query()
            ->with('sector')
            ->whereIn('symbol', $rows->pluck('symbol'))
            ->get()
            ->keyBy('symbol');

        foreach ($rows as $row) {
            $existing = $existingStocks->get($row['symbol']);
            $companyName = $row['company_name'] ?: $existing?->company_name;
            $sectorName = $existing?->sector?->name;

            if (blank($companyName) || blank($sectorName)) {
                $profile = $this->marketClient->fetchProfile($row['detail_url']);
                $companyName = $companyName ?: $profile['company_name'];
                $sectorName = $sectorName ?: $profile['sector'];
            }

            $sector = Sector::query()->firstOrCreate([
                'name' => $sectorName ?: 'Unknown',
            ]);

            Stock::query()->updateOrCreate(
                ['symbol' => $row['symbol']],
                [
                    'sector_id' => $sector->id,
                    'company_name' => $companyName ?: $row['symbol'],
                ],
            );
        }

        return $rows->count();
    }
}
