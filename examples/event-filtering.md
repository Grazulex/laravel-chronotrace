# Event Filtering and Analysis Examples

This comprehensive guide shows you how to effectively filter, analyze, and understand different types of events captured by ChronoTrace.

## Understanding Event Types

ChronoTrace captures comprehensive event data across multiple categories:

- **📊 Database Events** - SQL queries, transactions, connections, bindings
- **🗄️ Cache Events** - Hits, misses, writes, deletions, store operations  
- **🌐 HTTP Events** - External API calls, responses, failures, timeouts
- **⚙️ Job Events** - Queue job processing, failures, completions, dispatching
- **📝 Laravel Events** - Custom events, model events, lifecycle events (optional)
- **📧 Mail Events** - Email sending, queuing, failures
- **🔔 Notification Events** - Push notifications, SMS, webhooks

## Basic Event Filtering

### View All Events

```bash
# See comprehensive trace information
php artisan chronotrace:replay abc12345-def6-7890-abcd-ef1234567890

# Detailed view with context, headers, and content
php artisan chronotrace:replay abc12345 --detailed

# Maximum information including Laravel context
php artisan chronotrace:replay abc12345 --detailed --context --headers --content --bindings
```

### Filter by Single Event Type

```bash
# Database events only
php artisan chronotrace:replay abc12345 --db

# Cache operations only  
php artisan chronotrace:replay abc12345 --cache

# External HTTP requests only
php artisan chronotrace:replay abc12345 --http

# Queue job events only
php artisan chronotrace:replay abc12345 --jobs
```

### Combine Multiple Filters

```bash
# Database and cache events
php artisan chronotrace:replay abc12345 --db --cache

# HTTP and job events
php artisan chronotrace:replay abc12345 --http --jobs

# Database with SQL bindings
php artisan chronotrace:replay abc12345 --db --bindings

# Everything except jobs
php artisan chronotrace:replay abc12345 --db --cache --http
```

## Advanced Database Analysis

### Finding Performance Issues

```bash
# Show all database queries with execution times
php artisan chronotrace:replay abc12345 --db

# Show SQL bindings for debugging parameters
php artisan chronotrace:replay abc12345 --db --bindings

# Find slow queries (>100ms)
php artisan chronotrace:replay abc12345 --db | grep -E "[0-9]{3,}ms"

# Identify potential N+1 queries
php artisan chronotrace:replay abc12345 --db | grep -E "SELECT.*WHERE.*IN"
```

**Example Output:**
```
📊 DATABASE EVENTS
  🔍 [14:30:22.123] Query: SELECT * FROM users WHERE active = ? (15ms on mysql)
      Bindings: [1]
  🔍 [14:30:22.145] Query: SELECT * FROM roles WHERE user_id IN (?, ?, ?) (8ms on mysql)
      Bindings: [1, 2, 3]
  ⚠️  [14:30:22.200] Query: SELECT * FROM posts WHERE user_id = ? (250ms on mysql)
      Bindings: [1]
```

### Transaction Analysis

```bash
# Show transaction events
php artisan chronotrace:replay abc12345 --db | grep -E "(Transaction|COMMIT|ROLLBACK)"

# Find failed transactions
php artisan chronotrace:replay abc12345 --db | grep "ROLLBACK"
```

