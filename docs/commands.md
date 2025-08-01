# Commands

ChronoTrace provides several Artisan commands for managing and working with traces. This guide covers all available commands and their options.

## Available Commands

- [`chronotrace:install`](#chronotraceinstall) - Install and configure ChronoTrace middleware
- [`chronotrace:record`](#chronotracerecord) - Record a trace for a specific URL
- [`chronotrace:list`](#chronotracelist) - List stored traces
- [`chronotrace:replay`](#chronotracereplay) - Replay and display events from a stored trace  
- [`chronotrace:purge`](#chronotracepurge) - Purge old traces
- [`chronotrace:diagnose`](#chronotracediagnose) - Diagnose configuration and potential issues
- [`chronotrace:test-middleware`](#chronotracetestmiddleware) - Test middleware installation and activation
- [`chronotrace:test-internal`](#chronotracetestinternal) - Test ChronoTrace with internal Laravel operations

## `chronotrace:install`

Install and configure ChronoTrace middleware automatically.

### Syntax

```bash
php artisan chronotrace:install [options]
```

### Options

- **`--force`** - Overwrite existing configuration files

### Examples

```bash
# First-time installation
php artisan chronotrace:install

# Force reinstall and overwrite config
php artisan chronotrace:install --force
```

### What it does

1. **Publishes configuration** - Creates `config/chronotrace.php`
2. **Detects Laravel version** - Automatically configures for Laravel 11+ or legacy versions
3. **Configures middleware** - For Laravel 11+, automatically adds middleware to `bootstrap/app.php`
4. **Provides manual instructions** - If automatic configuration fails

### Laravel 11+ Automatic Configuration

For Laravel 11+, the command automatically adds this to your `bootstrap/app.php`:

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

### Legacy Laravel Support

For Laravel versions before 11, the middleware is auto-registered through the service provider.

### Notes

- Run this command after installing the package via Composer
- Safe to run multiple times (use `--force` to overwrite)
- Checks Laravel version and configures appropriately

## `chronotrace:record`

Record a trace by making an HTTP request to a specific URL.

### Syntax

```bash
php artisan chronotrace:record {url} [options]
```

### Arguments

- **`url`** - The URL to record (can be relative like `/api/users` or absolute)

### Options

- **`--method=GET`** - HTTP method to use (GET, POST, PUT, DELETE, etc.)
- **`--data=`** - JSON data to send with the request (for POST/PUT)
- **`--headers=`** - JSON headers to send with the request
- **`--timeout=30`** - Request timeout in seconds

### Examples

```bash
# Record a simple GET request
php artisan chronotrace:record /api/users

# Record a POST request with data
php artisan chronotrace:record /api/users \
  --method=POST \
  --data='{"name":"John Doe","email":"john@example.com"}'

# Record with custom timeout
php artisan chronotrace:record /api/slow-endpoint --timeout=60

# Record with custom headers
php artisan chronotrace:record /api/protected \
  --method=GET \
  --headers='{"Authorization":"Bearer token123","Content-Type":"application/json"}'

# Record an external API call
php artisan chronotrace:record https://api.external.com/data
```

### Notes

- The command makes an actual HTTP request to your application
- All events (DB queries, cache operations, etc.) are captured during the request
- The resulting trace can be replayed later with `chronotrace:replay`

## `chronotrace:list`

List all stored traces with their metadata.

### Syntax

```bash
php artisan chronotrace:list [options]
```

### Options

- **`--limit=20`** - Number of traces to display (default: 20)
- **`--full-id`** - Show full trace IDs instead of truncated versions

### Examples

```bash
# List recent traces
php artisan chronotrace:list

# List more traces
php artisan chronotrace:list --limit=50

# List all traces
php artisan chronotrace:list --limit=1000

# Show full trace IDs for easy copying
php artisan chronotrace:list --full-id

# Show recent traces with full IDs
php artisan chronotrace:list --limit=10 --full-id
```

### Output

```
┌─────────────┬─────────────┬─────────────────────┐
│ Trace ID    │ Size        │ Created At          │
├─────────────┼─────────────┼─────────────────────┤
│ abc12345... │ 15,234 bytes│ 2024-01-15 14:30:22 │
│ def67890... │ 8,912 bytes │ 2024-01-15 13:45:18 │
│ ghi54321... │ 23,456 bytes│ 2024-01-15 12:15:45 │
└─────────────┴─────────────┴─────────────────────┘
Showing 20 of 150 traces.
```

## `chronotrace:replay`

Replay and display events from a stored trace.

### Syntax

```bash
php artisan chronotrace:replay {trace-id} [options]
```

### Arguments

- **`trace-id`** - The ID of the trace to replay (from `chronotrace:list`)

### Options

- **`--db`** - Show only database events
- **`--cache`** - Show only cache events  
- **`--http`** - Show only HTTP events
- **`--jobs`** - Show only job events
- **`--format=table`** - Output format (table, json, raw)
- **`--generate-test`** - Generate a Pest test file from the trace
- **`--test-path=tests/Generated`** - Path for generated test files (default: tests/Generated)
- **`--detailed`** - Show detailed information including context, headers, and response content
- **`--context`** - Show Laravel context (versions, config, env vars)
- **`--headers`** - Show request and response headers
- **`--content`** - Show response content
- **`--bindings`** - Show SQL query bindings
- **`--compact`** - Show minimal information only

### Examples

```bash
# Replay all events from a trace
php artisan chronotrace:replay abc12345

# Show only database events
php artisan chronotrace:replay abc12345 --db

# Show only cache and HTTP events
php artisan chronotrace:replay abc12345 --cache --http

# Show detailed output with context and headers
php artisan chronotrace:replay abc12345 --detailed

# Show SQL query bindings for debugging
php artisan chronotrace:replay abc12345 --db --bindings

# Show Laravel context information
php artisan chronotrace:replay abc12345 --context

# Show request/response headers
php artisan chronotrace:replay abc12345 --headers

# Show response content
php artisan chronotrace:replay abc12345 --content

# Compact output (minimal information)
php artisan chronotrace:replay abc12345 --compact

# Output as JSON
php artisan chronotrace:replay abc12345 --format=json

# Output as raw data
php artisan chronotrace:replay abc12345 --format=raw

# Generate Pest test from trace
php artisan chronotrace:replay abc12345 --generate-test

# Generate test in custom directory
php artisan chronotrace:replay abc12345 --generate-test --test-path=tests/Integration
```

### Output Example

```
=== TRACE INFORMATION ===
🆔 Trace ID: abc12345-def6-7890-abcd-ef1234567890
🕒 Timestamp: 2024-01-15 14:30:22
🌍 Environment: production
🔗 Request URL: https://app.example.com/api/users
📊 Response Status: 200
⏱️  Duration: 245ms
💾 Memory Usage: 18.45 KB

=== CAPTURED EVENTS ===
📊 DATABASE EVENTS
  🔍 [14:30:22.123] Query: SELECT * FROM users WHERE active = ? (15ms on mysql)
  🔍 [14:30:22.145] Query: SELECT * FROM roles WHERE user_id IN (?, ?, ?) (8ms on mysql)

🗄️  CACHE EVENTS
  ❌ [14:30:22.120] Cache MISS: users:list (store: redis)
  💾 [14:30:22.150] Cache WRITE: users:list (store: redis)

🌐 HTTP EVENTS
  📤 [14:30:22.200] HTTP Request: GET https://api.external.com/validation
  📥 [14:30:22.230] HTTP Response: GET https://api.external.com/validation → 200 (1,234 bytes)

📈 EVENTS SUMMARY
  📊 Database events: 2
  🗄️  Cache events: 2
  🌐 HTTP events: 2
  ⚙️  Job events: 0
  📝 Total events: 6
```

## `chronotrace:purge`

Remove old traces based on retention policy.

### Syntax

```bash
php artisan chronotrace:purge [options]
```

### Options

- **`--days=30`** - Delete traces older than N days (default: 30)
- **`--confirm`** - Skip confirmation prompt

### Examples

```bash
# Purge traces older than 30 days (with confirmation)
php artisan chronotrace:purge

# Purge traces older than 7 days
php artisan chronotrace:purge --days=7

# Purge without confirmation prompt
php artisan chronotrace:purge --days=30 --confirm
```

### Safety

```bash
# Always confirm what will be deleted
Do you want to delete traces older than 30 days? (yes/no) [no]:
> yes

Purging traces older than 30 days...
Successfully purged 45 traces.
```

## `chronotrace:diagnose`

Diagnose ChronoTrace configuration and identify potential issues.

### Syntax

```bash
php artisan chronotrace:diagnose
```

### What it checks

1. **General Configuration** - Enabled status, mode, storage type, async settings
2. **Queue Configuration** - Queue connections, auto-detection, fallback settings
3. **Storage Configuration** - Storage paths, permissions, S3/MinIO settings
4. **Permissions** - File system permissions for local storage
5. **End-to-End Test** - Complete workflow validation

### Example Output

```
🔍 ChronoTrace Configuration Diagnosis

📋 General Configuration:
  enabled: true
  mode: record_on_error
  storage: local
  async_storage: false

⚡ Queue Configuration:
  queue_connection: auto-detect
  queue_name: chronotrace
  queue_fallback: true
    ✅ Auto-detected working connection: database

💾 Storage Configuration:
  Storage type: local
  Storage path: /app/storage/chronotrace
  ✅ Storage configuration looks good

🔐 Permissions Check:
  ✅ Storage directory permissions are correct

🧪 End-to-End Test:
  Testing trace creation and storage...
  ✅ Storage instance created successfully

✅ All tests passed! ChronoTrace should work correctly.
```

### Use Cases

- **Initial Setup** - Verify configuration after installation
- **Troubleshooting** - Identify configuration issues
- **Production Validation** - Ensure proper setup before deployment
- **Regular Health Checks** - Periodic configuration validation

## `chronotrace:test-middleware`

Test ChronoTrace middleware installation and activation.

### Syntax

```bash
php artisan chronotrace:test-middleware
```

### What it tests

1. **Configuration Check** - Validates basic settings
2. **Middleware Registration** - Verifies middleware can be instantiated
3. **Simulation Test** - Tests middleware with a simulated request
4. **Recommendations** - Provides setup suggestions

### Example Output

```
🧪 Testing ChronoTrace Middleware Installation

📋 Configuration Check:
  chronotrace.enabled: true
  chronotrace.mode: always
  chronotrace.debug: true

🔧 Middleware Registration Check:
  ✅ Middleware class can be instantiated
  ⚠️  Cannot programmatically verify middleware registration in Laravel 11+
     Please ensure it's added to bootstrap/app.php manually

💡 Recommendations:
  - Middleware is properly registered ✅

🚀 Simulation Test:
  📍 Simulating GET /test request...
  ✅ Middleware processed request successfully
```

### Manual Setup Instructions

If middleware is not properly registered, the command will show:

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

### Use Cases

- **Post-Installation** - Verify middleware is working after setup
- **Debugging** - Troubleshoot middleware-related issues
- **CI/CD Validation** - Automated testing in deployment pipelines

## Command Combinations

### Typical Workflow

```bash
# 1. Install and configure ChronoTrace
php artisan chronotrace:install

# 2. Verify installation
php artisan chronotrace:diagnose
php artisan chronotrace:test-middleware

# 3. Record a problematic request
php artisan chronotrace:record /api/problematic-endpoint

# 4. List traces to find the ID
php artisan chronotrace:list --full-id

# 5. Replay the trace to debug
php artisan chronotrace:replay abc12345 --db

# 6. Generate test from trace
php artisan chronotrace:replay abc12345 --generate-test

# 7. Clean up old traces
php artisan chronotrace:purge --days=7
```

### Monitoring Workflow

```bash
# Check system health
php artisan chronotrace:diagnose

# Check recent traces
php artisan chronotrace:list --limit=10

# Analyze database performance
php artisan chronotrace:replay {trace-id} --db

# Check external API calls
php artisan chronotrace:replay {trace-id} --http

# Generate regression tests
php artisan chronotrace:replay {trace-id} --generate-test --test-path=tests/Monitoring

# Clean up periodically
php artisan chronotrace:purge --days=15 --confirm
```

## Error Handling

### Common Errors

**Trace not found:**
```bash
php artisan chronotrace:replay invalid-id
# Error: Trace invalid-id not found.
```

**Storage permission issues:**
```bash
php artisan chronotrace:list
# Error: Failed to list traces: Permission denied
```

**Invalid JSON data:**
```bash
php artisan chronotrace:record /api/users --data='invalid-json'
# Warning: Invalid JSON data provided
```

## Automation

### Scheduled Purging

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Purge old traces daily
    $schedule->command('chronotrace:purge --days=15 --confirm')
             ->daily()
             ->onFailure(function () {
                 Log::error('Failed to purge ChronoTrace files');
             });
}
```

### Monitoring Script

```bash
#!/bin/bash
# check-traces.sh

TRACES=$(php artisan chronotrace:list --limit=1000 | grep -c "│")
echo "Total traces: $TRACES"

if [ $TRACES -gt 1000 ]; then
    echo "Warning: Too many traces, running purge..."
    php artisan chronotrace:purge --days=7 --confirm
fi
```

## Performance Considerations

### Large Traces

For traces with many events, use filters:

```bash
# Instead of showing everything
php artisan chronotrace:replay {trace-id}

# Show only relevant events
php artisan chronotrace:replay {trace-id} --db --cache
```

### JSON Output

For programmatic processing:

```bash
php artisan chronotrace:replay {trace-id} --format=json | jq '.database'
```

## `chronotrace:test-internal`

Test ChronoTrace with internal Laravel operations like database queries, cache operations, and custom events. This command addresses the limitation where `chronotrace:record` primarily captures external HTTP events.

### Syntax

```bash
php artisan chronotrace:test-internal [options]
```

### Options

- **`--with-db`** - Include database operation tests
- **`--with-cache`** - Include cache operation tests
- **`--with-events`** - Include custom event tests

### Examples

```bash
# Test all internal operations
php artisan chronotrace:test-internal --with-db --with-cache --with-events

# Test only database operations
php artisan chronotrace:test-internal --with-db

# Test cache and events only
php artisan chronotrace:test-internal --with-cache --with-events
```

### What it tests

#### Database Operations (`--with-db`)
- Creates test tables and performs CRUD operations
- Tests both Eloquent ORM and Query Builder
- Captures SQL queries, bindings, and timing

#### Cache Operations (`--with-cache`)
- Tests cache set/get operations
- Tests cache invalidation
- May fail in minimal environments without cache table

#### Custom Events (`--with-events`)
- Fires custom Laravel events
- Tests event listener registration
- Validates event data capture

### Output

The command provides detailed feedback including:

- **Trace ID**: Unique identifier for the test session
- **Operation Results**: Success/failure status for each test
- **ChronoTrace Activity**: Debug information about captured events
- **Usage Instructions**: How to replay or analyze the captured trace

### Use Cases

- **Testing Internal Operations**: When `chronotrace:record` doesn't capture internal events
- **Configuration Validation**: Verify ChronoTrace captures database and cache operations
- **Development Workflow**: Generate test traces for internal operations
- **Debugging**: Understand what ChronoTrace captures during internal Laravel operations

### Related Commands

After running `chronotrace:test-internal`, use:

```bash
# View the captured trace
php artisan chronotrace:replay {trace-id}

# Generate test file from captured operations  
php artisan chronotrace:replay {trace-id} --generate-test
```

For more details, see [Testing Internal Operations](testing-internal-operations.md).

## Next Steps

- [Learn about event capturing](event-capturing.md)
- [Understand storage options](storage.md)
- [Test internal operations](testing-internal-operations.md)
- [Check out practical examples](../examples/README.md)