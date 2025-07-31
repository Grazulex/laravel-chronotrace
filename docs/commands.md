# Commands

ChronoTrace provides several Artisan commands for managing and working with traces. This guide covers all available commands and their options.

## Available Commands

- [`chronotrace:record`](#chronotracerecord) - Record a trace for a specific URL
- [`chronotrace:list`](#chronotracelist) - List stored traces
- [`chronotrace:replay`](#chronotracereplay) - Replay and display events from a stored trace  
- [`chronotrace:purge`](#chronotracepurge) - Purge old traces

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

### Examples

```bash
# List recent traces
php artisan chronotrace:list

# List more traces
php artisan chronotrace:list --limit=50

# List all traces
php artisan chronotrace:list --limit=1000
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

### Examples

```bash
# Replay all events from a trace
php artisan chronotrace:replay abc12345

# Show only database events
php artisan chronotrace:replay abc12345 --db

# Show only cache and HTTP events
php artisan chronotrace:replay abc12345 --cache --http

# Output as JSON
php artisan chronotrace:replay abc12345 --format=json
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

## Command Combinations

### Typical Workflow

```bash
# 1. Record a problematic request
php artisan chronotrace:record /api/problematic-endpoint

# 2. List traces to find the ID
php artisan chronotrace:list

# 3. Replay the trace to debug
php artisan chronotrace:replay abc12345 --db

# 4. Clean up old traces
php artisan chronotrace:purge --days=7
```

### Monitoring Workflow

```bash
# Check recent traces
php artisan chronotrace:list --limit=10

# Analyze database performance
php artisan chronotrace:replay {trace-id} --db

# Check external API calls
php artisan chronotrace:replay {trace-id} --http

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

## Next Steps

- [Learn about event capturing](event-capturing.md)
- [Understand storage options](storage.md)
- [Check out practical examples](../examples/README.md)