**Example Analysis Script:**
```bash
#!/bin/bash
# analyze-database-performance.sh

TRACE_ID=$1
if [ -z "$TRACE_ID" ]; then
    echo "Usage: $0 <trace-id>"
    exit 1
fi

echo "🔍 Database Performance Analysis for: $TRACE_ID"
echo "=" | head -c 50; echo

# Count queries
TOTAL_QUERIES=$(php artisan chronotrace:replay $TRACE_ID --db | grep -c "Query:")
echo "📊 Total Queries: $TOTAL_QUERIES"

# Find slow queries
SLOW_QUERIES=$(php artisan chronotrace:replay $TRACE_ID --db | grep -c "[0-9]{3,}ms")
echo "🐌 Slow Queries (>100ms): $SLOW_QUERIES"

if [ $SLOW_QUERIES -gt 0 ]; then
    echo ""
    echo "🔍 Slowest Queries:"
    php artisan chronotrace:replay $TRACE_ID --db --bindings | grep -E "[0-9]{3,}ms" | head -3
fi

# Check for N+1 patterns
N_PLUS_ONE=$(php artisan chronotrace:replay $TRACE_ID --db | grep -c "WHERE.*IN")
if [ $N_PLUS_ONE -gt 2 ]; then
    echo ""
    echo "⚠️  Potential N+1 Queries Detected: $N_PLUS_ONE"
    php artisan chronotrace:replay $TRACE_ID --db | grep "WHERE.*IN" | head -2
fi

# Transaction analysis
TRANSACTIONS=$(php artisan chronotrace:replay $TRACE_ID --db | grep -c "Transaction")
ROLLBACKS=$(php artisan chronotrace:replay $TRACE_ID --db | grep -c "ROLLBACK")

if [ $TRANSACTIONS -gt 0 ]; then
    echo ""
    echo "🔄 Transactions: $TRANSACTIONS"
    echo "↩️  Rollbacks: $ROLLBACKS"
fi
```

## Cache Event Analysis

### Understanding Cache Patterns

```bash
# View all cache operations
php artisan chronotrace:replay abc12345 --cache

# Focus on cache misses (optimization opportunities)
php artisan chronotrace:replay abc12345 --cache | grep "MISS"

# Check cache hit ratio
php artisan chronotrace:replay abc12345 --cache | grep -E "(HIT|MISS)" | sort | uniq -c
```

**Example Output:**
```
🗄️ CACHE EVENTS
  ❌ [14:30:22.120] Cache MISS: users:list (store: redis)
  💾 [14:30:22.150] Cache WRITE: users:list (store: redis, ttl: 3600)
  ✅ [14:30:22.200] Cache HIT: config:app (store: redis)
  🗑️ [14:30:22.250] Cache DELETE: users:1:profile (store: redis)
```

### Cache Optimization Script

```bash
#!/bin/bash
# analyze-cache-efficiency.sh

TRACE_ID=$1
echo "🗄️ Cache Analysis for: $TRACE_ID"

# Count cache operations
HITS=$(php artisan chronotrace:replay $TRACE_ID --cache | grep -c "HIT")
MISSES=$(php artisan chronotrace:replay $TRACE_ID --cache | grep -c "MISS")
WRITES=$(php artisan chronotrace:replay $TRACE_ID --cache | grep -c "WRITE")
DELETES=$(php artisan chronotrace:replay $TRACE_ID --cache | grep -c "DELETE")

echo "📊 Cache Statistics:"
echo "  ✅ Hits: $HITS"
echo "  ❌ Misses: $MISSES"
echo "  💾 Writes: $WRITES"
echo "  🗑️ Deletes: $DELETES"

if [ $MISSES -gt 0 ] && [ $HITS -gt 0 ]; then
    HIT_RATIO=$(echo "scale=2; $HITS * 100 / ($HITS + $MISSES)" | bc)
    echo "  📈 Hit Ratio: ${HIT_RATIO}%"
    
    if [ $(echo "$HIT_RATIO < 80" | bc) -eq 1 ]; then
        echo "⚠️  Low cache hit ratio - consider cache optimization"
    fi
fi

if [ $MISSES -gt $HITS ]; then
    echo ""
    echo "🔍 Most Missed Cache Keys:"
    php artisan chronotrace:replay $TRACE_ID --cache | grep "MISS" | head -5
fi
```

## HTTP Event Analysis

### External Service Monitoring

```bash
# View all external HTTP requests
php artisan chronotrace:replay abc12345 --http

# Check for failed requests
php artisan chronotrace:replay abc12345 --http | grep -E "(Failed|[45][0-9][0-9])"

# Monitor response times
php artisan chronotrace:replay abc12345 --http | grep -E "[0-9]{2,}ms"
```

