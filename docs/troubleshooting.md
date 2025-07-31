# Troubleshooting

This guide helps you resolve common issues with Laravel ChronoTrace.

## Installation Issues

### Package Not Found

**Error:**
```
Could not find package grazulex/laravel-chronotrace
```

**Solution:**
```bash
# Ensure you're using the correct package name
composer require --dev grazulex/laravel-chronotrace

# Clear composer cache if needed
composer clear-cache
composer update
```

### Configuration Not Published

**Error:**
```
Configuration file not found
```

**Solution:**
```bash
# Publish the configuration
php artisan vendor:publish --tag=chronotrace-config

# Force republish if needed
php artisan vendor:publish --tag=chronotrace-config --force

# Verify config exists
ls -la config/chronotrace.php
```

### Service Provider Not Registered

**Error:**
```
Class 'ChronoTrace' not found
```

**Solution:**
Laravel's package discovery should handle this automatically. If not:

```php
// config/app.php
'providers' => [
    // Other providers...
    Grazulex\LaravelChronotrace\LaravelChronotraceServiceProvider::class,
],
```

## Storage Issues

### Permission Denied (Local Storage)

**Error:**
```
Permission denied: /var/www/storage/chronotrace
```

**Solution:**
```bash
# Create directory if it doesn't exist
mkdir -p storage/chronotrace

# Set proper permissions
chmod -R 755 storage/chronotrace/
chown -R www-data:www-data storage/chronotrace/

# For development (less secure but simpler)
chmod -R 777 storage/chronotrace/
```

### S3 Access Denied

**Error:**
```
AWS Error: Access Denied (403)
```

**Solutions:**

1. **Check Credentials:**
```bash
# Test AWS credentials
aws s3 ls s3://your-bucket-name/

# Verify environment variables
echo $AWS_ACCESS_KEY_ID
echo $AWS_SECRET_ACCESS_KEY
```

2. **Verify Bucket Policy:**
```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Principal": {
                "AWS": "arn:aws:iam::ACCOUNT:user/chronotrace-user"
            },
            "Action": [
                "s3:GetObject",
                "s3:PutObject",
                "s3:DeleteObject",
                "s3:ListBucket"
            ],
            "Resource": [
                "arn:aws:s3:::your-bucket-name",
                "arn:aws:s3:::your-bucket-name/*"
            ]
        }
    ]
}
```

3. **Check IAM Permissions:**
```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "s3:GetObject",
                "s3:PutObject",
                "s3:DeleteObject",
                "s3:ListBucket"
            ],
            "Resource": [
                "arn:aws:s3:::your-bucket-name",
                "arn:aws:s3:::your-bucket-name/*"
            ]
        }
    ]
}
```

### Disk Space Issues

**Error:**
```
No space left on device
```

**Solution:**
```bash
# Check disk usage
df -h

# Find large traces
find storage/chronotrace/ -name "*.json" -size +10M -ls

# Purge old traces
php artisan chronotrace:purge --days=7 --confirm

# Check compression settings
php artisan config:show chronotrace.compression
```

## Recording Issues

### No Traces Recorded

**Check Configuration:**
```bash
# Verify ChronoTrace is enabled
php artisan config:show chronotrace.enabled

# Check recording mode
php artisan config:show chronotrace.mode

# For 'targeted' mode, verify targets
php artisan config:show chronotrace.targets
```

**Enable Debug Mode:**
```env
CHRONOTRACE_DEBUG=true
```

**Check Logs:**
```bash
tail -f storage/logs/laravel.log | grep ChronoTrace
```

### Recording Command Fails

**Error:**
```
php artisan chronotrace:record /api/users
Warning: Recording functionality not yet implemented.
```

**This is expected** - the record command shows a warning because it's a placeholder. The actual recording happens through:

1. **Middleware** (for HTTP requests)
2. **Event listeners** (automatic recording based on mode)
3. **Manual recording** (programmatically)

**Manual Recording Example:**
```php
use Grazulex\LaravelChronotrace\Services\TraceRecorder;

$recorder = app(TraceRecorder::class);
$recorder->startRecording();

// Your application logic here
$users = User::all();

$traceId = $recorder->stopRecording();
```

### Empty Traces

**Check Event Capture Settings:**
```bash
php artisan config:show chronotrace.capture
```

**Enable More Event Types:**
```env
CHRONOTRACE_CAPTURE_DATABASE=true
CHRONOTRACE_CAPTURE_CACHE=true
CHRONOTRACE_CAPTURE_HTTP=true
CHRONOTRACE_CAPTURE_JOBS=true
```

## Replay Issues

### Trace Not Found

**Error:**
```
Trace abc123 not found
```

**Solutions:**
```bash
# List available traces
php artisan chronotrace:list

# Check storage configuration
php artisan config:show chronotrace.storage
php artisan config:show chronotrace.path

# For S3, verify bucket access
aws s3 ls s3://your-bucket-name/traces/
```

### Replay Shows No Events

**Check Event Filters:**
```bash
# Try without filters first
php artisan chronotrace:replay {trace-id}

# Then try specific filters
php artisan chronotrace:replay {trace-id} --db
php artisan chronotrace:replay {trace-id} --cache
```

**Verify Event Capture:**
```bash
# Check if events were captured during recording
php artisan config:show chronotrace.capture
```

## Queue Issues

### Queue Jobs Not Processing

**Check Queue Worker:**
```bash
# Verify worker is running
ps aux | grep "queue:work"

# Start worker if needed
php artisan queue:work --queue=chronotrace --verbose

# Check for failed jobs
php artisan queue:failed
```

