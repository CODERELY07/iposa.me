#!/bin/sh
# Container start. Only quick, local work happens here: the web server must start
# listening fast, because hosts like Render fail a deploy if no port opens in time.
# Migrations and seeding run right after, in the background (docker/boot.sh).
set -e
cd /var/www/html

export PORT="${PORT:-10000}"

# Render's "generateValue" gives a raw base64 key; Laravel expects the "base64:" prefix.
if [ -n "$APP_KEY" ] && [ "${APP_KEY#base64:}" = "$APP_KEY" ]; then
    export APP_KEY="base64:$APP_KEY"
fi

if [ -z "$APP_KEY" ]; then
    echo "WARNING: APP_KEY is not set. Pages will fail until you set it (php artisan key:generate --show)." >&2
fi

# Render provides the public URL of the service; use it unless APP_URL was set explicitly.
if [ -z "$APP_URL" ] && [ -n "$RENDER_EXTERNAL_URL" ]; then
    export APP_URL="$RENDER_EXTERNAL_URL"
fi

envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/http.d/default.conf

# Cache config/routes/views with the real environment (values are baked in here).
# These are local and quick; a failure must not stop the server from starting.
php artisan optimize:clear > /dev/null 2>&1 || true
php artisan config:cache || echo "WARNING: config:cache failed; running without cached config." >&2
php artisan route:cache || true
php artisan view:cache || true
php artisan event:cache || true

chown -R www-data:www-data storage bootstrap/cache

echo "Starting web server on port ${PORT}."

exec supervisord -c /etc/supervisord.conf
