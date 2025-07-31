# Basic Usage Examples

This guide shows you how to get started with Laravel ChronoTrace through practical examples.

## Installation and Setup

### Step 1: Install the Package

```bash
composer require --dev grazulex/laravel-chronotrace
```

### Step 2: Install and Configure

```bash
# Automatic installation with middleware setup (recommended)
php artisan chronotrace:install

# Force reinstall if needed
php artisan chronotrace:install --force
```

**What this does:**
- Publishes configuration file to `config/chronotrace.php`
- Detects Laravel version and configures middleware appropriately
- For Laravel 12.x: Automatically adds middleware to `bootstrap/app.php`
- Creates storage directory with proper permissions

### Step 3: Verify Installation

```bash
# Diagnose configuration
php artisan chronotrace:diagnose

# Test middleware setup
php artisan chronotrace:test-middleware
```

### Step 4: Basic Configuration

Edit `config/chronotrace.php` or set environment variables:

```env
# Basic settings
CHRONOTRACE_ENABLED=true
CHRONOTRACE_MODE=record_on_error
CHRONOTRACE_STORAGE=local

# Performance settings
CHRONOTRACE_ASYNC_STORAGE=true
CHRONOTRACE_QUEUE_CONNECTION=database

# Event capture settings
CHRONOTRACE_CAPTURE_DATABASE=true
CHRONOTRACE_CAPTURE_CACHE=true
CHRONOTRACE_CAPTURE_HTTP=true
CHRONOTRACE_CAPTURE_JOBS=true
```

**Recording Modes:**
- `always` - Record every request (development)
- `sample` - Record percentage of requests (staging) 
- `record_on_error` - Only record on errors (production)
- `targeted` - Record specific routes/jobs only

## Recording Your First Trace

### Simple GET Request

```bash
# Record a simple API endpoint
php artisan chronotrace:record /api/users
```

This captures all events that occur during the request:
- Database queries
- Cache operations  
- HTTP requests to external services
- Queue jobs dispatched

### POST Request with Data

```bash
# Record user creation
php artisan chronotrace:record /api/users \
  --method=POST \
  --data='{"name":"John Doe","email":"john@example.com"}'
```

### Request with Authentication Headers

```bash
# Record authenticated endpoint
php artisan chronotrace:record /api/protected \
  --method=GET \
  --headers='{"Authorization":"Bearer your-token-here"}'
```

### Complex Endpoint

```bash
# Record an e-commerce checkout process
php artisan chronotrace:record /checkout/process \
  --method=POST \
  --data='{"cart_id": 123, "payment_method": "credit_card"}' \
  --headers='{"Authorization":"Bearer token","Content-Type":"application/json"}'
```

## Viewing Traces

### List All Traces

```bash
php artisan chronotrace:list
```

Output:
```
┌─────────────┬─────────────┬─────────────────────┐
│ Trace ID    │ Size        │ Created At          │
├─────────────┼─────────────┼─────────────────────┤
│ a1b2c3d4... │ 15,234 bytes│ 2024-01-15 14:30:22 │
│ e5f6g7h8... │ 8,912 bytes │ 2024-01-15 13:45:18 │
└─────────────┴─────────────┴─────────────────────┘
```

### List Recent Traces

```bash
# Show only the 5 most recent traces
php artisan chronotrace:list --limit=5

# Show full trace IDs for easy copying
php artisan chronotrace:list --full-id

# Combine options
php artisan chronotrace:list --limit=10 --full-id
```

## Replaying Traces

### View All Events

```bash
# Replace with your actual trace ID
php artisan chronotrace:replay a1b2c3d4-e5f6-7890-abcd-ef1234567890
```

This shows comprehensive information:

