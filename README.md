# Nepse Stocks Analyzer

Laravel 13 application for syncing, analyzing, and backtesting NEPSE market data.

This repository currently uses the app inside `code/`. Docker is not used.

## Stack

- PHP 8.4
- Laravel 13
- MySQL
- Inertia + Vue 3 + Tailwind CSS
- Database queue

## Repository layout

- `code/`: active Laravel 13 application
- `.gitignore`: repo-level Git ignores

## Features

- Stock catalog sync from MeroLagani
- Daily and historical price sync using NepaliPaisa
- Dashboard summary
- Recommendation screens
- Backtesting screens
- Stock list with per-symbol history view

## Local setup

```bash
cd /Users/shrestsav/personal/nepse/code

composer install
npm install

cp .env.example .env
php artisan key:generate
php artisan migrate
```

Update `code/.env` for your local MySQL credentials if needed.

Important defaults:

- `DB_CONNECTION=mysql`
- `QUEUE_CONNECTION=database`
- `SESSION_DRIVER=file`

## Running the app

From `code/`:

```bash
composer run dev
```

That starts:

- Laravel dev server
- queue listener
- log tail
- Vite dev server

Open:

- [http://localhost:8000](http://localhost:8000)

If you use Herd or another local domain, point your browser there instead.

## Queue and scheduler

Some features only work if the queue worker and scheduler are running.

Queue-backed features:

- dashboard sync page
- live sync
- backtesting

If you are not using `composer run dev`, run these separately:

```bash
php artisan queue:work
php artisan schedule:run
```

For a real scheduled environment, Laravel still needs the normal cron entry:

```cron
* * * * * cd /Users/shrestsav/personal/nepse/code && php artisan schedule:run >> /dev/null 2>&1
```

## CLI sync command

Use the unified NEPSE sync command from `code/`:

```bash
php artisan nepse:sync daily
php artisan nepse:sync full
php artisan nepse:sync smart
```

### Modes

`daily`

- scheduled sync mode
- refreshes the most recent market window
- default lookback is 7 days
- supports:

```bash
php artisan nepse:sync daily --days=7
php artisan nepse:sync daily --from=2026-03-01 --to=2026-03-07
php artisan nepse:sync daily --symbol=ADBL
```

`full`

- full historical backfill
- always starts from `NEPSE_FULL_SYNC_FROM_DATE`
- currently defaults to `2021-01-01`
- supports:

```bash
php artisan nepse:sync full
php artisan nepse:sync full --symbol=ADBL
```

`smart`

- fully automatic incremental sync
- calculates the earliest missing date across tracked stocks
- does not accept `--from`, `--to`, `--days`, or `--symbol`
- usage:

```bash
php artisan nepse:sync smart
```

### Scheduled daily sync

The scheduler runs:

```bash
php artisan nepse:sync daily
```

By default it runs daily at `18:15` in the app timezone.

Config keys:

- `NEPSE_DAILY_SYNC_LOOKBACK_DAYS`
- `NEPSE_DAILY_SYNC_SCHEDULE_TIME`
- `NEPSE_FULL_SYNC_FROM_DATE`

## Web routes

After login:

- `/dashboard`
- `/dashboard/recommendations`
- `/dashboard/backtesting`
- `/dashboard/sync`
- `/dashboard/stocks`

Root behavior:

- guest -> `/login`
- authenticated user -> `/dashboard`

## Sync notes

- The dashboard sync page only exposes `smart` and `live`
- Full sync is command-only
- Live sync writes current-day rows using MeroLagani market data
- Stock list "Latest date" is the latest stored trading date for that symbol

## Tests and checks

From `code/`:

```bash
php artisan test
npm run types:check
npm run lint:check
npm run build
```

## Branding assets

Branding files are stored in:

- `code/public/app/`

These are used for:

- app logo
- favicon assets
- browser title branding
