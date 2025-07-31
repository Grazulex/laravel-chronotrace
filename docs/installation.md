# Installation Guide for Laravel ChronoTrace

This guide covers the complete installation process for Laravel ChronoTrace with Laravel 12.x.

## Requirements

- **PHP 8.3+** (Required for Laravel 12.x)
- **Laravel 12.x** (Latest supported version)
- **Composer** (For package management)
- **Queue System** (Redis/Database - recommended for production)
- **Storage** (Local filesystem or S3-compatible storage)

## Quick Installation

### 1. Install via Composer

```bash
composer require --dev grazulex/laravel-chronotrace
```

### 2. Automatic Installation and Configuration

Run the automatic installation command:

```bash
php artisan chronotrace:install
```

**What this command does:**
- ✅ Publishes configuration file to `config/chronotrace.php`
- ✅ Detects Laravel 12.x and configures middleware automatically
- ✅ Creates storage directory with proper permissions
- ✅ Adds middleware to `bootstrap/app.php` for Laravel 12.x
- ✅ Provides manual setup instructions if automatic configuration fails

### 3. Verify Installation

```bash
# Comprehensive configuration check
php artisan chronotrace:diagnose

# Test middleware registration
php artisan chronotrace:test-middleware

# Test recording functionality
php artisan chronotrace:record /test
```

## Laravel 12.x-Specific Setup

### Automatic Middleware Configuration

