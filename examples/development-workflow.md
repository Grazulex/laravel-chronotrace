# Development Workflow Examples

This guide shows how to integrate ChronoTrace into your daily development workflow for debugging, testing, and performance optimization.

## Development Environment Setup

### Local Development Configuration

```env
# .env.local
CHRONOTRACE_ENABLED=true
CHRONOTRACE_MODE=always
CHRONOTRACE_STORAGE=local
CHRONOTRACE_RETENTION_DAYS=7
CHRONOTRACE_DEBUG=true

# Capture everything for debugging
CHRONOTRACE_CAPTURE_DATABASE=true
CHRONOTRACE_CAPTURE_CACHE=true
CHRONOTRACE_CAPTURE_HTTP=true
CHRONOTRACE_CAPTURE_JOBS=true
CHRONOTRACE_CAPTURE_EVENTS=true

# Synchronous storage for immediate access
CHRONOTRACE_ASYNC_STORAGE=false
```

### Development-Specific Configuration

```php
// config/chronotrace.php - Development overrides
if (app()->environment('local')) {
    return array_merge($config, [
        'mode' => 'always',
        'debug' => true,
        'retention_days' => 3,
        'capture' => [
            'database' => true,
            'cache' => true,
            'http' => true,
            'jobs' => true,
            'events' => true,
        ],
        'compression' => ['enabled' => false], // Faster writes
        'async_storage' => false,              // Immediate availability
    ]);
}
```

## Daily Development Workflows

### Bug Investigation Workflow

**1. Reproduce the Bug:**
```bash
# Start with manual recording
php artisan chronotrace:record /problematic-endpoint \
  --method=POST \
  --data='{"reproduce": "the-bug"}'
```

**2. Get the Trace ID:**
```bash
# List recent traces
php artisan chronotrace:list --limit=5
```

**3. Analyze the Issue:**
```bash
# Get overview
php artisan chronotrace:replay {trace-id}

# Focus on specific areas
php artisan chronotrace:replay {trace-id} --db    # Database issues
php artisan chronotrace:replay {trace-id} --http  # External services
php artisan chronotrace:replay {trace-id} --jobs  # Background processing
```

**4. Document Findings:**
```bash
# Save trace for reference
php artisan chronotrace:replay {trace-id} > bug-analysis-$(date +%Y%m%d).txt
```

### Feature Development Workflow

**1. Baseline Recording:**
```bash
# Record before making changes
php artisan chronotrace:record /new-feature-endpoint
BASELINE_TRACE=$(php artisan chronotrace:list --limit=1 | tail -1 | awk '{print $2}')
```

**2. Develop Feature:**
```bash
# Make your code changes
git add .
git commit -m "Implement new feature"
```

**3. Performance Comparison:**
```bash
# Record after changes
php artisan chronotrace:record /new-feature-endpoint
NEW_TRACE=$(php artisan chronotrace:list --limit=1 | tail -1 | awk '{print $2}')

# Compare performance
echo "Before:"
php artisan chronotrace:replay $BASELINE_TRACE | grep "Duration:\|Memory:"

echo "After:"
php artisan chronotrace:replay $NEW_TRACE | grep "Duration:\|Memory:"
```

**4. Query Analysis:**
```bash
# Check for N+1 queries
php artisan chronotrace:replay $NEW_TRACE --db | grep -c "Query:"
php artisan chronotrace:replay $BASELINE_TRACE --db | grep -c "Query:"
```

### Code Review Preparation

**Create Performance Report:**
```bash
#!/bin/bash
# scripts/pre-review-check.sh

echo "=== Pre-Review Performance Check ==="

# Record key endpoints
ENDPOINTS=("/api/users" "/api/products" "/dashboard")

for endpoint in "${ENDPOINTS[@]}"; do
    echo "Testing $endpoint..."
    php artisan chronotrace:record "$endpoint"
    
    TRACE_ID=$(php artisan chronotrace:list --limit=1 | tail -1 | awk '{print $2}')
    
    echo "Trace ID: $TRACE_ID"
    echo "Queries: $(php artisan chronotrace:replay $TRACE_ID --db | grep -c 'Query:')"
    echo "Duration: $(php artisan chronotrace:replay $TRACE_ID | grep 'Duration:' | awk '{print $3}')"
    echo "---"
done
```

## Testing Integration

### Unit Test with ChronoTrace

