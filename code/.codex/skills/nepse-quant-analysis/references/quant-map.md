# Quant Map

## Core Services

- `app/Services/Nepse/RecommendationService.php`
- `app/Services/Nepse/BacktestingService.php`
- `app/Services/Nepse/TechnicalIndicators.php`

## Config Areas

- `nepse.recommendations.history_start_date`
- `nepse.recommendations.dashboard_summary_lookback_days`
- `nepse.recommendations.page_lookback_days`
- `nepse.recommendations.sparkline_points`
- `nepse.recommendations.excluded_sector_names`
- `nepse.recommendations.profiles.*`
- `nepse.backtesting.warmup_points`
- `nepse.backtesting.minimum_hold_days`
- `nepse.backtesting.default_range_days`
- `nepse.backtesting.profiles.*`

## Main Models And Tables

- `Sector`
- `Stock`
- `PriceHistory`
- `BacktestRun`
- `BacktestTrade`

`price_histories` is the source of truth for both recommendations and backtests.

## Practical Questions To Answer

- Which sector exclusions applied?
- What was the exact as-of date?
- How many history points were available after filtering?
- Which profile was in use?
- Did the indicator arrays line up with the service’s lookback assumptions?
- Did the backtest exit due to stop loss, rule exit, or forced close?

## Tests

- `tests/Unit/RecommendationServiceTest.php`
- `tests/Unit/NepseBacktestingServiceTest.php`
- `tests/Feature/NepseRecommendationTest.php`
- `tests/Feature/NepseBacktestingTest.php`

Use these tests as executable documentation for signal thresholds and trade lifecycle behavior.