**Check Queue Configuration:**
```bash
php artisan config:show chronotrace.queue_connection
php artisan config:show chronotrace.queue_name
```

**Test Queue Connection:**
```bash
# For Redis
redis-cli ping

# For Database
php artisan queue:table
php artisan migrate
```

### Async Storage Not Working

**Disable Async Storage Temporarily:**
```env
CHRONOTRACE_ASYNC_STORAGE=false
```

**Check Queue Driver:**
```bash
php artisan config:show queue.default
```

## Performance Issues

### High Memory Usage

**Reduce Event Capture:**
```env
CHRONOTRACE_CAPTURE_EVENTS=false
CHRONOTRACE_CAPTURE_CACHE=false
```

**Enable Compression:**
```env
CHRONOTRACE_COMPRESSION_ENABLED=true
CHRONOTRACE_COMPRESSION_LEVEL=9
```

**Use Async Storage:**
```env
CHRONOTRACE_ASYNC_STORAGE=true
```

### Slow Response Times

**Change Recording Mode:**
```env
# From 'always' to error-only
CHRONOTRACE_MODE=record_on_error

# Or use sampling
CHRONOTRACE_MODE=sample
CHRONOTRACE_SAMPLE_RATE=0.001
```

**Disable Verbose Events:**
```env
CHRONOTRACE_CAPTURE_EVENTS=false
CHRONOTRACE_CAPTURE_CACHE=false
```

### Large Trace Files

**Enable Compression:**
```php
'compression' => [
    'enabled' => true,
    'level' => 9,
    'max_payload_size' => 512 * 1024, // 512KB
],
```

**Reduce Captured Data:**
```php
'capture' => [
    'database' => true,
    'cache' => false,    // Often verbose
    'http' => true,
    'jobs' => true,
    'events' => false,   // Very verbose
],
```

## Configuration Issues

### Environment Variables Not Loaded

**Check .env File:**
```bash
# Verify variables exist
grep CHRONOTRACE .env

# Test variable loading
php artisan tinker
>>> env('CHRONOTRACE_ENABLED')
```

**Clear Configuration Cache:**
```bash
php artisan config:clear
php artisan config:cache
```

### Invalid Configuration

**Validate Configuration:**
```bash
# Show full config
php artisan config:show chronotrace

# Test specific sections
php artisan config:show chronotrace.storage
php artisan config:show chronotrace.capture
```

## Common Error Messages

### "TraceStorage not found"

**Error:**
```
Target class [TraceStorage] does not exist
```

**Solution:**
```bash
# Ensure service provider is loaded
php artisan clear-compiled
php artisan config:clear
composer dump-autoload
```

### "Queue connection not found"

**Error:**
```
Queue connection [chronotrace] not configured
```

**Solution:**
```php
// config/queue.php - Add connection if using custom queue
'connections' => [
    'chronotrace' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'chronotrace',
    ],
],
```

### "S3 bucket not found"

**Error:**
```
The specified bucket does not exist
```

**Solution:**
```bash
# Create bucket
aws s3 mb s3://your-bucket-name

# Verify bucket exists
aws s3 ls | grep your-bucket-name

# Check region configuration
aws s3api get-bucket-location --bucket your-bucket-name
```

## Debug Mode

Enable debug mode for detailed logging:

```env
CHRONOTRACE_DEBUG=true
```

**Check Debug Output:**
```bash
tail -f storage/logs/laravel.log | grep -i chronotrace
```

## Testing Your Setup

### Verify Installation

```bash
# Check if commands are available
php artisan list chronotrace

# Expected output:
# chronotrace:list
# chronotrace:purge
# chronotrace:record
# chronotrace:replay
```

### Test Storage

```bash
# Test local storage
ls -la storage/chronotrace/

# Test S3 storage
aws s3 ls s3://your-bucket-name/traces/

# Test list command
php artisan chronotrace:list
```

### Test Configuration

```bash
# Show configuration
php artisan config:show chronotrace

# Test environment variables
php -r "echo env('CHRONOTRACE_ENABLED') ? 'Enabled' : 'Disabled';"
```

## Getting Help

### Enable Verbose Logging

```env
CHRONOTRACE_DEBUG=true
LOG_LEVEL=debug
```

### Collect System Information

```bash
# PHP version
php --version

# Laravel version
php artisan --version

# Package version
composer show grazulex/laravel-chronotrace

# Configuration
php artisan config:show chronotrace
```

### Log Analysis

```bash
# Filter ChronoTrace logs
grep -i chronotrace storage/logs/laravel.log

# Watch logs in real-time
tail -f storage/logs/laravel.log | grep -i chronotrace
```

### Create Minimal Test Case

```php
// Create a simple test route
Route::get('/test-chronotrace', function () {
    logger('Testing ChronoTrace');
    return response()->json(['status' => 'ok']);
});
```

```bash
# Test the route
curl http://your-app.test/test-chronotrace

# Check if trace was created
php artisan chronotrace:list
```

## Support Resources

1. **Documentation**: [Full documentation](README.md)
2. **Examples**: [Practical examples](../examples/README.md)
3. **Issues**: [GitHub Issues](https://github.com/Grazulex/laravel-chronotrace/issues)
4. **Discussions**: [GitHub Discussions](https://github.com/Grazulex/laravel-chronotrace/discussions)

When reporting issues, please include:

- PHP and Laravel versions
- ChronoTrace configuration (scrubbed of sensitive data)
- Error messages and stack traces
- Steps to reproduce the issue