For Laravel 12.x, the installer automatically adds ChronoTrace middleware to your `bootstrap/app.php`:

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // ChronoTrace middleware - automatically added by chronotrace:install
        $middleware->web(append: [
            \Grazulex\LaravelChronotrace\Middleware\ChronoTraceMiddleware::class,
        ]);
        $middleware->api(append: [
            \Grazulex\LaravelChronotrace\Middleware\ChronoTraceMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
```

### Manual Middleware Configuration

If automatic configuration fails, add the middleware manually to `bootstrap/app.php`:

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

## Configuration Options

### Basic Configuration

The installer creates `config/chronotrace.php` with sensible defaults. You can also configure via environment variables:

```env
# Basic settings
CHRONOTRACE_ENABLED=true
CHRONOTRACE_MODE=record_on_error
CHRONOTRACE_STORAGE=local

# Performance settings
CHRONOTRACE_ASYNC_STORAGE=true
CHRONOTRACE_QUEUE_CONNECTION=database
```

### Environment-Specific Configuration

#### Development Environment
```env
CHRONOTRACE_ENABLED=true
CHRONOTRACE_MODE=always
CHRONOTRACE_STORAGE=local
CHRONOTRACE_DEBUG=true
CHRONOTRACE_ASYNC_STORAGE=false
```

#### Production Environment
```env
CHRONOTRACE_ENABLED=true
CHRONOTRACE_MODE=record_on_error
CHRONOTRACE_STORAGE=s3
CHRONOTRACE_ASYNC_STORAGE=true
CHRONOTRACE_QUEUE_CONNECTION=redis
```

## Storage Setup

### Local Storage (Development)

Local storage is configured by default:

```bash
# Ensure storage directory exists and is writable
mkdir -p storage/chronotrace
chmod 755 storage/chronotrace
```

### S3 Storage (Production)

For production deployments, configure S3 storage:

```env
# S3 configuration
CHRONOTRACE_STORAGE=s3
CHRONOTRACE_S3_BUCKET=your-chronotrace-bucket
CHRONOTRACE_S3_REGION=us-east-1
CHRONOTRACE_S3_PREFIX=traces

# AWS credentials (can use IAM roles in production)
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
```

### MinIO Storage (Self-Hosted)

For self-hosted S3-compatible storage:

```env
CHRONOTRACE_STORAGE=s3
CHRONOTRACE_S3_BUCKET=chronotrace
CHRONOTRACE_S3_REGION=us-east-1
CHRONOTRACE_S3_ENDPOINT=https://minio.yourcompany.com
```

## Queue Configuration

ChronoTrace works best with queue-based async storage in production:

### Redis Queue (Recommended)

```env
# Redis configuration for queues
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# ChronoTrace specific queue settings
CHRONOTRACE_QUEUE_CONNECTION=redis
CHRONOTRACE_QUEUE=chronotrace
```

### Database Queue (Alternative)

```env
# Database queue configuration
QUEUE_CONNECTION=database
CHRONOTRACE_QUEUE_CONNECTION=database
CHRONOTRACE_QUEUE=chronotrace
```

Run the queue worker:

```bash
php artisan queue:work --queue=chronotrace
```

## Post-Installation Steps

### 1. Validate Configuration

```bash
# Run comprehensive diagnostics
php artisan chronotrace:diagnose
```

Expected output:
```
🔍 ChronoTrace Configuration Diagnosis

📋 General Configuration:
  enabled: true
  mode: record_on_error
  storage: local
  async_storage: true

⚡ Queue Configuration:
  queue_connection: auto-detect
  ✅ Auto-detected working connection: database

💾 Storage Configuration:
  ✅ Storage configuration looks good

🔐 Permissions Check:
  ✅ Storage directory permissions are correct

✅ All tests passed! ChronoTrace should work correctly.
```

### 2. Test Middleware

```bash
php artisan chronotrace:test-middleware
```

### 3. Record Your First Trace

```bash
# Record a test endpoint
php artisan chronotrace:record /

# List the recorded trace
php artisan chronotrace:list

# Replay the trace
php artisan chronotrace:replay {trace-id}
```

## Troubleshooting

### Common Installation Issues

#### Middleware Not Working

**Problem**: Traces are not being recorded automatically.

**Solution**:
```bash
# Check middleware configuration
php artisan chronotrace:test-middleware

# Verify routes are being traced
php artisan route:list | grep ChronoTrace
```

#### Storage Permission Issues

**Problem**: Cannot write traces to storage.

**Solution**:
```bash
# Fix storage permissions
chmod -R 755 storage/chronotrace
chown -R www-data:www-data storage/chronotrace
```

#### Queue Connection Issues

**Problem**: Async storage not working.

**Solution**:
```bash
# Check queue configuration
php artisan chronotrace:diagnose

# Test queue connection
php artisan queue:work --queue=chronotrace --timeout=30
```

#### S3 Configuration Issues

**Problem**: Cannot connect to S3.

**Solution**:
```bash
# Test S3 connection
aws s3 ls s3://your-chronotrace-bucket/

# Verify credentials
php artisan tinker
>>> Storage::disk('s3')->exists('test');
```

### Getting Help

If you encounter issues:

1. **Run diagnostics**: `php artisan chronotrace:diagnose`
2. **Check logs**: `tail -f storage/logs/laravel.log`
3. **Verify configuration**: Review `config/chronotrace.php`
4. **Test manually**: Try recording a trace manually
5. **Check GitHub issues**: [Laravel ChronoTrace Issues](https://github.com/Grazulex/laravel-chronotrace/issues)

## Next Steps

After successful installation:

1. **[Configure for your environment](configuration.md)** - Set up environment-specific settings
2. **[Learn the commands](commands.md)** - Master all available Artisan commands
3. **[Try basic usage examples](../examples/basic-usage.md)** - Start recording and analyzing traces
4. **[Set up production monitoring](../examples/production-monitoring.md)** - Configure for production use

## Advanced Installation

### Manual Configuration

If you prefer manual configuration, you can publish the config file separately:

```bash
php artisan vendor:publish --tag=chronotrace-config
```

### Custom Service Provider

For advanced customization, you can extend the service provider:

```php
<?php

namespace App\Providers;

use Grazulex\LaravelChronotrace\LaravelChronotraceServiceProvider;

class CustomChronoTraceServiceProvider extends LaravelChronotraceServiceProvider
{
    public function boot(): void
    {
        parent::boot();
        
        // Add custom configuration or listeners
    }
}
```

Register in `config/app.php`:

```php
'providers' => [
    // Replace the original provider
    // Grazulex\LaravelChronotrace\LaravelChronotraceServiceProvider::class,
    App\Providers\CustomChronoTraceServiceProvider::class,
],
```

### Docker Installation

For Docker environments:

```dockerfile
# In your Dockerfile
RUN composer require --dev grazulex/laravel-chronotrace

# Ensure storage directory exists
RUN mkdir -p storage/chronotrace && \
    chown -R www-data:www-data storage/chronotrace
```

```yaml
# docker-compose.yml
services:
  app:
    # ... your app configuration
    environment:
      - CHRONOTRACE_ENABLED=true
      - CHRONOTRACE_STORAGE=local
    volumes:
      - ./storage/chronotrace:/var/www/html/storage/chronotrace
```

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