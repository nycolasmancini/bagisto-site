<?php
// Simple deployment script for DigitalOcean
// This runs composer install with all dependencies

echo "Starting Bagisto deployment...\n";

// Install dependencies
echo "Installing dependencies...\n";
exec("composer install --no-dev --optimize-autoloader --no-interaction 2>&1", $output, $return);

if ($return !== 0) {
    echo "Composer install failed:\n";
    echo implode("\n", $output);
    exit(1);
}

// Generate application key if needed
if (empty(getenv('APP_KEY'))) {
    echo "Generating application key...\n";
    exec("php artisan key:generate --force --no-interaction");
}

// Clear and cache configurations
echo "Optimizing application...\n";
exec("php artisan config:clear");
exec("php artisan config:cache");
exec("php artisan route:cache");
exec("php artisan view:cache");

// Run migrations
echo "Running database migrations...\n";
exec("php artisan migrate --force --no-interaction");

// Seed database
echo "Seeding database...\n";
exec("php artisan db:seed --force --no-interaction");

// Create storage link
exec("php artisan storage:link");

echo "Deployment completed successfully!\n";
?>