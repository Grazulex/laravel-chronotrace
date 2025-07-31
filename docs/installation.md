# Installation

## Requirements

- **PHP 8.3+**
- **Laravel 12.x**
- **Composer**

## Installation Steps

### 1. Install via Composer

```bash
composer require --dev grazulex/laravel-chronotrace
```

### 2. Install and Configure

Run the automatic installation command:

```bash
php artisan chronotrace:install
```

This command will:
- Publish the configuration file at `config/chronotrace.php`
- Detect your Laravel version (11+ or legacy)
- Automatically configure middleware for Laravel 11+
- Provide manual setup instructions if needed

If you need to force reinstall or overwrite existing configuration:

```bash
php artisan chronotrace:install --force
```

### 3. Verify Installation

Check that everything is properly configured:

```bash
# Diagnose configuration
php artisan chronotrace:diagnose

# Test middleware setup
php artisan chronotrace:test-middleware
```

### 4. Configure Storage (Optional)

By default, traces are stored locally in `storage/chronotrace/`. For production environments, consider using S3 or Minio:

```bash
# For S3 storage
CHRONOTRACE_STORAGE=s3
CHRONOTRACE_S3_BUCKET=your-bucket-name
CHRONOTRACE_S3_REGION=us-east-1

# For Minio
CHRONOTRACE_STORAGE=s3
CHRONOTRACE_S3_BUCKET=chronotrace
CHRONOTRACE_S3_ENDPOINT=https://your-minio.example.com
CHRONOTRACE_S3_REGION=us-east-1
```

### 5. Environment Configuration

Add these environment variables to your `.env` file:

```env
# Enable ChronoTrace
CHRONOTRACE_ENABLED=true

# Recording mode
CHRONOTRACE_MODE=record_on_error  # always | sample | record_on_error | targeted

# Sample rate (when mode=sample)
CHRONOTRACE_SAMPLE_RATE=0.001  # 0.1% of successful requests

# Storage configuration
CHRONOTRACE_STORAGE=local
CHRONOTRACE_PATH="/path/to/storage/chronotrace"

# Retention policy
CHRONOTRACE_RETENTION_DAYS=15
CHRONOTRACE_AUTO_PURGE=true

# What to capture
CHRONOTRACE_CAPTURE_DATABASE=true
CHRONOTRACE_CAPTURE_CACHE=true
CHRONOTRACE_CAPTURE_HTTP=true
CHRONOTRACE_CAPTURE_JOBS=true
CHRONOTRACE_CAPTURE_EVENTS=false  # Disabled by default (verbose)

# Queue configuration for async storage
CHRONOTRACE_QUEUE_CONNECTION=default
CHRONOTRACE_QUEUE=chronotrace

# Debug mode
CHRONOTRACE_DEBUG=false
```

## Middleware Registration

For **Laravel 11+**, the middleware is automatically configured by the `chronotrace:install` command.

For **Legacy Laravel versions**, the middleware is auto-registered through the service provider.

### Manual Middleware Setup (Laravel 11+)

If automatic configuration fails, manually add the middleware to your `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \Grazulex\LaravelChronotrace\Middleware\ChronoTraceMiddleware::class,
    ]);
    $middleware->api(append: [
        \Grazulex\LaravelChronotrace\Middleware\ChronoTraceMiddleware::class,
    ]);
})
```

### Selective Route Registration

Apply middleware to specific route groups:

```php
Route::middleware(['chronotrace'])->group(function () {
    Route::get('/api/users', [UserController::class, 'index']);
    // Other routes...
});
```

## Verification

Verify the installation by listing available commands:

```bash
php artisan list chronotrace
```

You should see:

```
chronotrace:diagnose       Diagnose ChronoTrace configuration and potential issues
chronotrace:install        Install and configure ChronoTrace middleware
chronotrace:list           List stored traces
chronotrace:purge          Purge old traces  
chronotrace:record         Record a trace for a specific URL
chronotrace:replay         Replay and display events from a stored trace
chronotrace:test-middleware Test ChronoTrace middleware installation and activation
```

## Storage Permissions

Ensure your storage directory has proper write permissions:

```bash
# For local storage
chmod -R 755 storage/chronotrace/
chown -R www-data:www-data storage/chronotrace/

# Create directory if it doesn't exist
mkdir -p storage/chronotrace
```

## Queue Setup (For Async Storage)

If you plan to use async storage for better performance, ensure your queue worker is running:

```bash
php artisan queue:work --queue=chronotrace
```

## Troubleshooting

### Run Diagnostics

If you encounter any issues, start with the diagnostic command:

```bash
php artisan chronotrace:diagnose
```

This will check:
- Configuration validity
- Storage permissions
- Queue connectivity
- End-to-end functionality

### Test Middleware

Verify middleware installation:

```bash
php artisan chronotrace:test-middleware
```

### Permission Issues

If you encounter permission errors:

```bash
sudo chown -R $(whoami) storage/
sudo chmod -R 755 storage/
```

### Configuration Issues

If the configuration isn't working properly:

```bash
# Force reinstall
php artisan chronotrace:install --force

# Check configuration values
php artisan config:show chronotrace
```

### Storage Issues

For S3 storage issues, verify your AWS credentials:

```bash
aws s3 ls s3://your-bucket-name/
```

## Next Steps

- [Configure your settings](configuration.md)
- [Learn about available commands](commands.md)
- [Check out examples](../examples/README.md)