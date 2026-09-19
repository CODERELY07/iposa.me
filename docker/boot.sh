#!/bin/sh
# Runs once per container start, after the web server is already listening:
# database migrations, the platform operator account, and optional demo data.
# Kept out of the start path so a slow or misconfigured database can never
# stop the port from opening (which hosts read as a failed deploy).
cd /var/www/html

attempt=0
until php artisan migrate --force; do
    attempt=$((attempt + 1))

    if [ "$attempt" -ge 20 ]; then
        echo "DATABASE NOT READY: migrations failed $attempt times. The app will show errors until the database works. Check DB_HOST / DB_DATABASE / DB_USERNAME / DB_PASSWORD." >&2
        exit 1
    fi

    echo "Waiting for the database ($attempt of 20)..."
    sleep 5
done

php artisan app:ensure-super-admin || true

if [ "$SEED_DEMO_DATA" = "true" ]; then
    # Only seeders without Faker (a dev dependency that isn't installed in this image).
    echo "SEED_DEMO_DATA=true: loading the Kape't Burger demo shop (skipped if it already exists)."
    php artisan db:seed --class=Database\\Seeders\\UserSeeder --force || true
    php artisan db:seed --class=Database\\Seeders\\DemoShopSeeder --force || true
fi

echo "Database is ready."
