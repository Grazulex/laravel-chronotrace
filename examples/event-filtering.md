# Event Filtering Examples

This guide shows you how to effectively filter and analyze different types of events captured by ChronoTrace.

## Understanding Event Types

ChronoTrace captures four main types of events:

- **📊 Database** - SQL queries, transactions, connections
- **🗄️ Cache** - Hits, misses, writes, deletions  
- **🌐 HTTP** - External API calls and responses
- **⚙️ Jobs** - Queue job processing and failures

## Basic Filtering

### View All Events

```bash
# See everything captured in a trace
php artisan chronotrace:replay abc12345-def6-7890-abcd-ef1234567890
```

This shows:
- Trace overview (URL, status, duration, memory)
- All captured events with timestamps
- Summary statistics

### Filter by Event Type

```bash
# Database events only
php artisan chronotrace:replay {trace-id} --db

# Cache events only  
php artisan chronotrace:replay {trace-id} --cache

# HTTP events only
php artisan chronotrace:replay {trace-id} --http

# Job events only
php artisan chronotrace:replay {trace-id} --jobs
```

### Combine Multiple Filters

```bash
# Database and HTTP events
php artisan chronotrace:replay {trace-id} --db --http

# Cache and job events
php artisan chronotrace:replay {trace-id} --cache --jobs

# Everything except jobs
php artisan chronotrace:replay {trace-id} --db --cache --http
```

## Database Event Analysis

### Finding N+1 Query Problems

**Record a problematic endpoint:**
```bash
php artisan chronotrace:record /api/users-with-posts
```

**Analyze database queries:**
```bash
php artisan chronotrace:replay {trace-id} --db
```

**Look for patterns like:**
```
📊 DATABASE EVENTS
  🔍 [14:30:22.123] Query: SELECT * FROM users (15ms on mysql)
  🔍 [14:30:22.145] Query: SELECT * FROM posts WHERE user_id = ? (8ms on mysql)
  🔍 [14:30:22.150] Query: SELECT * FROM posts WHERE user_id = ? (7ms on mysql)
  🔍 [14:30:22.155] Query: SELECT * FROM posts WHERE user_id = ? (6ms on mysql)
  ... (repeated for each user)
```

**Solution:** Add eager loading:
```php
// Instead of: User::all()
$users = User::with('posts')->get();
```

### Transaction Analysis

**Look for transaction patterns:**
```bash
php artisan chronotrace:replay {trace-id} --db
```

**Example output:**
```
📊 DATABASE EVENTS
  🔄 [14:30:22.100] Transaction BEGIN on mysql
  🔍 [14:30:22.110] Query: INSERT INTO orders (user_id, total) VALUES (?, ?) (5ms on mysql)
  🔍 [14:30:22.120] Query: INSERT INTO order_items (order_id, product_id) VALUES (?, ?) (3ms on mysql)
  🔍 [14:30:22.125] Query: UPDATE products SET stock = stock - ? WHERE id = ? (4ms on mysql)
  ✅ [14:30:22.130] Transaction COMMIT on mysql
```

**Identify issues:**
- Long-running transactions
- Transactions without commits
- Rollbacks indicating errors

### Slow Query Detection

**Filter for database events then look for:**
- High execution times (>100ms)
- Queries without proper indexes
- Full table scans

```
🔍 [14:30:22.123] Query: SELECT * FROM products WHERE description LIKE '%laptop%' (2,450ms on mysql)
```

This indicates a missing index on the `description` field.

## Cache Event Analysis

### Cache Hit/Miss Ratios

**Record an endpoint that should use caching:**
```bash
php artisan chronotrace:record /api/popular-products
```

**Analyze cache patterns:**
```bash
php artisan chronotrace:replay {trace-id} --cache
```

**Good caching pattern:**
```
🗄️  CACHE EVENTS
  ✅ [14:30:22.120] Cache HIT: products:popular (store: redis, size: 2,048 bytes)
  ✅ [14:30:22.125] Cache HIT: categories:main (store: redis, size: 512 bytes)
```

**Poor caching pattern:**
```
🗄️  CACHE EVENTS
  ❌ [14:30:22.120] Cache MISS: products:popular (store: redis)
  💾 [14:30:22.180] Cache WRITE: products:popular (store: redis)
  ❌ [14:30:22.185] Cache MISS: user:123:preferences (store: redis)
  ❌ [14:30:22.190] Cache MISS: user:123:permissions (store: redis)
```

