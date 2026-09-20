# Deploy ExamNex on Hostinger (test) — step by step

This uploads the app as a normal PHP site (no Docker needed). `vendor/` and the
compiled CSS (`public/css/app.css`) are already included, so you don't need
Composer or Node on the server.

## 1. Create a subdomain
hPanel → **Domains → Subdomains** → create `exam` under `cmsnmietbschool.in`
→ you get `exam.cmsnmietbschool.in` and a folder like
`/domains/cmsnmietbschool.in/public_html/exam` (note this path).

**Set its Document Root to the app's `public` folder** (very important):
`.../exam/public`  — Hostinger lets you set a custom document root when creating
the subdomain, or later in the subdomain settings.

## 2. Create the database
hPanel → **Databases → MySQL Databases** → create a database + user (give all
privileges). Copy the **DB name, user, password, host** (host is usually `localhost`).

## 3. Upload the files
- Upload **`examnex-deploy.zip`** into the subdomain folder (`.../exam`) via
  hPanel **File Manager** and **Extract** it there.
- After extracting you should have `app/ public/ vendor/ routes/ artisan …`
  inside `.../exam`, and the web root pointing to `.../exam/public`.

## 4. Configure .env
- In the subdomain folder, rename **`.env.production` → `.env`**.
- Edit `.env`:
  - `APP_URL=https://exam.cmsnmietbschool.in`
  - `DB_DATABASE / DB_USERNAME / DB_PASSWORD` = your hPanel values
  - (optional) `GEMINI_API_KEY=` your key
  - Set a random `SETUP_KEY=` (e.g. `SETUP_KEY=k7x92mfq31`)

## 5. Set PHP version + extensions
hPanel → **Advanced → PHP Configuration** → PHP **8.2 or 8.3**, and enable:
`pdo_mysql, mbstring, intl, gd, zip, curl, openssl, fileinfo`.

## 6. Run the one-time setup (creates tables + demo login)
Open in browser:
```
https://exam.cmsnmietbschool.in/deploy-setup/YOUR_SETUP_KEY
```
It runs migrations + seeds demo data. When it says "complete":
**delete the `SETUP_KEY` line from `.env`** and reload.

> If your plan has **SSH**, you can skip step 6 and instead run:
> `php artisan migrate --force && php artisan db:seed --force && php artisan optimize`

## 7. Enable SSL (https)
hPanel → **Security → SSL** → install free SSL for the subdomain. Done.

## 8. Login
- Admin: `admin@examnex.test` / `password`  → **change the password** in Settings.
- Public test link appears on each published test's page:
  `https://exam.cmsnmietbschool.in/test/<code>`

## Notes
- Writable folders: make sure `storage/` and `bootstrap/cache/` and
  `public/uploads/` are writable (755/775). File Manager → permissions if needed.
- To update later: re-upload changed files; if you changed views/config run
  `php artisan optimize:clear` (SSH) or just re-upload and it refreshes.
- Docker files (`Dockerfile`, `docker-compose.yml`) are only for local dev — not
  used on Hostinger.
