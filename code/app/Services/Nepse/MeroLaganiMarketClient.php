<?php

namespace App\Services\Nepse;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class MeroLaganiMarketClient
{
    /**
     * @return list<array{
     *     symbol: string,
     *     ltp: string|null,
     *     ltv: string|null,
     *     change_percent: string|null,
     *     high: string|null,
     *     low: string|null,
     *     open: string|null,
     *     quantity: string|null,
     *     company_name: string|null,
     *     detail_url: string|null
     * }>
     */
    public function fetchMarketRows(): array
    {
        $html = $this->requestHtml(config('nepse.merolagani.market_url'));
        $xpath = $this->xpath($html);
        $table = $xpath->query("//table[contains(@class, 'live-trading')]")->item(0);

        if (! $table instanceof DOMElement) {
            return [];
        }

        $headers = [];

        foreach ($xpath->query('.//thead/tr/th', $table) as $index => $header) {
            $headers[$index] = $this->normalizeLabel($header->textContent);
        }

        $rows = [];

        foreach ($xpath->query('.//tbody/tr', $table) as $row) {
            $cells = $xpath->query('./td', $row);

            if ($cells === false || $cells->length === 0) {
                continue;
            }

            $normalized = [];

            foreach ($cells as $index => $cell) {
                $label = $headers[$index] ?? "column_{$index}";
                $normalized[$label] = $this->cleanText($cell->textContent);

                if ($index === 0) {
                    $normalized['symbol'] = $this->cleanText($cell->textContent);

                    $link = $xpath->query('.//a', $cell)->item(0);

                    if ($link instanceof DOMElement && $link->hasAttribute('href')) {
                        $normalized['detail_url'] = $this->absoluteUrl($link->getAttribute('href'));
                    }
                }
            }

            $symbol = (string) ($normalized['symbol'] ?? '');

            if ($symbol === '') {
                continue;
            }

            $rows[] = [
                'symbol' => $symbol,
                'ltp' => $this->firstMatch($normalized, ['ltp', 'last traded price']),
                'ltv' => $this->firstMatch($normalized, ['ltv']),
                'change_percent' => $this->firstMatch($normalized, ['change%', '% change', 'change %', 'change']),
                'high' => $this->firstMatch($normalized, ['high']),
                'low' => $this->firstMatch($normalized, ['low']),
                'open' => $this->firstMatch($normalized, ['open']),
                'quantity' => $this->firstMatch($normalized, ['qty', 'quantity', 'volume']),
                'company_name' => $this->firstMatch($normalized, ['company name']),
                'detail_url' => $normalized['detail_url'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * @return array{company_name: string|null, sector: string|null}
     */
    public function fetchProfile(?string $detailUrl): array
    {
        if (blank($detailUrl)) {
            return [
                'company_name' => null,
                'sector' => null,
            ];
        }

        $html = $this->requestHtml($detailUrl);
        $xpath = $this->xpath($html);

        return [
            'company_name' => $this->findDefinitionValue($xpath, ['company name', 'company']),
            'sector' => $this->findDefinitionValue($xpath, ['sector']),
        ];
    }

    private function requestHtml(string $url): string
    {
        $response = Http::timeout(30)
            ->withHeaders([
                'Accept' => 'text/html,application/xhtml+xml',
                'User-Agent' => 'Mozilla/5.0',
            ])
            ->get($url)
            ->throw();

        $body = $response->body();

        if ($body === '') {
            throw new RuntimeException("Empty HTML response from [{$url}].");
        }

        return $body;
    }

    private function xpath(string $html): DOMXPath
    {
        libxml_use_internal_errors(true);

        $document = new DOMDocument();
        $document->loadHTML($html);

        libxml_clear_errors();

        return new DOMXPath($document);
    }

    private function absoluteUrl(string $href): string
    {
        if (Str::startsWith($href, ['http://', 'https://'])) {
            return $href;
        }

        return rtrim((string) config('nepse.merolagani.base_url'), '/').'/'.ltrim($href, '/');
    }

    /**
     * @param array<string, string> $values
     * @param list<string> $candidates
     */
    private function firstMatch(array $values, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $values) && $values[$candidate] !== '') {
                return $values[$candidate];
            }
        }

        return null;
    }

    private function findDefinitionValue(DOMXPath $xpath, array $labels): ?string
    {
        foreach ($xpath->query('//tr') as $row) {
            $headers = $xpath->query('./th', $row);
            $cells = $xpath->query('./td', $row);

            if ($headers !== false && $headers->length > 0 && $cells !== false && $cells->length > 0) {
                $label = $this->normalizeLabel($headers->item(0)?->textContent ?? '');

                if (in_array($label, $labels, true)) {
                    return $this->cleanText($cells->item(0)?->textContent ?? '');
                }
            }

            if ($cells !== false && $cells->length >= 2) {
                $label = $this->normalizeLabel($cells->item(0)?->textContent ?? '');

                if (in_array($label, $labels, true)) {
                    return $this->cleanText($cells->item(1)?->textContent ?? '');
                }
            }
        }

        return null;
    }

    private function cleanText(?string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', (string) $value));
    }

    private function normalizeLabel(?string $value): string
    {
        return Str::of($this->cleanText($value))
            ->lower()
            ->replace([':', '-', '_'], ' ')
            ->squish()
            ->value();
    }
}