### Cache Invalidation Issues

**Look for rapid cache writes and deletions:**
```
🗄️  CACHE EVENTS
  💾 [14:30:22.100] Cache WRITE: user:123:profile (store: redis)
  🗑️  [14:30:22.105] Cache FORGET: user:123:profile (store: redis)
  💾 [14:30:22.110] Cache WRITE: user:123:profile (store: redis)
```

This indicates cache invalidation happening too frequently.

### Cache Size Analysis

**Monitor cache entry sizes:**
```
✅ [14:30:22.120] Cache HIT: reports:large_dataset (store: redis, size: 15,728,640 bytes)
```

Large cache entries (>1MB) might indicate:
- Over-caching
- Inefficient data serialization
- Need for data compression

## HTTP Event Analysis

### External Service Dependencies

**Record an endpoint that calls external APIs:**
```bash
php artisan chronotrace:record /api/weather-forecast
```

**Analyze external calls:**
```bash
php artisan chronotrace:replay {trace-id} --http
```

**Example output:**
```
🌐 HTTP EVENTS
  📤 [14:30:22.200] HTTP Request: GET https://api.weather.com/forecast (body: 0 bytes)
  📥 [14:30:22.450] HTTP Response: GET https://api.weather.com/forecast → 200 (2,048 bytes)
  📤 [14:30:22.500] HTTP Request: POST https://analytics.internal.com/track (body: 256 bytes)
  📥 [14:30:22.520] HTTP Response: POST https://analytics.internal.com/track → 200 (64 bytes)
```

### Performance Analysis

**Identify slow external services:**
```
📤 [14:30:22.200] HTTP Request: GET https://slow-api.example.com/data
📥 [14:30:25.200] HTTP Response: GET https://slow-api.example.com/data → 200
```

The 3-second gap indicates a slow external service.

### Error Detection

**Find failing external calls:**
```
📤 [14:30:22.200] HTTP Request: GET https://api.unstable.com/data
❌ [14:30:22.210] HTTP Connection Failed: GET https://api.unstable.com/data
```

### Service Dependency Mapping

**Use HTTP events to understand service dependencies:**

```bash
# Record multiple related endpoints
php artisan chronotrace:record /api/user-dashboard
php artisan chronotrace:record /api/user-settings
php artisan chronotrace:record /api/user-notifications

# Analyze each for external dependencies
php artisan chronotrace:replay {trace-id-1} --http
php artisan chronotrace:replay {trace-id-2} --http  
php artisan chronotrace:replay {trace-id-3} --http
```

This helps you understand which external services your application depends on.

## Queue Job Analysis

### Job Processing Patterns

**Record an endpoint that dispatches jobs:**
```bash
php artisan chronotrace:record /orders/confirmation --method=POST --data='{"order_id": 123}'
```

**Analyze job execution:**
```bash
php artisan chronotrace:replay {trace-id} --jobs
```

**Successful job pattern:**
```
⚙️  JOB EVENTS
  🔄 [14:30:22.300] Job STARTED: ProcessOrderPayment (queue: payments, connection: redis) - attempt #1
  ✅ [14:30:22.450] Job COMPLETED: ProcessOrderPayment (queue: payments, connection: redis)
  🔄 [14:30:22.500] Job STARTED: SendOrderConfirmation (queue: emails, connection: redis) - attempt #1
  ✅ [14:30:22.650] Job COMPLETED: SendOrderConfirmation (queue: emails, connection: redis)
```

### Job Failure Analysis

**Identify failing jobs:**
```
⚙️  JOB EVENTS
  🔄 [14:30:22.300] Job STARTED: SendWelcomeEmail (queue: emails, connection: redis) - attempt #1
  ❌ [14:30:22.350] Job FAILED: SendWelcomeEmail (queue: emails, connection: redis) - SMTP timeout
  🔄 [14:30:27.300] Job STARTED: SendWelcomeEmail (queue: emails, connection: redis) - attempt #2
  ❌ [14:30:27.350] Job FAILED: SendWelcomeEmail (queue: emails, connection: redis) - SMTP timeout
```

This shows a job failing and being retried.

### Queue Performance

