<?php

namespace App\Enums;

enum BacktestStrategy: string
{
    case RsiAdx = 'rsi_adx';
    case MaEmaAdx = 'ma_ema_adx';

    public function label(): string
    {
        return match ($this) {
            self::RsiAdx => 'RSI + ADX',
            self::MaEmaAdx => 'MA / EMA + ADX',
        };
    }
}
