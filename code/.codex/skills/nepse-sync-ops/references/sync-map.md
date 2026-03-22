# Sync Map

## Key Files

- `README.md`
- `config/nepse.php`
- `app/Console/Commands/NepseSyncCommand.php`
- `routes/console.php`
- `app/Http/Controllers/Nepse/SyncController.php`
- `app/Http/Controllers/Nepse/WatchStockController.php`
- `app/Http/Requests/StartSyncRequest.php`
- `app/Jobs/RunNepseSync.php`
- `app/Jobs/SyncStockPriceHistory.php`
- `app/Services/Nepse/SyncLogTracker.php`
- `app/Services/Nepse/MeroLaganiCatalogImporter.php`
- `app/Services/Nepse/MeroLaganiLivePriceSynchronizer.php`
- `app/Services/Nepse/NepaliPaisaDailyPriceSynchronizer.php`
- `app/Services/Nepse/NepaliPaisaHistorySynchronizer.php`

## Architectural Split

- CLI sync is date-driven and goes through `NepseSyncCommand`.
- Queue-backed historical sync is stock-driven and goes through `RunNepseSync` and `SyncStockPriceHistory`.
- Live sync uses MeroLagani and today’s row updates.
- All paths touch `price_histories`, so drift between flows matters.

## Runtime Checks

- Scheduler: `php artisan schedule:list`
- Daily sync smoke: `php artisan nepse:sync daily --days=1 --symbol=ADBL`
- Full sync smoke: `php artisan nepse:sync full --symbol=ADBL`
- Smart sync smoke: `php artisan nepse:sync smart`
- Queue worker: `php artisan queue:work`
- Logs: `php artisan pail`

## Tables To Inspect

- `sync_logs`: run status, counts, and error summary
- `stocks`: tracked symbol catalog
- `price_histories`: historical and live price rows

Useful questions:

- Is the stock present in `stocks`?
- Is the missing row absent for one symbol or all symbols?
- Did `sync_logs` mark the run as failed, or did the row get skipped as valid?
- Did the active path use NepaliPaisa or MeroLagani?

## Tests

- `tests/Feature/NepseSyncCommandTest.php`
- `tests/Feature/NepseWatchStockTest.php`

These tests already fake upstream HTTP responses and are the first place to extend when sync behavior changes.
