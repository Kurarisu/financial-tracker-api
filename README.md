# KasKu API

KasKu API is the Laravel backend for the personal finance app. It provides token-authenticated endpoints for accounts, categories, transactions, dashboard summaries, and reports. The frontend is maintained separately in the `financial-tracker` project.

## Requirements

- PHP 8.2 or later
- Composer
- SQLite or MySQL
- Node.js and npm only if you need the Laravel Vite assets

## Local setup

Install PHP dependencies and create your local environment file:

```bash
composer install
cp .env.example .env
php artisan key:generate
```

On Windows PowerShell, use `Copy-Item .env.example .env` instead of `cp`.

The example environment uses SQLite. Create its database file if it does not exist:

```bash
New-Item -ItemType File -Path database/database.sqlite
```

Alternatively, configure `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env` for MySQL. Then run migrations and local development seed data:

```bash
php artisan migrate --seed
```

> **Local development only:** the database seeder creates a demo user (`test@example.com`, password `password`) and sample accounts, tasks, and categories. Do not expose this seeded account or use these credentials in production. For production, configure a secure database and create users through registration instead.

Start the API:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Check that it is running at [http://127.0.0.1:8000/api/ping](http://127.0.0.1:8000/api/ping).

## Connect the frontend

The frontend runs separately, usually at `http://localhost:3001`. In its `.env.local`, set:

```env
API_BASE_URL=http://127.0.0.1:8000/api
```

The Next.js API proxy forwards authenticated requests to this backend. See the frontend README for the full setup.

## Authentication

Register and login endpoints return a Laravel Sanctum personal access token. Send it on protected requests as:

```http
Authorization: Bearer YOUR_TOKEN
Accept: application/json
```

Logout revokes the current token. Registration also creates the user's default cash account.

## API overview

All API endpoints are under `/api`.

| Method | Endpoint | Access | Purpose |
| --- | --- | --- | --- |
| `GET` | `/ping` | Public | Health check |
| `POST` | `/auth/register` | Public | Create an account and token |
| `POST` | `/auth/login` | Public | Authenticate and get a token |
| Resource routes (`GET`, `POST`, `PUT`, `PATCH`, `DELETE`) | `/accounts` | Token | Manage accounts and balances |
| Resource routes (`GET`, `POST`, `PUT`, `PATCH`, `DELETE`) | `/categories` | Token | Manage personal categories |
| `PUT` | `/categories/{category}/budget` | Token | Set an expense category's monthly budget |
| Resource routes (`GET`, `POST`, `PUT`, `PATCH`, `DELETE`) | `/transactions` | Token | Manage income, expenses, and transfers |
| `POST` | `/accounts/{account}/reconcile` | Token | Reconcile an account balance |
| `POST` | `/accounts/{account}/opening-balance` | Token | Set an account's opening balance |
| `GET` | `/dashboard/summary` | Token | Dashboard totals and activity |
| `GET` | `/reports/monthly` | Token | Monthly totals |
| `GET` | `/reports/category-breakdown` | Token | Monthly category totals |
| `GET` | `/reports/cashflow-trend` | Token | Monthly cash-flow series |
| `GET` | `/reports/account-distribution` | Token | Expense totals by account |
| `POST` | `/reports/export-month-directed` | Token | Export a selected month |
| `GET` | `/activity-logs` | Token | View account activity |

The category list can include built-in transaction categories with `?include_defaults=1`; the frontend uses this for the transaction picker. User-created categories remain scoped to their owner.

## Useful commands

```bash
php artisan route:list --path=api
php artisan migrate:status
php artisan db:seed --class=CategorySeeder
```

## Main directories

```text
app/Http/Controllers/Api/   API controllers
app/Models/                 Eloquent models
database/migrations/        Database schema
database/seeders/           Local demo data and built-in categories
routes/api.php              API routes
```