```
=== TRACE INFORMATION ===
🆔 Trace ID: a1b2c3d4-e5f6-7890-abcd-ef1234567890
🕒 Timestamp: 2024-01-15 14:30:22
🌍 Environment: local
🔗 Request URL: http://localhost:8000/api/users
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
  📥 [14:30:22.230] HTTP Response: GET https://api.external.com/validation → 200

📈 EVENTS SUMMARY
  📊 Database events: 2
  🗄️  Cache events: 2
  🌐 HTTP events: 2
  ⚙️  Job events: 0
  📝 Total events: 6
```

### Advanced Filtering Options

```bash
# View detailed information with context, headers, and content
php artisan chronotrace:replay a1b2c3d4 --detailed

# Show SQL query bindings for debugging
php artisan chronotrace:replay a1b2c3d4 --db --bindings

# Show Laravel context (versions, config, environment)
php artisan chronotrace:replay a1b2c3d4 --context

# Show request and response headers
php artisan chronotrace:replay a1b2c3d4 --headers

# Show response content
php artisan chronotrace:replay a1b2c3d4 --content

# Compact output (minimal information)
php artisan chronotrace:replay a1b2c3d4 --compact

# Output as JSON for programmatic processing
php artisan chronotrace:replay a1b2c3d4 --format=json

# Output as raw data
php artisan chronotrace:replay a1b2c3d4 --format=raw
```

### Combine Multiple Filters

```bash
# View database and cache events only
php artisan chronotrace:replay a1b2c3d4 --db --cache
```

### Generate Tests from Traces

```bash
# Generate a Pest test from a trace
php artisan chronotrace:replay a1b2c3d4 --generate-test

# Generate test in specific directory
php artisan chronotrace:replay a1b2c3d4 --generate-test --test-path=tests/Integration

# View the generated test
cat tests/Generated/ChronoTrace_a1b2c3d4_Test.php

# Run the generated test
./vendor/bin/pest tests/Generated/ChronoTrace_a1b2c3d4_Test.php
```

## Practical Examples

### Example 1: Debugging Slow API Response

```bash
# 1. Record the slow endpoint
php artisan chronotrace:record /api/dashboard/stats

# 2. Get the trace ID
php artisan chronotrace:list --limit=1

# 3. Analyze database queries for N+1 problems
php artisan chronotrace:replay {trace-id} --db
```

Look for:
- Repeated similar queries
- Long execution times
- Too many queries for simple operations

### Example 2: Monitoring External API Calls

```bash
# 1. Record an endpoint that calls external services
php artisan chronotrace:record /api/weather/forecast

# 2. View HTTP events to see external dependencies
php artisan chronotrace:replay {trace-id} --http
```

This helps you:
- Track external API response times
- Monitor API failures
- Understand service dependencies

### Example 3: Cache Performance Analysis

```bash
# 1. Record a data-heavy endpoint
php artisan chronotrace:record /api/products/search?q=laptop

# 2. Analyze cache effectiveness
php artisan chronotrace:replay {trace-id} --cache
```

Look for:
- Cache miss ratios
- Opportunities for additional caching
- Cache key patterns

### Example 4: Queue Job Monitoring

```bash
# 1. Record an endpoint that dispatches jobs
php artisan chronotrace:record /orders/confirmation \
  --method=POST \
  --data='{"order_id": 12345}'

# 2. View queue job processing
php artisan chronotrace:replay {trace-id} --jobs
```

This shows:
- Which jobs were dispatched
- Job processing status
- Job failures and retries

### Example 5: Test Generation Workflow

```bash
# 1. Record a critical business workflow
php artisan chronotrace:record /api/orders/complete \
  --method=POST \
  --data='{"order_id": 123, "confirm": true}' \
  --headers='{"Authorization":"Bearer token"}'

# 2. Verify the trace captured everything
php artisan chronotrace:replay {trace-id}

# 3. Generate a regression test
php artisan chronotrace:replay {trace-id} --generate-test --test-path=tests/Business

# 4. Review and run the generated test
cat tests/Business/ChronoTrace_{trace-id}_Test.php
./vendor/bin/pest tests/Business/ChronoTrace_{trace-id}_Test.php

# 5. Commit the test to prevent regressions
git add tests/Business/ChronoTrace_{trace-id}_Test.php
git commit -m "Add regression test for order completion workflow"
```

