#!/bin/sh
set -e

echo "[entrypoint] waiting for MySQL at ${DB_HOST}:${DB_PORT} ..."
until php -r '$h=getenv("DB_HOST");$p=getenv("DB_PORT");$d=getenv("DB_DATABASE");$u=getenv("DB_USERNAME");$w=getenv("DB_PASSWORD");try{new PDO("mysql:host=$h;port=$p;dbname=$d",$u,$w);exit(0);}catch(\Throwable $e){exit(1);}' 2>/dev/null; do
    sleep 2
done
echo "[entrypoint] MySQL is up."

# Ensure the uploads dir (mounted volume) exists and is writable by Apache.
mkdir -p public/uploads
chown -R www-data:www-data public/uploads
chmod -R 775 public/uploads

# Migrate; seed only on the very first boot (empty schema).
php artisan migrate --force
if [ "$(php artisan tinker --execute='echo \App\Models\User::count();' 2>/dev/null | tail -1)" = "0" ]; then
    echo "[entrypoint] seeding demo data ..."
    php artisan db:seed --force
fi

# Cache config/routes/views for production speed.
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[entrypoint] starting Apache on :8000"
exec apache2-foreground