**Example Output:**
```
🌐 HTTP EVENTS
  📤 [14:30:22.200] HTTP Request: GET https://api.external.com/users/123
      Headers: {"Authorization": "Bearer ***", "Accept": "application/json"}
  📥 [14:30:22.450] HTTP Response: GET https://api.external.com/users/123 → 200 (1,234 bytes, 250ms)
  📤 [14:30:22.500] HTTP Request: POST https://webhook.service.com/notify
  ❌ [14:30:22.800] HTTP Response: POST https://webhook.service.com/notify → 500 (Connection timeout)
```

### External Service Health Check

```bash
#!/bin/bash
# check-external-services.sh

TRACE_ID=$1
echo "🌐 External Services Health Check: $TRACE_ID"

# Count HTTP requests and failures
TOTAL_REQUESTS=$(php artisan chronotrace:replay $TRACE_ID --http | grep -c "HTTP Request:")
FAILURES=$(php artisan chronotrace:replay $TRACE_ID --http | grep -c -E "(Failed|[45][0-9][0-9])")
TIMEOUTS=$(php artisan chronotrace:replay $TRACE_ID --http | grep -c "timeout")

echo "📊 HTTP Statistics:"
echo "  📤 Total Requests: $TOTAL_REQUESTS"
echo "  ❌ Failures: $FAILURES"
echo "  ⏰ Timeouts: $TIMEOUTS"

if [ $TOTAL_REQUESTS -gt 0 ]; then
    SUCCESS_RATE=$(echo "scale=2; ($TOTAL_REQUESTS - $FAILURES) * 100 / $TOTAL_REQUESTS" | bc)
    echo "  ✅ Success Rate: ${SUCCESS_RATE}%"
fi

if [ $FAILURES -gt 0 ]; then
    echo ""
    echo "❌ Failed Requests:"
    php artisan chronotrace:replay $TRACE_ID --http | grep -E "(Failed|[45][0-9][0-9])" | head -3
fi

# Show slow requests (>1000ms)
SLOW_REQUESTS=$(php artisan chronotrace:replay $TRACE_ID --http | grep -c "[0-9]{4,}ms")
if [ $SLOW_REQUESTS -gt 0 ]; then
    echo ""
    echo "🐌 Slow Requests (>1s):"
    php artisan chronotrace:replay $TRACE_ID --http | grep "[0-9]{4,}ms" | head -3
fi
```

## Queue Job Analysis

### Job Performance and Reliability

```bash
# View all job events
php artisan chronotrace:replay abc12345 --jobs

# Check for failed jobs
php artisan chronotrace:replay abc12345 --jobs | grep "Failed"

# Monitor job processing times
php artisan chronotrace:replay abc12345 --jobs | grep -E "[0-9]{3,}ms"
```

**Example Output:**
```
⚙️ JOB EVENTS
  🔄 [14:30:22.300] Job STARTED: ProcessUserRegistration (queue: default)
  ✅ [14:30:22.450] Job COMPLETED: ProcessUserRegistration (150ms)
  🔄 [14:30:22.500] Job STARTED: SendWelcomeEmail (queue: emails)
  ❌ [14:30:22.600] Job FAILED: SendWelcomeEmail (Connection refused)
  🔄 [14:30:22.700] Job RETRY: SendWelcomeEmail (attempt 2/3)
```

### Job Health Monitoring