This creates comprehensive tests that validate:
- HTTP status codes
- Response structure
- Performance expectations
- Database interactions
- Cache behavior

## Common Workflows

### Daily Development Workflow

```bash
# Morning: Validate setup and check overnight traces
php artisan chronotrace:diagnose
php artisan chronotrace:list --limit=10

# During development: Record new features
php artisan chronotrace:record /api/new-feature

# Generate tests for new features
php artisan chronotrace:replay {trace-id} --generate-test --test-path=tests/Feature

# Debug issues: Replay problematic traces
php artisan chronotrace:replay {trace-id} --db

# End of day: Clean up old traces
php artisan chronotrace:purge --days=7
```

### Bug Investigation Workflow

```bash
# 1. Reproduce the bug with headers if needed
php artisan chronotrace:record /problematic-endpoint \
  --method=POST \
  --data='{"reproduce": "bug"}' \
  --headers='{"Authorization":"Bearer token"}'

# 2. Identify the trace with full ID
php artisan chronotrace:list --limit=5 --full-id

# 3. Analyze step by step
php artisan chronotrace:replay {trace-id}           # Overview
php artisan chronotrace:replay {trace-id} --db      # Database issues
php artisan chronotrace:replay {trace-id} --http    # External services
php artisan chronotrace:replay {trace-id} --cache   # Cache problems

# 4. Generate test to prevent regression
php artisan chronotrace:replay {trace-id} --generate-test --test-path=tests/Bugs

# 5. Focus on specific areas based on findings
```

### Performance Optimization Workflow

```bash
# 1. Record baseline performance
php artisan chronotrace:record /performance-critical-endpoint

# 2. Note the trace ID and performance metrics
php artisan chronotrace:replay {trace-id}

# 3. Make optimizations (add caching, optimize queries, etc.)

# 4. Record again and compare
php artisan chronotrace:record /performance-critical-endpoint

# 5. Compare the two traces
php artisan chronotrace:replay {old-trace-id} --db
php artisan chronotrace:replay {new-trace-id} --db
```

## Maintenance

### Regular Cleanup

```bash
# Clean up traces older than 7 days
php artisan chronotrace:purge --days=7

# Force cleanup without confirmation
php artisan chronotrace:purge --days=7 --confirm
```

### Check Storage Usage

```bash
# Check how much space traces are using
du -sh storage/chronotrace/

# List trace file sizes
php artisan chronotrace:list --limit=50
```

## Next Steps

Once you're comfortable with basic usage:

- [Configure different recording modes](configuration-examples.md)
- [Set up production monitoring](production-monitoring.md)
- [Learn about custom storage options](custom-storage.md)
- [Explore advanced event filtering](event-filtering.md)

## Troubleshooting

### Installation Issues

```bash
# If installation fails, try diagnosing first
php artisan chronotrace:diagnose

# Test middleware setup
php artisan chronotrace:test-middleware

# Force reinstall
php artisan chronotrace:install --force
```

### No Traces Recorded

Check your configuration:

```bash
# Verify ChronoTrace is enabled
php artisan config:show chronotrace.enabled

# Check recording mode
php artisan config:show chronotrace.mode

# Run full diagnosis
php artisan chronotrace:diagnose
```

### Permission Errors

```bash
# Fix storage permissions
chmod -R 755 storage/chronotrace/
chown -R www-data:www-data storage/chronotrace/
```

### Empty Trace Replays

This usually means no events were captured. Check:

- Are the event listeners enabled in config?
- Is the request actually executing the code you expect?
- Are there any errors in the Laravel logs?

---

**Next:** [Configuration Examples](configuration-examples.md)