```php
<?php
// tests/Feature/UserApiTest.php

use Grazulex\LaravelChronotrace\Services\TraceRecorder;

class UserApiTest extends TestCase
{
    public function test_user_list_performance()
    {
        $recorder = app(TraceRecorder::class);
        
        // Start recording
        $recorder->startRecording();
        
        // Make request
        $response = $this->getJson('/api/users');
        
        // Stop recording and get trace
        $traceId = $recorder->stopRecording();
        $trace = app(TraceStorage::class)->retrieve($traceId);
        
        // Assertions
        $response->assertStatus(200);
        $this->assertLessThan(1000, $trace->response->duration); // < 1 second
        $this->assertLessThan(10, count($trace->database));      // < 10 queries
    }
    
    public function test_no_n_plus_one_in_user_posts()
    {
        $recorder = app(TraceRecorder::class);
        
        // Create test data
        $users = User::factory(5)->create();
        foreach ($users as $user) {
            Post::factory(3)->create(['user_id' => $user->id]);
        }
        
        $recorder->startRecording();
        $response = $this->getJson('/api/users-with-posts');
        $traceId = $recorder->stopRecording();
        
        $trace = app(TraceStorage::class)->retrieve($traceId);
        
        // Should only have 2 queries: users + posts (with eager loading)
        $queryCount = count($trace->database);
        $this->assertLessThanOrEqual(2, $queryCount, 
            "Expected ≤2 queries, got {$queryCount}. Possible N+1 problem.");
    }
}
```

### Integration Test Helper

```php
<?php
// tests/Helpers/ChronoTraceTestTrait.php

trait ChronoTraceTestTrait
{
    protected function recordRequest(string $method, string $uri, array $data = []): string
    {
        $recorder = app(TraceRecorder::class);
        
        $recorder->startRecording();
        
        switch (strtolower($method)) {
            case 'get':
                $this->getJson($uri);
                break;
            case 'post':
                $this->postJson($uri, $data);
                break;
            case 'put':
                $this->putJson($uri, $data);
                break;
            case 'delete':
                $this->deleteJson($uri);
                break;
        }
        
        return $recorder->stopRecording();
    }
    
    protected function assertQueryCount(string $traceId, int $expectedCount, string $message = null)
    {
        $trace = app(TraceStorage::class)->retrieve($traceId);
        $actualCount = count($trace->database);
        
        $this->assertEquals($expectedCount, $actualCount, 
            $message ?: "Expected {$expectedCount} queries, got {$actualCount}");
    }
    
    protected function assertNoCacheHits(string $traceId)
    {
        $trace = app(TraceStorage::class)->retrieve($traceId);
        $hits = array_filter($trace->cache, fn($event) => $event['type'] === 'hit');
        
        $this->assertEmpty($hits, 'Expected no cache hits');
    }
    
    protected function assertCacheHit(string $traceId, string $key)
    {
        $trace = app(TraceStorage::class)->retrieve($traceId);
        $hits = array_filter($trace->cache, function($event) use ($key) {
            return $event['type'] === 'hit' && $event['key'] === $key;
        });
        
        $this->assertNotEmpty($hits, "Expected cache hit for key: {$key}");
    }
}
```

**Usage in Tests:**
```php
class ProductApiTest extends TestCase
{
    use ChronoTraceTestTrait;
    
    public function test_product_search_uses_cache()
    {
        // First request should cache the results
        $traceId1 = $this->recordRequest('GET', '/api/products/search?q=laptop');
        $this->assertNoCacheHits($traceId1);
        
        // Second request should hit cache
        $traceId2 = $this->recordRequest('GET', '/api/products/search?q=laptop');
        $this->assertCacheHit($traceId2, 'products:search:laptop');
    }
}
```

## Performance Optimization Workflow

### Identifying Performance Issues

**1. Benchmark Current Performance:**
```bash
# Create baseline measurements
php artisan chronotrace:record /api/slow-endpoint
BASELINE=$(php artisan chronotrace:list --limit=1 | tail -1 | awk '{print $2}')

echo "Baseline metrics:"
php artisan chronotrace:replay $BASELINE | grep -E "(Duration|Memory|queries)"
```

**2. Profile Queries:**
```bash
# Analyze database performance
php artisan chronotrace:replay $BASELINE --db > queries-before.txt

# Look for slow queries
cat queries-before.txt | grep -E "\([0-9]{3,}" # >100ms queries
```

**3. Optimize Code:**
```php
// Before: N+1 problem
$users = User::all();
foreach ($users as $user) {
    echo $user->posts->count(); // N+1!
}

// After: Eager loading
$users = User::withCount('posts')->get();
foreach ($users as $user) {
    echo $user->posts_count;
}
```

**4. Verify Improvements:**
```bash
# Record after optimization
php artisan chronotrace:record /api/slow-endpoint
OPTIMIZED=$(php artisan chronotrace:list --limit=1 | tail -1 | awk '{print $2}')

# Compare results
echo "Before optimization:"
php artisan chronotrace:replay $BASELINE --db | grep -c "Query:"

echo "After optimization:"
php artisan chronotrace:replay $OPTIMIZED --db | grep -c "Query:"
```

