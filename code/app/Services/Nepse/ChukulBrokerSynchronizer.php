<?php

namespace App\Services\Nepse;

use App\Models\Broker;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ChukulBrokerSynchronizer
{
    public function sync(): int
    {
        $response = Http::timeout(30)
            ->retry(3, fn (int $attempt): int => $attempt * 1000, throw: false)
            ->acceptJson()
            ->withHeaders($this->requestHeaders())
            ->get((string) config('nepse.chukul.broker_url'))
            ->throw();

        $rows = $response->json();

        if (! is_array($rows)) {
            throw new RuntimeException('Unexpected broker response from Chukul.');
        }

        $normalizedRows = collect($rows)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(function (array $row): array {
                $brokerNo = trim((string) ($row['broker_no'] ?? ''));
                $brokerName = trim((string) ($row['broker_name'] ?? ''));

                if ($brokerNo === '' || $brokerName === '') {
                    throw new RuntimeException('Broker response contained an invalid row.');
                }

                return [
                    'broker_no' => $brokerNo,
                    'broker_name' => $brokerName,
                ];
            })
            ->unique('broker_no')
            ->values();

        foreach ($normalizedRows as $row) {
            Broker::query()->updateOrCreate(
                ['broker_no' => $row['broker_no']],
                ['broker_name' => $row['broker_name']],
            );
        }

        return $normalizedRows->count();
    }

    /**
     * @return array<string, string>
     */
    private function requestHeaders(): array
    {
        return [
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Priority' => 'u=1, i',
            'Referer' => 'https://chukul.com/floorsheet',
            'Sec-CH-UA' => '"Chromium";v="146", "Not-A.Brand";v="24", "Google Chrome";v="146"',
            'Sec-CH-UA-Mobile' => '?0',
            'Sec-CH-UA-Platform' => '"macOS"',
            'Sec-Fetch-Dest' => 'empty',
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Site' => 'same-origin',
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',
        ];
    }
}
