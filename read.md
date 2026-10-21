#  kasku-api (Financial Tracker API)
#  API backend for a financial tracker application using Laravel 12. PostgreSQL is the main DB and Docker is the recommended environment. Work locally on either Docker (recommended) or XAMPP.
# Running with Docker:

Ensure Docker is running (Docker Desktop or Docker Engine).
In the project directory, run:
docker compose up -d
Wait for containers to be healthy (docker compose ps).
Generate app key (container/pause-build):
docker compose exec app php artisan key:generate
Run migrations:
docker compose exec app php artisan migrate
Seed (optional):
docker compose exec app php artisan db:seed
API available at http://localhost:8000/ and Postgres at localhost:5434.
Testing endpoints:
/ping (public)
/auth/register, /auth/login (public)
/auth/me, /auth/logout, /activity-logs, /accounts, /categories, /transactions, /dashboard/summary, /reports/* (auth:sanctum + activity.log middleware).
Bypass DB if using XAMPP: In config/database.php, set 'default' => 'pgsql' and provide values from XAMPP (or use sqlite:php:8.2-fpm Dockerfile includes sqlite3 and pdo_sqlite; use docker-compose DATABASE and /var/www/html/.env to change DB driver to sqlite and file path if preferred).
Deploy: Expose port 8000 (web), 5434 (postgres), 8080 (pgAdmin on demand).
---*