# Dashboard Map

## Server Files

- `routes/web.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `app/Http/Controllers/Nepse/DashboardController.php`
- `app/Http/Controllers/Nepse/RecommendationController.php`
- `app/Http/Controllers/Nepse/BacktestingController.php`
- `app/Http/Controllers/Nepse/SyncController.php`
- `app/Http/Controllers/Nepse/StockController.php`
- `app/Http/Controllers/Nepse/WatchStockController.php`

## Client Files

- `resources/js/app.ts`
- `resources/js/ssr.ts`
- `resources/js/layouts/AppLayout.vue`
- `resources/js/components/AppSidebar.vue`
- `resources/js/pages/nepse/Dashboard.vue`
- `resources/js/pages/nepse/Recommendations.vue`
- `resources/js/pages/nepse/Backtesting.vue`
- `resources/js/pages/nepse/Sync.vue`
- `resources/js/pages/nepse/Stocks.vue`
- `resources/js/pages/nepse/WatchStock.vue`
- `resources/js/components/nepse/*`
- `resources/js/types/nepse.ts`

## Generated Or Contract-Derived Files

- `resources/js/routes/*`
- `resources/js/actions/*`
- `resources/js/wayfinder/index.ts`

Prefer regenerating Wayfinder output after backend route or controller-signature changes.

## Existing UI Patterns

- Shared props come from `HandleInertiaRequests`.
- Most dashboard filters use Inertia `router.get(...)` or `router.reload(...)`.
- Sync and backtesting pages poll the server instead of maintaining a separate client cache.
- Watch-stock is the main client-driven island and talks to a JSON quote endpoint.

## Validation Commands

- `npm run types:check`
- `npm run lint:check`
- `npm run build`
- `php artisan test`