**Monitor job processing times:**
```
🔄 [14:30:22.000] Job STARTED: GenerateReport (queue: reports, connection: redis)
✅ [14:30:27.000] Job COMPLETED: GenerateReport (queue: reports, connection: redis)
```

A 5-second job might indicate performance issues.

## Combined Analysis Workflows

### E-commerce Checkout Flow

**Record the complete checkout process:**
```bash
php artisan chronotrace:record /checkout/process \
  --method=POST \
  --data='{"cart_id": 123, "payment_method": "stripe"}'
```

**Step-by-step analysis:**

1. **Database operations:**
```bash
php artisan chronotrace:replay {trace-id} --db
```
Look for:
- Order creation queries
- Inventory updates
- Payment record inserts

2. **Cache operations:**
```bash
php artisan chronotrace:replay {trace-id} --cache
```
Look for:
- Cart data retrieval
- Product cache hits/misses
- User session updates

3. **External services:**
```bash
php artisan chronotrace:replay {trace-id} --http
```
Look for:
- Payment gateway calls
- Shipping API requests
- Tax calculation services

4. **Background jobs:**
```bash
php artisan chronotrace:replay {trace-id} --jobs
```
Look for:
- Email notifications
- Inventory updates
- Analytics events

### API Performance Audit

**Record multiple API endpoints:**
```bash
php artisan chronotrace:record /api/v1/users
php artisan chronotrace:record /api/v1/products
php artisan chronotrace:record /api/v1/orders
```

**Create a performance report:**

```bash
#!/bin/bash
# performance-audit.sh

echo "=== API Performance Audit ==="

for trace_id in $(php artisan chronotrace:list --limit=10 | tail -n +4 | head -n -1 | awk '{print $2}'); do
    echo "Analyzing trace: $trace_id"
    
    echo "Database queries:"
    php artisan chronotrace:replay $trace_id --db | grep "Query:" | wc -l
    
    echo "Cache operations:"
    php artisan chronotrace:replay $trace_id --cache | grep "Cache" | wc -l
    
    echo "External calls:"
    php artisan chronotrace:replay $trace_id --http | grep "HTTP" | wc -l
    
    echo "---"
done
```

### Debug Production Issues

**When a production issue occurs:**

1. **Find the problematic trace:**
```bash
php artisan chronotrace:list --limit=50
```

2. **Get overview:**
```bash
php artisan chronotrace:replay {trace-id}
```

3. **Focus on specific areas:**
```bash
# Check for database issues
php artisan chronotrace:replay {trace-id} --db

# Check external service failures  
php artisan chronotrace:replay {trace-id} --http

# Check background job failures
php artisan chronotrace:replay {trace-id} --jobs
```

## Advanced Filtering Techniques

### Using JSON Output for Processing

```bash
# Export trace data as JSON for custom analysis
php artisan chronotrace:replay {trace-id} --format=json > trace.json

# Process with jq
cat trace.json | jq '.database[] | select(.time > 100)' # Slow queries
cat trace.json | jq '.http[] | select(.status >= 400)'  # Failed HTTP calls
```

### Combining with System Tools

```bash
# Find traces with many database queries
php artisan chronotrace:list | while read line; do
    trace_id=$(echo $line | awk '{print $2}')
    query_count=$(php artisan chronotrace:replay $trace_id --db | grep "Query:" | wc -l)
    if [ $query_count -gt 50 ]; then
        echo "Trace $trace_id has $query_count queries"
    fi
done
```

### Performance Comparison

```bash
# Compare before/after optimization
echo "Before optimization:"
php artisan chronotrace:replay {old-trace-id} --db | grep "Query:" | wc -l

echo "After optimization:"  
php artisan chronotrace:replay {new-trace-id} --db | grep "Query:" | wc -l
```

## Best Practices for Event Analysis

1. **Start Broad, Then Focus**: Always look at the full trace first, then filter down

2. **Look for Patterns**: Repeated events often indicate optimization opportunities

3. **Monitor Timing**: Pay attention to timestamps to understand event sequence

4. **Compare Traces**: Compare similar requests to identify anomalies

5. **Document Findings**: Keep notes on what you discover for future reference

6. **Automate Analysis**: Create scripts for common analysis patterns

## Next Steps

- [Learn about production monitoring](production-monitoring.md)
- [Explore custom storage setups](custom-storage.md)
- [Set up development workflows](development-workflow.md)