# Configuration

ChronoTrace offers extensive configuration options to control when and how traces are captured. This guide covers all available settings.

## Configuration File

The configuration file is located at `config/chronotrace.php` after publishing:

```bash
php artisan vendor:publish --tag=chronotrace-config
```

## Recording Modes

### Available Modes

```php
'mode' => env('CHRONOTRACE_MODE', 'record_on_error'),
```

- **`always`** - Record every request (high overhead, use carefully)
- **`sample`** - Record a percentage of successful requests
- **`record_on_error`** - Only record when 5xx errors occur (recommended)
- **`targeted`** - Record only specific routes or jobs

### Sample Mode Configuration

```php
'sample_rate' => env('CHRONOTRACE_SAMPLE_RATE', 0.001), // 0.1% of requests
```

### Targeted Mode Configuration

```php
'targets' => [
    'routes' => [
        'checkout/*',
        'payment/*',
        'orders/*',
        'api/v1/users/*',
    ],
    'jobs' => [
        'ProcessPayment',
        'SendOrderConfirmation',
        'App\Jobs\GenerateReport',
    ],
],
```

## Storage Configuration

### Local Storage

```php
'storage' => env('CHRONOTRACE_STORAGE', 'local'),
'path' => env('CHRONOTRACE_PATH', storage_path('chronotrace')),
```

### S3/Minio Storage

```php
'storage' => env('CHRONOTRACE_STORAGE', 's3'),
's3' => [
    'bucket' => env('CHRONOTRACE_S3_BUCKET', 'chronotrace'),
    'region' => env('CHRONOTRACE_S3_REGION', 'us-east-1'),
    'endpoint' => env('CHRONOTRACE_S3_ENDPOINT'), // For Minio
    'path_prefix' => env('CHRONOTRACE_S3_PREFIX', 'traces'),
],
```

## Data Retention

```php
'retention_days' => env('CHRONOTRACE_RETENTION_DAYS', 15),
'auto_purge' => env('CHRONOTRACE_AUTO_PURGE', true),
```

- **`retention_days`** - How long to keep traces before automatic deletion
- **`auto_purge`** - Whether to automatically delete old traces

## Compression Settings

```php
'compression' => [
    'enabled' => true,
    'level' => 6, // Compression level (1-9)
    'max_payload_size' => 1024 * 1024, // 1MB threshold for blob storage
],
```

## Event Capture Configuration

Control which types of events are captured:

```php
'capture' => [
    'database' => env('CHRONOTRACE_CAPTURE_DATABASE', true),
    'cache' => env('CHRONOTRACE_CAPTURE_CACHE', true),
    'http' => env('CHRONOTRACE_CAPTURE_HTTP', true),
    'jobs' => env('CHRONOTRACE_CAPTURE_JOBS', true),
    'events' => env('CHRONOTRACE_CAPTURE_EVENTS', false),
],
```

### Individual Event Types

- **`database`** - SQL queries, transactions, connections
- **`cache`** - Cache hits, misses, writes, deletions
- **`http`** - External HTTP requests made during execution
- **`jobs`** - Queue job processing, failures, completions
- **`events`** - Laravel events (can be verbose, disabled by default)

## Security & PII Scrubbing

```php
'scrub' => [
    'password',
    'token',
    'secret',
    'key',
    'authorization',
    'cookie',
    'session',
    'credit_card',
    'ssn',
    'email',
    'phone',
],
```

These fields will be automatically masked in captured data to protect sensitive information.

## Performance Configuration

### Async Storage

```php
'async_storage' => true,
'queue_connection' => env('CHRONOTRACE_QUEUE_CONNECTION', 'default'),
'queue_name' => env('CHRONOTRACE_QUEUE', 'chronotrace'),
```

Enable async storage to avoid blocking request processing while saving traces.

### Debug Mode

```php
'debug' => env('CHRONOTRACE_DEBUG', false),
```

Enable debug mode for troubleshooting (adds logging overhead).

## Environment Variables

Here's a complete `.env` configuration example:

```env
# Basic Configuration
CHRONOTRACE_ENABLED=true
CHRONOTRACE_MODE=record_on_error
CHRONOTRACE_SAMPLE_RATE=0.001

# Storage
CHRONOTRACE_STORAGE=local
CHRONOTRACE_PATH="/var/www/storage/chronotrace"

# S3 Storage (alternative)
# CHRONOTRACE_STORAGE=s3
# CHRONOTRACE_S3_BUCKET=my-chronotrace-bucket
# CHRONOTRACE_S3_REGION=us-west-2
# CHRONOTRACE_S3_ENDPOINT=https://s3.amazonaws.com

# Retention
CHRONOTRACE_RETENTION_DAYS=30
CHRONOTRACE_AUTO_PURGE=true

# Event Capture
CHRONOTRACE_CAPTURE_DATABASE=true
CHRONOTRACE_CAPTURE_CACHE=true
CHRONOTRACE_CAPTURE_HTTP=true
CHRONOTRACE_CAPTURE_JOBS=true
CHRONOTRACE_CAPTURE_EVENTS=false

# Queue Configuration
CHRONOTRACE_QUEUE_CONNECTION=redis
CHRONOTRACE_QUEUE=chronotrace

# Debug
CHRONOTRACE_DEBUG=false
```

## Production Recommendations

### High-Traffic Applications

```php
'mode' => 'record_on_error', // Only capture errors
'sample_rate' => 0.0001,     // Very low sample rate if using 'sample' mode
'async_storage' => true,      // Always use async storage
'compression' => [
    'enabled' => true,
    'level' => 9,             // Maximum compression
],
```

### Development Environment

```php
'mode' => 'always',          // Capture everything for debugging
'debug' => true,             // Enable debug logging
'retention_days' => 7,       // Shorter retention for disk space
```

### Staging Environment

```php
'mode' => 'sample',
'sample_rate' => 0.01,       // 1% sample rate
'capture' => [
    'events' => true,        // Enable event capture for testing
],
```

## Configuration Validation

Validate your configuration with:

```bash
php artisan config:show chronotrace
```

## Troubleshooting Configuration

### Invalid Storage Settings

Check your storage configuration:

```bash
php artisan chronotrace:list
```

### Permission Issues

Ensure proper permissions:

```bash
# For local storage
chmod -R 755 storage/chronotrace/

# For S3, test credentials
aws s3 ls s3://your-bucket/
```

### Queue Issues

Verify queue configuration:

```bash
php artisan queue:work --queue=chronotrace --verbose
```

## Next Steps

- [Learn about available commands](commands.md)
- [Understand event capturing](event-capturing.md)
- [Check out practical examples](../examples/README.md)