```bash
#!/bin/bash
# analyze-job-health.sh

TRACE_ID=$1
echo "⚙️ Queue Jobs Health Analysis: $TRACE_ID"

# Count job events
STARTED=$(php artisan chronotrace:replay $TRACE_ID --jobs | grep -c "STARTED")
COMPLETED=$(php artisan chronotrace:replay $TRACE_ID --jobs | grep -c "COMPLETED")
FAILED=$(php artisan chronotrace:replay $TRACE_ID --jobs | grep -c "FAILED")
RETRIES=$(php artisan chronotrace:replay $TRACE_ID --jobs | grep -c "RETRY")

echo "📊 Job Statistics:"
echo "  🔄 Started: $STARTED"
echo "  ✅ Completed: $COMPLETED"
echo "  ❌ Failed: $FAILED"
echo "  🔄 Retries: $RETRIES"

if [ $STARTED -gt 0 ]; then
    SUCCESS_RATE=$(echo "scale=2; $COMPLETED * 100 / $STARTED" | bc)
    echo "  📈 Success Rate: ${SUCCESS_RATE}%"
fi

if [ $FAILED -gt 0 ]; then
    echo ""
    echo "❌ Failed Jobs:"
    php artisan chronotrace:replay $TRACE_ID --jobs | grep "FAILED" | head -3
fi

# Check for slow jobs (>5 seconds)
SLOW_JOBS=$(php artisan chronotrace:replay $TRACE_ID --jobs | grep -c "[0-9]{4,}ms")
if [ $SLOW_JOBS -gt 0 ]; then
    echo ""
    echo "🐌 Slow Jobs (>5s):"
    php artisan chronotrace:replay $TRACE_ID --jobs | grep "[0-9]{4,}ms" | head -3
fi
```

## Output Format Options

### JSON Output for Programmatic Analysis

```bash
# Export trace as JSON
php artisan chronotrace:replay abc12345 --format=json > trace.json

# Process with jq
php artisan chronotrace:replay abc12345 --format=json | jq '.database[] | select(.duration > 100)'

# Extract database queries only
php artisan chronotrace:replay abc12345 --format=json | jq '.database[].sql'

# Get HTTP response codes
php artisan chronotrace:replay abc12345 --format=json | jq '.http[].status'
```

### Raw Output for Custom Processing

```bash
# Raw format for custom parsers
php artisan chronotrace:replay abc12345 --format=raw

# Pipe to custom analysis scripts
php artisan chronotrace:replay abc12345 --format=raw | ./custom-analyzer.py
```

## Advanced Filtering Scenarios

### E-commerce Checkout Analysis

```bash
# Record checkout process
php artisan chronotrace:record /checkout/process \
  --method=POST \
  --data='{"cart_id": "123", "payment_method": "stripe"}'

# Analyze payment processing
TRACE_ID=$(php artisan chronotrace:list --limit=1 --full-id | grep "│" | head -1 | awk '{print $2}')

echo "🛒 E-commerce Checkout Analysis"
echo "================================"

# Check database performance (order creation, inventory updates)
echo "📊 Database Operations:"
php artisan chronotrace:replay $TRACE_ID --db --bindings | grep -E "(orders|inventory|payments)"

# Monitor payment gateway calls
echo ""
echo "💳 Payment Gateway Integration:"
php artisan chronotrace:replay $TRACE_ID --http | grep -E "(stripe|paypal|payment)"

# Check background job processing (email, inventory updates)
echo ""
echo "⚙️ Background Processing:"
php artisan chronotrace:replay $TRACE_ID --jobs
```

### API Performance Audit

```bash
#!/bin/bash
# api-performance-audit.sh

API_ENDPOINTS=(
    "/api/v1/users"
    "/api/v1/orders"
    "/api/v1/products"
    "/api/v1/analytics"
)

echo "🔍 API Performance Audit"
echo "========================"

for endpoint in "${API_ENDPOINTS[@]}"; do
    echo "Testing: $endpoint"
    
    # Record endpoint
    php artisan chronotrace:record "$endpoint"
    TRACE_ID=$(php artisan chronotrace:list --limit=1 --full-id | grep "│" | head -1 | awk '{print $2}')
    
    # Get performance metrics
    DURATION=$(php artisan chronotrace:replay $TRACE_ID | grep "Duration:" | awk '{print $3}')
    MEMORY=$(php artisan chronotrace:replay $TRACE_ID | grep "Memory:" | awk '{print $3}')
    
    echo "  ⏱️  Duration: $DURATION"
    echo "  💾 Memory: $MEMORY"
    
    # Count database queries
    DB_QUERIES=$(php artisan chronotrace:replay $TRACE_ID --db | grep -c "Query:")
    echo "  📊 DB Queries: $DB_QUERIES"
    
    # Check for external calls
    HTTP_CALLS=$(php artisan chronotrace:replay $TRACE_ID --http | grep -c "HTTP Request:")
    echo "  🌐 HTTP Calls: $HTTP_CALLS"
    
    echo ""
done
```

