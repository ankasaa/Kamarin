# Kamarin

Booking management and analytics dashboard for a single guesthouse, plus a public booking
engine. Laravel 13 + React (Inertia) + Tailwind + PostgreSQL.

During development, PHP, Composer, and Node run locally on Windows. Only PostgreSQL runs in
Docker (see `compose.yaml`).

## Requirements

- PHP 8.3+ with the `pdo_pgsql` extension enabled (verified: 8.3.35, Laragon)
- Composer 2 (verified: 2.10.3)
- Node.js 24 + npm (verified: 24.21.0 / 11.19.0)
- Docker Desktop with Docker Compose (verified: 29.8.1 / 5.5.1)

## First-time setup

```powershell
docker compose up -d      # PostgreSQL 18 with two databases: kamarin (dev), kamarin_test (tests)
composer install
composer run setup        # copies .env if missing, generates APP_KEY, migrates, npm install + build
```

## Running the app

```powershell
composer run dev          # php artisan dev: app server (http://127.0.0.1:8000), queue worker, Vite
```

Stop with `Ctrl+C`. The scheduler is not part of `composer run dev`; run it in a separate
terminal when needed:

```powershell
php artisan schedule:work
```

## Important commands

| Command | Purpose |
| --- | --- |
| `docker compose up -d` / `docker compose stop` | Start / stop PostgreSQL |
| `docker compose down -v` | Stop PostgreSQL and delete both databases and their data |
| `composer run dev` | App server + queue worker + Vite dev server |
| `composer run test` | Config clear, Pint check, PHPStan, then `php artisan test` (Pest) |
| `php artisan test` | Run the test suite only, against PostgreSQL `kamarin_test` |
| `composer run lint` | Format code with Laravel Pint |

## Versions (verified 2026-10-10)

| Component | Version |
| --- | --- |
| PHP | 8.3.35 (local) |
| Laravel | 13.35.0 (`laravel/framework`) |
| PostgreSQL | 18.6 (Docker image `postgres:18-alpine`) |
| Node / npm | 24.21.0 / 11.19.0 (local) |
| Composer | 2.10.3 |
| Pest | 4.7.8 |

## Environment notes

- Timezone is set through `APP_TIMEZONE` in `.env` (currently `Asia/Makassar`).
- Tests run against the separate PostgreSQL database `kamarin_test` (`phpunit.xml`), never
  against SQLite. The SQLite PHP extensions are no longer needed. PostgreSQL must be running:
  `docker compose up -d`.
- A guard in `tests/TestCase.php` aborts every test before any migration or data deletion
  unless `DB_DATABASE` ends with `_test`, so the suite can never wipe development data.
- Use the `freezeNow()` helper (defined in `tests/Pest.php`) to pin the clock in tests;
  the clock resets automatically between tests.
- `docker/postgresql/init-test-db.sql` creates `kamarin_test` on the first container start only.