### Automated Performance Testing

```bash
#!/bin/bash
# scripts/performance-test.sh

ENDPOINTS=(
    "/api/users"
    "/api/products"
    "/api/dashboard"
    "/api/reports/summary"
)

echo "=== Performance Test Report ==="
echo "Generated: $(date)"
echo

for endpoint in "${ENDPOINTS[@]}"; do
    echo "Testing: $endpoint"
    
    # Record multiple samples
    for i in {1..3}; do
        php artisan chronotrace:record "$endpoint" > /dev/null 2>&1
    done
    
    # Get last 3 traces
    TRACES=($(php artisan chronotrace:list --limit=3 | tail -n +4 | head -3 | awk '{print $2}'))
    
    total_duration=0
    total_queries=0
    
    for trace in "${TRACES[@]}"; do
        duration=$(php artisan chronotrace:replay "$trace" | grep "Duration:" | awk '{print $3}' | sed 's/ms//')
        queries=$(php artisan chronotrace:replay "$trace" --db | grep -c "Query:")
        
        total_duration=$((total_duration + duration))
        total_queries=$((total_queries + queries))
    done
    
    avg_duration=$((total_duration / 3))
    avg_queries=$((total_queries / 3))
    
    echo "  Average Duration: ${avg_duration}ms"
    echo "  Average Queries: ${avg_queries}"
    
    # Alert on performance issues
    if [ $avg_duration -gt 1000 ]; then
        echo "  ⚠️  SLOW RESPONSE (>1s)"
    fi
    
    if [ $avg_queries -gt 20 ]; then
        echo "  ⚠️  HIGH QUERY COUNT (>20)"
    fi
    
    echo
done
```

## Debugging Workflows

### External API Debugging

**1. Record API Integration:**
```bash
# Test external API calls
php artisan chronotrace:record /api/weather-forecast
```

**2. Analyze External Dependencies:**
```bash
TRACE_ID=$(php artisan chronotrace:list --limit=1 | tail -1 | awk '{print $2}')

# Check external API calls
php artisan chronotrace:replay $TRACE_ID --http

# Look for failures or slow responses
php artisan chronotrace:replay $TRACE_ID --http | grep -E "(Failed|[0-9]{4,}ms)"
```

**3. Mock Slow Services for Testing:**
```php
// tests/Feature/WeatherApiTest.php

public function test_handles_slow_weather_api()
{
    // Mock slow response
    Http::fake([
        'api.weather.com/*' => Http::response(['data' => 'test'], 200, [])
            ->delay(5000), // 5 second delay
    ]);
    
    $traceId = $this->recordRequest('GET', '/api/weather-forecast');
    
    $trace = app(TraceStorage::class)->retrieve($traceId);
    
    // Verify timeout handling
    $this->assertLessThan(10000, $trace->response->duration); // Should timeout before 10s
}
```

### Cache Debugging

**1. Cache Miss Investigation:**
```bash
# Record cache-heavy endpoint
php artisan chronotrace:record /api/popular-products

TRACE_ID=$(php artisan chronotrace:list --limit=1 | tail -1 | awk '{print $2}')

# Check cache efficiency
php artisan chronotrace:replay $TRACE_ID --cache | grep -c "MISS"
php artisan chronotrace:replay $TRACE_ID --cache | grep -c "HIT"
```

**2. Cache Invalidation Debugging:**
```bash
# Clear cache and record
php artisan cache:clear
php artisan chronotrace:record /api/products/1

# Check what gets cached
TRACE_ID=$(php artisan chronotrace:list --limit=1 | tail -1 | awk '{print $2}')
php artisan chronotrace:replay $TRACE_ID --cache | grep "WRITE"
```

### Queue Job Debugging

**1. Job Failure Investigation:**
```bash
# Record job-dispatching endpoint
php artisan chronotrace:record /orders/process \
  --method=POST \
  --data='{"order_id": 123}'

TRACE_ID=$(php artisan chronotrace:list --limit=1 | tail -1 | awk '{print $2}')

# Check job execution
php artisan chronotrace:replay $TRACE_ID --jobs
```

**2. Queue Performance Analysis:**
```bash
# Look for slow jobs
php artisan chronotrace:replay $TRACE_ID --jobs | grep -E "STARTED.*COMPLETED" | while read line; do
    # Extract timing information
    echo "$line"
done
```

## Git Integration

### Pre-commit Hook

