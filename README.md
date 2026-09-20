# ExamNex — Single-Software Edition (Laravel + MySQL)

Single-organisation online testing platform. This is a full rewrite of the original
multi-tenant Next.js/PostgreSQL SaaS into **Laravel 13 + MySQL + Blade** — no SaaS,
no tenants, no Row-Level Security. One organisation, one database.

## Stack
- **PHP 8.4 · Laravel 13** (Blade views, Tailwind via CDN — no npm build needed)
- **MySQL 8** (via Docker)
- Session auth with role gating (`ADMIN`, `TEST_CREATOR`, `CANDIDATE`)

## What's included
- **Question bank**: Folder → Subject → Topic hierarchy; 6 question types
  (MCQ single/multi, true/false, fill-blank, numeric, descriptive).
  Global duplicate prevention via a unique index on `questions.normalized_text`
  (checked in-app **and** enforced by the DB).
- **CSV import** with in-file + against-bank dedup and a per-row rejection report.
- **AI generation** (Google Gemini) → review → saved through the **same import
  pipeline** (so dedup + hierarchy always apply). Needs `GEMINI_API_KEY` in `.env`.
- **Tests**: config, add questions, assign candidates (by batch or individually),
  publish, live monitor, optional shared **public self-registration link**
  (`/test/<code>`), certificates.
- **Assessment engine** (ported 1:1): server-authoritative clock (`attempts.deadline_at`),
  frozen question/option order for consistent resume, auto-save, one attempt metered
  on start, objective auto-grading on submit, descriptive routed to manual grading,
  final score computed when all descriptive answers are graded.
- **Reports** with summary stats + **CSV export**, and an audit log.

## Run it — Docker (recommended)

Everything (app + MySQL) runs from one compose file. From this folder:

```bash
docker compose up -d --build
```

Then open **http://localhost:8000**. The app container's entrypoint waits for MySQL,
runs migrations, seeds demo data on the first boot, caches config/routes/views, and
serves via Apache on port 8000. MySQL is exposed on host port **3308**.

```bash
docker logs -f examnex-app        # watch app logs
docker compose down               # stop
docker compose down -v            # stop + wipe the DB volume (fresh start next time)
docker compose up -d --build      # rebuild after code changes
```

Enable AI generation: put your key in `docker-compose.yml` under `app.environment`
(`GEMINI_API_KEY: "..."`) and `docker compose up -d`.

## Run it — without Docker (local dev)

1. Start just MySQL: `docker compose up -d db` (host port 3308)
2. `php artisan migrate --seed`
3. `php artisan serve` → http://127.0.0.1:8000

> Composer lives at the repo root as `composer.phar` — run `php ../composer.phar ...`.
> A custom `php.ini` (enabling openssl/curl/pdo_mysql/mbstring/fileinfo) sits next to `php.exe`.

## Demo logins (all password: `password`)
| Role         | Email                     |
|--------------|---------------------------|
| Admin        | admin@examnex.test        |
| Test Creator | creator@examnex.test      |
| Candidate    | asha.rao@student.test     |

Public demo link: **`/test/DEMO123456`**

## .env keys that matter
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3308
DB_DATABASE=examnex
DB_USERNAME=examnex
DB_PASSWORD=examnex
GEMINI_API_KEY=        # add your Google AI Studio key to enable AI generation
GEMINI_MODEL=gemini-2.0-flash
```

## Key code map
- Migrations: `database/migrations/` · Models: `app/Models/`
- Engine & rules: `app/Services/` (`AttemptService`, `Evaluation`, `QuestionService`,
  `QuestionBank`, `Gemini`) · `app/Support/` (`Normalizer`, `Audit`)
- Controllers: `app/Http/Controllers/` · Routes: `routes/web.php`
- Role gate: `app/Http/Middleware/RoleMiddleware.php`
- Views: `resources/views/` (candidate exam runner: `candidate/run.blade.php`)
