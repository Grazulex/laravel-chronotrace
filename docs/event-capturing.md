# Event Capturing

ChronoTrace captures various types of events during request execution. This guide explains what events are captured and how to configure event capture.

## Event Types

### 📊 Database Events

**What's Captured:**
- SQL queries with bindings and execution time
- Transaction begin/commit/rollback operations
- Connection information
- Query performance metrics

**Example Output:**
```
📊 DATABASE EVENTS
  🔍 [14:30:22.123] Query: SELECT * FROM users WHERE active = ? (15ms on mysql)
  🔍 [14:30:22.145] Query: SELECT * FROM roles WHERE user_id IN (?, ?, ?) (8ms on mysql)
  🔄 [14:30:22.100] Transaction BEGIN on mysql
  ✅ [14:30:22.200] Transaction COMMIT on mysql
```

**Configuration:**
```php
'capture' => [
    'database' => true,
],
```

### 🗄️ Cache Events

**What's Captured:**
- Cache hits and misses
- Cache write operations
- Cache deletions/forgetting
- Store information and data sizes

**Example Output:**
```
🗄️  CACHE EVENTS
  ✅ [14:30:22.120] Cache HIT: users:list (store: redis, size: 1,234 bytes)
  ❌ [14:30:22.125] Cache MISS: products:featured (store: redis)
  💾 [14:30:22.150] Cache WRITE: users:list (store: redis)
  🗑️  [14:30:22.180] Cache FORGET: users:stale (store: redis)
```

**Configuration:**
```php
'capture' => [
    'cache' => true,
],
```

### 🌐 HTTP Events

**What's Captured:**
- External HTTP requests made during execution
- Request/response details including headers and body sizes
- Connection failures and timeouts
- Response status codes and timing

**Example Output:**
```
🌐 HTTP EVENTS
  📤 [14:30:22.200] HTTP Request: GET https://api.external.com/validation (body: 256 bytes)
  📥 [14:30:22.230] HTTP Response: GET https://api.external.com/validation → 200 (1,234 bytes)
  ❌ [14:30:22.250] HTTP Connection Failed: POST https://api.slow.com/webhook
```

**Configuration:**
```php
'capture' => [
    'http' => true,
],
```

### ⚙️ Queue Job Events

**What's Captured:**
- Job processing start/completion
- Job failures and retry attempts
- Queue and connection information
- Job execution timing

**Example Output:**
```
⚙️  JOB EVENTS
  🔄 [14:30:22.300] Job STARTED: ProcessUserRegistration (queue: default, connection: redis) - attempt #1
  ✅ [14:30:22.450] Job COMPLETED: ProcessUserRegistration (queue: default, connection: redis)
  ❌ [14:30:22.500] Job FAILED: SendWelcomeEmail (queue: emails, connection: redis) - Exception: SMTP timeout
```

**Configuration:**
```php
'capture' => [
    'jobs' => true,
],
```

### 🎉 Laravel Events

**What's Captured:**
- Custom Laravel events fired during execution
- Event listener execution
- Event data (PII-scrubbed)

**Note:** This is disabled by default as it can be very verbose.

**Configuration:**
```php
'capture' => [
    'events' => false, // Disabled by default
],
```

## Configuration Options

### Enable/Disable Event Types

```php
// config/chronotrace.php
'capture' => [
    'database' => env('CHRONOTRACE_CAPTURE_DATABASE', true),
    'cache' => env('CHRONOTRACE_CAPTURE_CACHE', true),
    'http' => env('CHRONOTRACE_CAPTURE_HTTP', true),
    'jobs' => env('CHRONOTRACE_CAPTURE_JOBS', true),
    'events' => env('CHRONOTRACE_CAPTURE_EVENTS', false),
],
```

### Environment Variables

```env
# Enable specific event types
CHRONOTRACE_CAPTURE_DATABASE=true
CHRONOTRACE_CAPTURE_CACHE=true
CHRONOTRACE_CAPTURE_HTTP=true
CHRONOTRACE_CAPTURE_JOBS=true
CHRONOTRACE_CAPTURE_EVENTS=false
```

## Event Listeners

ChronoTrace uses Laravel event listeners to capture these events:

- **`DatabaseEventListener`** - Listens to `Illuminate\Database\Events\*`
- **`CacheEventListener`** - Listens to `Illuminate\Cache\Events\*`
- **`HttpEventListener`** - Listens to HTTP client events
- **`QueueEventListener`** - Listens to `Illuminate\Queue\Events\*`

## Performance Considerations

### High-Volume Applications

For applications with high event volume, consider:

```php
'capture' => [
    'database' => true,   // Keep for debugging
    'cache' => false,     // Disable if too verbose
    'http' => true,       // Keep for external dependencies
    'jobs' => true,       // Keep for background processing
    'events' => false,    // Always disable in production
],
```

### Development vs Production

**Development:**
```php
'capture' => [
    'database' => true,
    'cache' => true,
    'http' => true,
    'jobs' => true,
    'events' => true,  // Enable for full debugging
],
```

**Production:**
```php
'capture' => [
    'database' => true,
    'cache' => false,   // Often too verbose
    'http' => true,     // Critical for monitoring
    'jobs' => true,     // Important for background tasks
    'events' => false,  // Too verbose for production
],
```

## Filtering During Replay

You can filter events when replaying traces:

```bash
# View only database events
php artisan chronotrace:replay {trace-id} --db

# View only cache events
php artisan chronotrace:replay {trace-id} --cache

# View only HTTP events
php artisan chronotrace:replay {trace-id} --http

# View only job events
php artisan chronotrace:replay {trace-id} --jobs

# Combine multiple filters
php artisan chronotrace:replay {trace-id} --db --http
```

## Custom Events

If you need to capture custom application events, you can extend ChronoTrace by creating your own event listeners:

```php
use Grazulex\LaravelChronotrace\Services\TraceRecorder;

class CustomEventListener
{
    public function __construct(private TraceRecorder $recorder)
    {
    }

    public function handle(YourCustomEvent $event): void
    {
        if ($this->recorder->isRecording()) {
            $this->recorder->captureEvent('custom', [
                'type' => 'your_event_type',
                'data' => $event->getData(),
                'timestamp' => microtime(true),
            ]);
        }
    }
}
```

## Troubleshooting

### No Events Captured

**Check Configuration:**
```bash
php artisan config:show chronotrace.capture
```

**Verify Event Types are Enabled:**
```php
// Ensure at least some event types are enabled
'capture' => [
    'database' => true,  // At minimum, enable database events
],
```

### Too Many Events

**Reduce Verbosity:**
```php
'capture' => [
    'events' => false,   // Disable Laravel events
    'cache' => false,    // Disable cache events if too many
],
```

### Missing Events

**Check Event Listeners:**
Ensure the service provider is properly registered and event listeners are bound.

```bash
php artisan event:list | grep Chronotrace
```

## Best Practices

1. **Start Simple**: Begin with database and HTTP events only
2. **Monitor Performance**: Watch for impact on application performance
3. **Adjust for Environment**: Use different settings for dev/staging/production
4. **Regular Review**: Periodically review which events provide value
5. **Storage Management**: More events = larger traces = more storage needed

## Next Steps

- [Learn about storage configuration](storage.md)
- [Understand security and PII scrubbing](security.md)
- [Check out practical examples](../examples/README.md)