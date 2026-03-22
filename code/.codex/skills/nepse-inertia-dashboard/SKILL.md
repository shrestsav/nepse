---
name: nepse-inertia-dashboard
description: Build and maintain this project's Laravel plus Inertia plus Vue dashboard flows. Use when adding or changing dashboard routes, controllers, shared props, Wayfinder-generated route contracts, Vue pages or layouts under `resources/js`, polling behavior, or any feature that spans Laravel controllers and Inertia page props in this NEPSE app.
---

# Nepse Inertia Dashboard

Work from `/Users/shrestsav/personal/nepse/code`.

## Rebuild Context

Read these files first:

- `routes/web.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `resources/js/app.ts`
- `resources/js/pages/nepse/*`

Read [`references/dashboard-map.md`](references/dashboard-map.md) when you need the project-specific server/client map and validation commands.

## Follow The Server To Client Path

When adding or changing a dashboard feature, trace it in this order:

1. route definition
2. controller or request class
3. shared props or page props
4. TypeScript types
5. page and component rendering
6. generated route helpers if backend contracts changed

Do not start in Vue and guess the backend payload shape.

## Treat Generated Files As Outputs

Treat these directories as generated or contract-derived:

- `resources/js/routes`
- `resources/js/actions`
- `resources/js/wayfinder`

If route names or controller signatures change, regenerate definitions instead of hand-maintaining drift-prone copies.

## Preserve Project Patterns

- Keep server-driven filtering and reload behavior on dashboard screens unless there is a strong reason to introduce a new client data layer.
- Keep shared auth, flash, and sidebar props aligned with `HandleInertiaRequests`.
- Preserve the existing layout shell and polling patterns unless the change is explicitly about UX or performance.
- Update `resources/js/types/nepse.ts` when prop contracts change.

## Validate

Run the smallest useful set first:

- `npm run types:check`
- `npm run lint:check`
- `php artisan test tests/Feature/DashboardTest.php`

Add broader checks when the change crosses routes, controllers, and pages:

- `php artisan test`
- `npm run build`
