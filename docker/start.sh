#!/bin/sh
# Container start: prepare Laravel for this environment, migrate, then hand over to supervisord.
set -e
cd /var/www/html

export PORT="${PORT:-10000}"

# Render's "generateValue" gives a raw base64 key; Laravel expects the "base64:" prefix.
if [ -n "$APP_KEY" ] && [ "${APP_KEY#base64:}" = "$APP_KEY" ]; then
    export APP_KEY="base64:$APP_KEY"
fi

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set. Generate one with: php artisan key:generate --show" >&2
    exit 1
fi

# Render provides the public URL of the service; use it unless APP_URL was set explicitly.
if [ -z "$APP_URL" ] && [ -n "$RENDER_EXTERNAL_URL" ]; then
    export APP_URL="$RENDER_EXTERNAL_URL"
fi

envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/http.d/default.conf

# Cache config/routes/views with the real environment (values are baked in here).
php artisan optimize:clear > /dev/null
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Wait for the database (fresh Render databases can take a moment), then migrate.
attempt=0
until php artisan migrate --force; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 10 ]; then
        echo "Database still unreachable after $attempt attempts." >&2
        exit 1
    fi
    echo "Waiting for the database ($attempt)..."
    sleep 3
done

php artisan app:ensure-super-admin

if [ "$SEED_DEMO_DATA" = "true" ]; then
    # Only seeders without Faker (a dev dependency that isn't installed in this image).
    echo "SEED_DEMO_DATA=true: loading the Kape't Burger demo shop (skipped if it already exists)."
    php artisan db:seed --class=Database\\Seeders\\UserSeeder --force
    php artisan db:seed --class=Database\\Seeders\\DemoShopSeeder --force
fi

chown -R www-data:www-data storage bootstrap/cache

exec supervisord -c /etc/supervisord.conf