### Multi-Step Workflow Analysis

```bash
#!/bin/bash
# workflow-analysis.sh - Analyze a complete user workflow

echo "👤 User Registration Workflow Analysis"
echo "======================================"

# Step 1: Registration form
php artisan chronotrace:record /register --method=GET
REG_FORM_TRACE=$(php artisan chronotrace:list --limit=1 --full-id | grep "│" | head -1 | awk '{print $2}')

# Step 2: Registration submission
php artisan chronotrace:record /register \
  --method=POST \
  --data='{"name":"Test User","email":"test@example.com","password":"password123"}'
REG_SUBMIT_TRACE=$(php artisan chronotrace:list --limit=1 --full-id | grep "│" | head -1 | awk '{print $2}')

# Step 3: Email verification
php artisan chronotrace:record /email/verify/123 --method=GET
VERIFY_TRACE=$(php artisan chronotrace:list --limit=1 --full-id | grep "│" | head -1 | awk '{print $2}')

echo "📊 Workflow Performance Summary:"
echo "================================"

for step in "Registration Form:$REG_FORM_TRACE" "Registration Submit:$REG_SUBMIT_TRACE" "Email Verify:$VERIFY_TRACE"; do
    STEP_NAME=$(echo $step | cut -d: -f1)
    TRACE_ID=$(echo $step | cut -d: -f2)
    
    echo "🔍 $STEP_NAME"
    echo "  Trace: $TRACE_ID"
    
    # Performance metrics
    php artisan chronotrace:replay $TRACE_ID | grep -E "(Duration|Memory|Response Status)"
    
    # Database operations
    DB_COUNT=$(php artisan chronotrace:replay $TRACE_ID --db | grep -c "Query:")
    echo "  📊 Database Queries: $DB_COUNT"
    
    # Background jobs
    JOB_COUNT=$(php artisan chronotrace:replay $TRACE_ID --jobs | grep -c "STARTED")
    echo "  ⚙️ Background Jobs: $JOB_COUNT"
    
    echo ""
done
```

## Best Practices for Event Analysis

### 1. Start with Overview, Then Focus

```bash
# 1. Get the big picture first
php artisan chronotrace:replay abc12345

# 2. Focus on specific areas based on findings
php artisan chronotrace:replay abc12345 --db  # If you see performance issues
php artisan chronotrace:replay abc12345 --http  # If external services are involved
```

### 2. Use Filtering for Specific Problems

```bash
# Database performance issues
php artisan chronotrace:replay abc12345 --db --bindings | grep -E "[0-9]{3,}ms"

# Cache optimization opportunities  
php artisan chronotrace:replay abc12345 --cache | grep "MISS"

# External service reliability
php artisan chronotrace:replay abc12345 --http | grep -E "[45][0-9][0-9]"
```

### 3. Combine with System Monitoring

```bash
# Export for external analysis
php artisan chronotrace:replay abc12345 --format=json | \
  jq '{duration: .info.duration, db_queries: (.database | length), http_calls: (.http | length)}'
```

### 4. Create Reusable Analysis Scripts

Create scripts for common analysis patterns:

```bash
# comprehensive-analysis.sh
#!/bin/bash
TRACE_ID=$1

echo "🔍 Comprehensive Trace Analysis: $TRACE_ID"
echo "=========================================="

# Performance overview
echo "📊 Performance Metrics:"
php artisan chronotrace:replay $TRACE_ID | grep -E "(Duration|Memory|Response Status)"

# Database analysis
echo ""
echo "💾 Database Analysis:"
DB_COUNT=$(php artisan chronotrace:replay $TRACE_ID --db | grep -c "Query:")
SLOW_COUNT=$(php artisan chronotrace:replay $TRACE_ID --db | grep -c "[0-9]{3,}ms")
echo "  Total queries: $DB_COUNT"
echo "  Slow queries: $SLOW_COUNT"

# Cache analysis
echo ""
echo "🗄️ Cache Analysis:"
CACHE_HITS=$(php artisan chronotrace:replay $TRACE_ID --cache | grep -c "HIT")
CACHE_MISSES=$(php artisan chronotrace:replay $TRACE_ID --cache | grep -c "MISS")
echo "  Cache hits: $CACHE_HITS"
echo "  Cache misses: $CACHE_MISSES"

# HTTP analysis
echo ""
echo "🌐 HTTP Analysis:"
HTTP_COUNT=$(php artisan chronotrace:replay $TRACE_ID --http | grep -c "HTTP Request:")
HTTP_FAILURES=$(php artisan chronotrace:replay $TRACE_ID --http | grep -c -E "[45][0-9][0-9]")
echo "  HTTP requests: $HTTP_COUNT"
echo "  HTTP failures: $HTTP_FAILURES"

# Job analysis
echo ""
echo "⚙️ Job Analysis:"
JOB_COUNT=$(php artisan chronotrace:replay $TRACE_ID --jobs | grep -c "STARTED")
JOB_FAILURES=$(php artisan chronotrace:replay $TRACE_ID --jobs | grep -c "FAILED")
echo "  Jobs started: $JOB_COUNT"
echo "  Job failures: $JOB_FAILURES"
```

---

**Next Steps:**
- [Learn about custom storage options](custom-storage.md)
- [Set up production monitoring](production-monitoring.md)
- [Master development workflows](development-workflow.md)
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

7. **Generate Tests**: Create regression tests from interesting traces

## Test Generation from Filtered Analysis

### Generate Tests for Performance Issues

```bash
# Record a slow endpoint
php artisan chronotrace:record /api/slow-endpoint

# Get the trace ID
TRACE_ID=$(php artisan chronotrace:list --limit=1 --full-id | tail -1 | awk '{print $2}')

# Analyze the performance issue
php artisan chronotrace:replay $TRACE_ID --db | grep "Query.*([0-9]{3,}" # Find slow queries

# Generate a test to prevent regression
php artisan chronotrace:replay $TRACE_ID --generate-test --test-path=tests/Performance

# Review and customize the generated test
cat tests/Performance/ChronoTrace_${TRACE_ID:0:8}_Test.php
```

### Generate Tests for External API Integration

```bash
# Record API integration
php artisan chronotrace:record /api/external-integration \
  --headers='{"Authorization":"Bearer test-token"}'

TRACE_ID=$(php artisan chronotrace:list --limit=1 --full-id | tail -1 | awk '{print $2}')

# Check external dependencies
php artisan chronotrace:replay $TRACE_ID --http

# Generate integration test
php artisan chronotrace:replay $TRACE_ID --generate-test --test-path=tests/Integration

# The test will include:
# - HTTP status assertions
# - Response structure validation
# - Performance expectations
# - External API call verification
```

### Generate Tests for Database Optimization

```bash
# Record before optimization
php artisan chronotrace:record /api/unoptimized-endpoint
BEFORE_ID=$(php artisan chronotrace:list --limit=1 --full-id | tail -1 | awk '{print $2}')

# Apply optimization (eager loading, caching, etc.)
# ... make code changes ...

# Record after optimization
php artisan chronotrace:record /api/unoptimized-endpoint  
AFTER_ID=$(php artisan chronotrace:list --limit=1 --full-id | tail -1 | awk '{print $2}')

# Compare query counts
echo "Before: $(php artisan chronotrace:replay $BEFORE_ID --db | grep -c "Query:")"
echo "After: $(php artisan chronotrace:replay $AFTER_ID --db | grep -c "Query:")"

# Generate test from optimized version
php artisan chronotrace:replay $AFTER_ID --generate-test --test-path=tests/Optimized
```

## Next Steps

- [Learn about production monitoring](production-monitoring.md)
- [Explore custom storage setups](custom-storage.md)
- [Set up development workflows](development-workflow.md)