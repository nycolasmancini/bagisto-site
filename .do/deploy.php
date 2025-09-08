<?php
// Production-optimized deployment script for DigitalOcean
// Handles errors gracefully and uses better composer settings

echo "Starting Bagisto deployment...\n";

function executeCommand($command, $description, $timeout = 300) {
    echo "=== $description ===\n";
    $output = [];
    $return = 0;
    
    // Set timeout for long-running commands
    $timeoutCommand = "timeout $timeout $command 2>&1";
    exec($timeoutCommand, $output, $return);
    
    if ($return !== 0) {
        echo "ERROR: $description failed with exit code $return\n";
        echo "Output:\n" . implode("\n", array_slice($output, -10)) . "\n"; // Show last 10 lines
        echo "Command: $command\n";
        
        // For non-critical failures, continue with warning
        if (strpos($description, 'Seed') !== false || strpos($description, 'storage:link') !== false) {
            echo "WARNING: Non-critical step failed, continuing...\n";
            return [];
        }
        
        exit(1);
    }
    
    echo "SUCCESS: $description completed\n";
    if (!empty($output)) {
        echo "Output:\n" . implode("\n", array_slice($output, -3)) . "\n"; // Show last 3 lines
    }
    return $output;
}

// Install dependencies - using production optimized settings
echo "Installing dependencies...\n";
executeCommand("composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress", "Composer install", 600);

// Generate autoload files
executeCommand("composer dump-autoload --optimize --no-dev", "Optimize autoload", 120);

// Generate application key if needed
if (empty(getenv('APP_KEY'))) {
    echo "Generating application key...\n";
    executeCommand("php artisan key:generate --force --no-interaction", "Generate application key");
}

// Clear any cached files that might interfere
echo "Clearing cached files...\n";
executeCommand("php artisan config:clear", "Clear config cache");
executeCommand("php artisan cache:clear", "Clear application cache");  
executeCommand("php artisan view:clear", "Clear view cache");
executeCommand("php artisan route:clear", "Clear route cache");

// Run package discovery with timeout protection
echo "Running package discovery...\n";
executeCommand("php artisan package:discover --ansi", "Package discovery", 180);

// Run migrations
echo "Running database migrations...\n";
executeCommand("php artisan migrate --force --no-interaction", "Database migrations");

// Seed database (non-critical, can fail)
echo "Seeding database...\n";
executeCommand("php artisan db:seed --force --no-interaction", "Database seeding");

// Create storage link (non-critical, can fail)
executeCommand("php artisan storage:link", "Create storage link");

// Cache configurations for production performance
echo "Caching configurations for production...\n";
executeCommand("php artisan config:cache", "Cache config");
executeCommand("php artisan route:cache", "Cache routes");
executeCommand("php artisan view:cache", "Cache views");

// Set proper permissions for production
echo "Setting permissions...\n";
executeCommand("chmod -R 755 storage", "Set storage permissions");
executeCommand("chmod -R 755 bootstrap/cache", "Set cache permissions");

echo "=== Deployment completed successfully! ===\n";
echo "Application is ready to serve requests.\n";
?>