```bash
#!/bin/bash
# .git/hooks/pre-commit

echo "Running ChronoTrace performance check..."

# Test critical endpoints
CRITICAL_ENDPOINTS=("/api/users" "/api/orders")

for endpoint in "${CRITICAL_ENDPOINTS[@]}"; do
    php artisan chronotrace:record "$endpoint" > /dev/null 2>&1
    
    TRACE_ID=$(php artisan chronotrace:list --limit=1 | tail -1 | awk '{print $2}')
    DURATION=$(php artisan chronotrace:replay "$TRACE_ID" | grep "Duration:" | awk '{print $3}' | sed 's/ms//')
    QUERIES=$(php artisan chronotrace:replay "$TRACE_ID" --db | grep -c "Query:")
    
    if [ "$DURATION" -gt 2000 ]; then
        echo "❌ Performance regression detected in $endpoint"
        echo "   Duration: ${DURATION}ms (>2000ms threshold)"
        exit 1
    fi
    
    if [ "$QUERIES" -gt 15 ]; then
        echo "❌ Query count regression detected in $endpoint"
        echo "   Queries: $QUERIES (>15 queries threshold)"
        exit 1
    fi
done

echo "✅ Performance checks passed"
```

### Branch Comparison

```bash
#!/bin/bash
# scripts/compare-branches.sh

if [ $# -ne 2 ]; then
    echo "Usage: $0 <branch1> <branch2>"
    exit 1
fi

BRANCH1=$1
BRANCH2=$2
ENDPOINT=${3:-"/api/users"}

echo "Comparing performance between $BRANCH1 and $BRANCH2"

# Test branch 1
git checkout $BRANCH1
php artisan chronotrace:record "$ENDPOINT" > /dev/null 2>&1
TRACE1=$(php artisan chronotrace:list --limit=1 | tail -1 | awk '{print $2}')

# Test branch 2
git checkout $BRANCH2
php artisan chronotrace:record "$ENDPOINT" > /dev/null 2>&1
TRACE2=$(php artisan chronotrace:list --limit=1 | tail -1 | awk '{print $2}')

# Compare results
echo "$BRANCH1:"
php artisan chronotrace:replay $TRACE1 | grep -E "(Duration|Memory|queries)"

echo "$BRANCH2:"
php artisan chronotrace:replay $TRACE2 | grep -E "(Duration|Memory|queries)"
```

## Team Collaboration

### Sharing Traces

**Export Trace for Sharing:**
```bash
# Export trace data
php artisan chronotrace:replay {trace-id} --format=json > trace-export.json

# Share via Git (remove sensitive data first)
git add trace-export.json
git commit -m "Add trace for bug investigation"
```

**Import and Analyze:**
```bash
# Team member can analyze the exported trace
cat trace-export.json | jq '.database[] | select(.time > 100)'
```

### Documentation Integration

**Add Performance Notes to README:**
```markdown
## Performance Benchmarks

Last updated: 2024-01-15

| Endpoint | Avg Duration | Avg Queries | Notes |
|----------|--------------|-------------|-------|
| `/api/users` | 245ms | 3 | Optimized with eager loading |
| `/api/products` | 180ms | 2 | Uses Redis caching |
| `/api/orders` | 890ms | 12 | Consider pagination |

To regenerate: `bash scripts/performance-test.sh`
```

## IDE Integration

### VS Code Snippets

```json
// .vscode/snippets.json
{
    "chronotrace-record": {
        "prefix": "ctrace",
        "body": [
            "php artisan chronotrace:record ${1:/endpoint} ${2:--method=GET}"
        ],
        "description": "Record ChronoTrace"
    },
    "chronotrace-replay": {
        "prefix": "creplay",
        "body": [
            "php artisan chronotrace:replay ${1:trace-id} ${2:--db}"
        ],
        "description": "Replay ChronoTrace"
    }
}
```

### PHPStorm Live Templates

Create live templates for common ChronoTrace operations:
- `ctrace` → `php artisan chronotrace:record`
- `creplay` → `php artisan chronotrace:replay`
- `clist` → `php artisan chronotrace:list`

## Best Practices

1. **Regular Cleanup**: Run `php artisan chronotrace:purge --days=3 --confirm` daily in development
2. **Performance Baselines**: Establish benchmarks for critical endpoints
3. **Automated Testing**: Include performance tests in your CI pipeline
4. **Documentation**: Keep performance notes updated in your project documentation
5. **Team Training**: Ensure all developers know how to use ChronoTrace for debugging
6. **Focus Areas**: Prioritize monitoring authentication, payments, and data-heavy endpoints

## Next Steps

- [Set up production monitoring](production-monitoring.md)
- [Configure custom storage](custom-storage.md)
- [Review security practices](../docs/security.md)