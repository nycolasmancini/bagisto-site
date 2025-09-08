#!/bin/sh

# Wait for database to be ready
until nc -z -v -w30 $DATABASE_HOST 25060
do
  echo "Waiting for database connection..."
  sleep 5
done

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Generate key if not set
php artisan key:generate --force

# Run migrations
php artisan migrate --force

# Seed database if needed
php artisan db:seed --force --class=DatabaseSeeder

# Cache configurations for better performance
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Create storage link
php artisan storage:link

# Set proper permissions
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache

# Start supervisord
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf