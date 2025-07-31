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

### 2. Publish Configuration

```bash
php artisan vendor:publish --tag=chronotrace-config
```

This will create the configuration file at `config/chronotrace.php`.

### 3. Configure Storage (Optional)

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

### 4. Environment Configuration

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

## Service Provider Registration

The service provider is automatically registered via Laravel's package discovery. If you need to register it manually, add it to your `config/app.php`:

```php
'providers' => [
    // Other providers...
    Grazulex\LaravelChronotrace\LaravelChronotraceServiceProvider::class,
],
```

## Middleware Registration (Optional)

To capture HTTP requests automatically, you can register the middleware in your `app/Http/Kernel.php`:

```php
protected $middleware = [
    // Other middleware...
    \Grazulex\LaravelChronotrace\Middleware\ChronoTraceMiddleware::class,
];
```

Or apply it to specific route groups:

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
chronotrace:list    List stored traces
chronotrace:purge   Purge old traces  
chronotrace:record  Record a trace for a specific URL
chronotrace:replay  Replay and display events from a stored trace
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

### Permission Issues

If you encounter permission errors:

```bash
sudo chown -R $(whoami) storage/
sudo chmod -R 755 storage/
```

### Configuration Not Found

If the configuration isn't published properly:

```bash
php artisan vendor:publish --provider="Grazulex\LaravelChronotrace\LaravelChronotraceServiceProvider" --tag=config --force
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