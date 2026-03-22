---
name: nepse-quant-analysis
description: Analyze and modify this project's recommendation, technical-indicator, backtesting, and price-history logic. Use when changing RSI, ADX, EMA, or MACD rules, tuning recommendation or backtest thresholds, explaining why a symbol was or was not selected, validating historical signal behavior against stored market data, or updating the related services, controllers, config, and tests.
---

# Nepse Quant Analysis

Work from `/Users/shrestsav/personal/nepse/code`.

## Rebuild Context

Read these files first:

- `config/nepse.php`
- `app/Services/Nepse/RecommendationService.php`
- `app/Services/Nepse/BacktestingService.php`
- `app/Services/Nepse/TechnicalIndicators.php`

Read [`references/quant-map.md`](references/quant-map.md) when you need the project-specific model, config, and test map.

## Analyze Before Editing

Separate the question into one of these buckets:

- recommendation selection logic
- indicator implementation details
- backtest entry or exit rules
- data-quality or historical-sample issues

Always identify the effective config inputs before changing service code. Many behaviors are driven by `config/nepse.php`, not by hardcoded rules.

## Verify Against Data

When explaining why a stock was selected or skipped:

1. Identify the as-of date.
2. Check excluded-sector rules.
3. Verify minimum history or warmup requirements.
4. Inspect the actual `price_histories` rows used by the service.
5. Only then reason about RSI, ADX, EMA, or MACD thresholds.

Prefer database queries or Laravel Boost database tools over mental reconstruction when the question depends on stored history.

## Change Safely

- Keep recommendation profiles, backtesting profiles, and tests aligned.
- Preserve the current service boundaries: controllers shape payloads, services do the quant logic.
- If you adjust thresholds or data windows, update unit tests so the intended signal path is explicit.
- If you add a new strategy or profile, update enums, config, services, controllers, and UI surfaces together.

## Validate

Run the most specific checks first:

- `php artisan test tests/Unit/RecommendationServiceTest.php`
- `php artisan test tests/Unit/NepseBacktestingServiceTest.php`
- `php artisan test tests/Feature/NepseRecommendationTest.php`
- `php artisan test tests/Feature/NepseBacktestingTest.php`

Run broader checks when you changed controller payloads or shared types:

- `php artisan test`
- `npm run types:check`
