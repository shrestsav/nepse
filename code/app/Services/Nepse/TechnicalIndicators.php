<?php

namespace App\Services\Nepse;

class TechnicalIndicators
{
    /**
     * @param list<float|int> $values
     * @return list<float>
     */
    public function ema(array $values, int $period): array
    {
        $series = array_values(array_map('floatval', $values));

        if (count($series) < $period) {
            return [];
        }

        $seed = array_sum(array_slice($series, 0, $period)) / $period;
        $multiplier = 2 / ($period + 1);
        $ema = [round($seed, 4)];

        for ($index = $period; $index < count($series); $index++) {
            $seed = (($series[$index] - $seed) * $multiplier) + $seed;
            $ema[] = round($seed, 4);
        }

        return $ema;
    }

    /**
     * @param list<float|int> $values
     * @return list<float>
     */
    public function rsi(array $values, int $period): array
    {
        $series = array_values(array_map('floatval', $values));

        if (count($series) <= $period) {
            return [];
        }

        $gains = [];
        $losses = [];

        for ($index = 1; $index < count($series); $index++) {
            $delta = $series[$index] - $series[$index - 1];
            $gains[] = max($delta, 0);
            $losses[] = max(-$delta, 0);
        }

        $averageGain = array_sum(array_slice($gains, 0, $period)) / $period;
        $averageLoss = array_sum(array_slice($losses, 0, $period)) / $period;
        $rsi = [round($this->relativeStrengthIndex($averageGain, $averageLoss), 4)];

        for ($index = $period; $index < count($gains); $index++) {
            $averageGain = (($averageGain * ($period - 1)) + $gains[$index]) / $period;
            $averageLoss = (($averageLoss * ($period - 1)) + $losses[$index]) / $period;
            $rsi[] = round($this->relativeStrengthIndex($averageGain, $averageLoss), 4);
        }

        return $rsi;
    }

    /**
     * @param list<float|int> $values
     * @return list<float>
     */
    public function macd(array $values, int $fastPeriod = 12, int $slowPeriod = 26): array
    {
        $fast = $this->emaAligned($values, $fastPeriod);
        $slow = $this->emaAligned($values, $slowPeriod);
        $macd = [];

        foreach ($values as $index => $value) {
            if ($fast[$index] === null || $slow[$index] === null) {
                continue;
            }

            $macd[] = round($fast[$index] - $slow[$index], 4);
        }

        return $macd;
    }

    /**
     * @param list<float|int> $high
     * @param list<float|int> $low
     * @param list<float|int> $close
     * @return list<float>
     */
    public function adx(array $high, array $low, array $close, int $period): array
    {
        $highSeries = array_values(array_map('floatval', $high));
        $lowSeries = array_values(array_map('floatval', $low));
        $closeSeries = array_values(array_map('floatval', $close));
        $count = min(count($highSeries), count($lowSeries), count($closeSeries));

        if ($count <= $period * 2) {
            return [];
        }

        $trueRanges = [];
        $positiveDm = [];
        $negativeDm = [];

        for ($index = 1; $index < $count; $index++) {
            $upMove = $highSeries[$index] - $highSeries[$index - 1];
            $downMove = $lowSeries[$index - 1] - $lowSeries[$index];

            $trueRanges[] = max(
                $highSeries[$index] - $lowSeries[$index],
                abs($highSeries[$index] - $closeSeries[$index - 1]),
                abs($lowSeries[$index] - $closeSeries[$index - 1]),
            );
            $positiveDm[] = $upMove > $downMove && $upMove > 0 ? $upMove : 0;
            $negativeDm[] = $downMove > $upMove && $downMove > 0 ? $downMove : 0;
        }

        $smoothedTr = array_sum(array_slice($trueRanges, 0, $period));
        $smoothedPositiveDm = array_sum(array_slice($positiveDm, 0, $period));
        $smoothedNegativeDm = array_sum(array_slice($negativeDm, 0, $period));
        $dxValues = [];

        for ($index = $period - 1; $index < count($trueRanges); $index++) {
            if ($index > $period - 1) {
                $smoothedTr = $smoothedTr - ($smoothedTr / $period) + $trueRanges[$index];
                $smoothedPositiveDm = $smoothedPositiveDm - ($smoothedPositiveDm / $period) + $positiveDm[$index];
                $smoothedNegativeDm = $smoothedNegativeDm - ($smoothedNegativeDm / $period) + $negativeDm[$index];
            }

            $positiveDi = $smoothedTr <= 0 ? 0 : 100 * ($smoothedPositiveDm / $smoothedTr);
            $negativeDi = $smoothedTr <= 0 ? 0 : 100 * ($smoothedNegativeDm / $smoothedTr);
            $denominator = $positiveDi + $negativeDi;

            $dxValues[] = $denominator === 0
                ? 0
                : 100 * (abs($positiveDi - $negativeDi) / $denominator);
        }

        if (count($dxValues) < $period) {
            return [];
        }

        $adx = array_sum(array_slice($dxValues, 0, $period)) / $period;
        $result = [round($adx, 4)];

        for ($index = $period; $index < count($dxValues); $index++) {
            $adx = (($adx * ($period - 1)) + $dxValues[$index]) / $period;
            $result[] = round($adx, 4);
        }

        return $result;
    }

    /**
     * @param list<float|int> $values
     * @return list<float|null>
     */
    private function emaAligned(array $values, int $period): array
    {
        $series = array_values(array_map('floatval', $values));
        $aligned = array_fill(0, count($series), null);
        $ema = $this->ema($series, $period);
        $startIndex = $period - 1;

        foreach ($ema as $offset => $value) {
            $aligned[$startIndex + $offset] = $value;
        }

        return $aligned;
    }

    private function relativeStrengthIndex(float $averageGain, float $averageLoss): float
    {
        if ($averageLoss == 0.0) {
            return 100.0;
        }

        if ($averageGain == 0.0) {
            return 0.0;
        }

        $relativeStrength = $averageGain / $averageLoss;

        return 100 - (100 / (1 + $relativeStrength));
    }
}
