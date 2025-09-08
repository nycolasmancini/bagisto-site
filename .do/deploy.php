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

// Prepare environment for composer install
echo "Preparing environment...\n";
if (!file_exists('.env')) {
    echo "Creating temporary .env file for bootstrap...\n";
    copy('.env.example', '.env');
    
    // Set basic required variables for bootstrap
    $envContent = file_get_contents('.env');
    $envContent = str_replace('APP_KEY=', 'APP_KEY=' . base64_encode(random_bytes(32)), $envContent);
    $envContent = str_replace('APP_ENV=local', 'APP_ENV=production', $envContent);
    $envContent = str_replace('APP_DEBUG=true', 'APP_DEBUG=false', $envContent);
    file_put_contents('.env', $envContent);
    
    echo "Temporary .env created for bootstrap process\n";
}

// Clear any existing problematic cache files
if (file_exists('bootstrap/cache/packages.php')) {
    unlink('bootstrap/cache/packages.php');
    echo "Cleared existing packages cache\n";
}
if (file_exists('bootstrap/cache/services.php')) {
    unlink('bootstrap/cache/services.php');
    echo "Cleared existing services cache\n";
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

// Run package discovery with fallback protection
echo "Running package discovery...\n";
$output = [];
$return = 0;
exec("timeout 180 php artisan package:discover --ansi 2>&1", $output, $return);

if ($return !== 0) {
    echo "WARNING: Package discovery failed, trying alternative approach...\n";
    echo "Error output:\n" . implode("\n", array_slice($output, -5)) . "\n";
    
    // Clear all cache and try again
    echo "Clearing all cached files...\n";
    executeCommand("php artisan config:clear", "Clear config cache");
    executeCommand("php artisan cache:clear", "Clear application cache");
    executeCommand("php artisan route:clear", "Clear route cache");
    executeCommand("php artisan view:clear", "Clear view cache");
    
    // Try package discovery without ANSI
    echo "Retrying package discovery without ANSI...\n";
    exec("timeout 120 php artisan package:discover 2>&1", $output2, $return2);
    
    if ($return2 !== 0) {
        echo "WARNING: Package discovery still failed. Continuing with manual provider registration...\n";
        echo "This may cause some packages to not be auto-discovered, but core functionality should work.\n";
        
        // Force clear bootstrap cache and continue
        executeCommand("rm -rf bootstrap/cache/*.php", "Clear bootstrap cache");
    } else {
        echo "SUCCESS: Package discovery completed on retry\n";
    }
} else {
    echo "SUCCESS: Package discovery completed\n";
}

// Remove temporary .env file to use DigitalOcean environment variables
if (file_exists('.env')) {
    unlink('.env');
    echo "Removed temporary .env file - using DigitalOcean environment variables\n";
}

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