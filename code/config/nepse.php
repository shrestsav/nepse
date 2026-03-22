<?php

return [
    'merolagani' => [
        'base_url' => env('NEPSE_MEROLAGANI_BASE_URL', 'https://merolagani.com'),
        'market_url' => env('NEPSE_MEROLAGANI_MARKET_URL', 'https://merolagani.com/LatestMarket.aspx'),
    ],

    'nepalipaisa' => [
        'daily_price_url' => env(
            'NEPSE_NEPALIPAISA_DAILY_PRICE_URL',
            'https://nepalipaisa.com/api/GetTodaySharePrice',
        ),
        'history_url' => env(
            'NEPSE_NEPALIPAISA_HISTORY_URL',
            'https://nepalipaisa.com/Modules/CompanyProfile/webservices/CompanyService.asmx/GetCompanyPriceHistory',
        ),
        'limit' => (int) env('NEPSE_NEPALIPAISA_LIMIT', 3000),
    ],

    'sync' => [
        'daily_lookback_days' => (int) env('NEPSE_DAILY_SYNC_LOOKBACK_DAYS', 7),
        'daily_schedule_time' => env('NEPSE_DAILY_SYNC_SCHEDULE_TIME', '18:15'),
        'full_from_date' => env('NEPSE_FULL_SYNC_FROM_DATE', '2020-01-01'),
    ],

    'recommendations' => [
        'history_start_date' => env('NEPSE_RECOMMENDATION_HISTORY_START_DATE', '2022-01-01'),
        'dashboard_summary_lookback_days' => (int) env('NEPSE_DASHBOARD_SUMMARY_LOOKBACK_DAYS', 30),
        'page_lookback_days' => (int) env('NEPSE_RECOMMENDATION_PAGE_LOOKBACK_DAYS', 120),
        'sparkline_points' => (int) env('NEPSE_SPARKLINE_POINTS', 16),
        'excluded_sector_names' => [
            'Mutual Fund',
            'Promotor Share',
            'Preferred Stock',
            'Corporate Debenture',
        ],
        'profiles' => [
            'rsi_adx' => [
                'minimum_history_points' => (int) env('NEPSE_RSI_ADX_MIN_HISTORY_POINTS', 50),
                'adx_period' => 14,
                'adx_min' => (float) env('NEPSE_RSI_ADX_ADX_MIN', 23),
                'rsi_period' => 14,
                'rsi_min' => (float) env('NEPSE_RSI_ADX_RSI_MIN', 50),
                'rsi_diff_min' => (float) env('NEPSE_RSI_ADX_RSI_DIFF_MIN', 4),
            ],
            'rsi_macd' => [
                'minimum_history_points' => (int) env('NEPSE_RSI_MACD_MIN_HISTORY_POINTS', 50),
                'rsi_period' => 14,
                'rsi_min' => (float) env('NEPSE_RSI_MACD_RSI_MIN', 50),
                'macd_fast_period' => 12,
                'macd_slow_period' => 26,
            ],
            'ma_ema_adx' => [
                'minimum_history_points' => (int) env('NEPSE_MA_EMA_ADX_MIN_HISTORY_POINTS', 20),
                'adx_period' => 5,
                'adx_min' => (float) env('NEPSE_MA_EMA_ADX_ADX_MIN', 40),
                'adx_max' => (float) env('NEPSE_MA_EMA_ADX_ADX_MAX', 60),
                'ema_period' => 10,
            ],
        ],
    ],

    'backtesting' => [
        'warmup_points' => (int) env('NEPSE_BACKTEST_WARMUP_POINTS', 200),
        'minimum_hold_days' => (int) env('NEPSE_BACKTEST_MIN_HOLD_DAYS', 3),
        'default_range_days' => (int) env('NEPSE_BACKTEST_DEFAULT_RANGE_DAYS', 365),
        'profiles' => [
            'rsi_adx' => [
                'adx_period' => 14,
                'adx_min' => (float) env('NEPSE_BACKTEST_RSI_ADX_ADX_MIN', 23),
                'adx_max' => (float) env('NEPSE_BACKTEST_RSI_ADX_ADX_MAX', 30),
                'adx_sell_drop_min' => (float) env('NEPSE_BACKTEST_RSI_ADX_ADX_SELL_DROP_MIN', 2),
                'rsi_period' => 14,
                'rsi_min' => (float) env('NEPSE_BACKTEST_RSI_ADX_RSI_MIN', 50),
                'rsi_max' => (float) env('NEPSE_BACKTEST_RSI_ADX_RSI_MAX', 60),
            ],
            'ma_ema_adx' => [
                'adx_period' => 5,
                'adx_min' => (float) env('NEPSE_BACKTEST_MA_EMA_ADX_ADX_MIN', 40),
                'adx_rise_min' => (float) env('NEPSE_BACKTEST_MA_EMA_ADX_ADX_RISE_MIN', 5),
                'ema_period' => 10,
                'price_above_ema_high_min_ratio' => (float) env('NEPSE_BACKTEST_MA_EMA_ADX_PRICE_ABOVE_EMA_RATIO', 0.05),
            ],
        ],
    ],
];
