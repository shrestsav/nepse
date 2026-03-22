---
name: nepse-sync-ops
description: Operate, debug, and extend this project's NEPSE synchronization flows, queue-backed sync jobs, scheduler wiring, upstream scraper/API clients, and sync-log bookkeeping. Use when running or changing `php artisan nepse:sync`, diagnosing missing price history, debugging MeroLagani or NepaliPaisa ingestion, investigating queue or scheduler behavior, or updating sync-related tests and dashboard sync pages.
---

# Nepse Sync Ops

Work from `/Users/shrestsav/personal/nepse/code`.

## Rebuild Context

Read these files first:

- `README.md`
- `config/nepse.php`
- `app/Console/Commands/NepseSyncCommand.php`
- `routes/console.php`

Read [`references/sync-map.md`](references/sync-map.md) when you need the project-specific file map, commands, and gotchas.

## Pick The Sync Path First

Decide which path is actually involved before changing code:

- CLI date-driven sync: `NepseSyncCommand` plus `MeroLaganiCatalogImporter` and `NepaliPaisaDailyPriceSynchronizer`
- Queue-backed historical sync: `RunNepseSync`, `SyncStockPriceHistory`, and `NepaliPaisaHistorySynchronizer`
- Live quote refresh: `WatchStockController` and `MeroLaganiLivePriceSynchronizer`

Do not treat these as one system. They write to overlapping tables but have different triggers, option rules, and failure modes.

## Investigate In This Order

1. Confirm the trigger surface: CLI, scheduler, dashboard sync page, or watch-stock quote endpoint.
2. Inspect `sync_logs`, `stocks`, and `price_histories` before editing code.
3. Check application logs for upstream failures, scraper breakage, or Wayfinder noise masking the real issue.
4. Trace the exact service or job that writes the row you care about.
5. Update tests around the path you changed, especially HTTP fakes for upstream providers.

Prefer read-only SQL or Laravel Boost database tools when verifying data gaps and duplicate-risk concerns.

## Change Safely

- Keep README behavior, command help text, and validation rules aligned.
- Preserve the `price_histories (stock_id, date)` uniqueness invariant.
- Treat MeroLagani HTML selectors and NepaliPaisa payload shapes as external contracts; if you change parsing, add or tighten failure-path tests.
- If you change sync status/progress behavior, check both controllers and Vue polling screens that read those values.

## Validate

Run the narrowest useful checks first:

- `php artisan test tests/Feature/NepseSyncCommandTest.php`
- `php artisan test tests/Feature/NepseWatchStockTest.php`
- `php artisan schedule:list`

Run broader checks only when the change crosses boundaries:

- `php artisan test`
- `npm run types:check`
