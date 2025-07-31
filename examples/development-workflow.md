# Development Workflow with Laravel ChronoTrace

This guide shows how to integrate Laravel ChronoTrace into your development workflow for debugging, testing, and quality assurance.

## Development Environment Setup

### Initial Configuration

```bash
# Install ChronoTrace in development
composer require --dev grazulex/laravel-chronotrace
php artisan chronotrace:install
```

### Development-Specific Configuration

```env
# Development .env settings
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

# Use synchronous storage for immediate feedback
CHRONOTRACE_ASYNC_STORAGE=false
```

## Feature Development Workflow

### 1. Before Starting Development

```bash
# Validate your ChronoTrace setup
php artisan chronotrace:diagnose

# Clean up old traces
php artisan chronotrace:purge --days=1 --confirm

# Test middleware
php artisan chronotrace:test-middleware
```

### 2. During Feature Development

#### Record Baseline Traces

```bash
# Record current behavior before changes
php artisan chronotrace:record /api/feature-endpoint \
  --method=GET \
  --headers='{"Authorization":"Bearer dev-token"}'

# Note the trace ID for later comparison
php artisan chronotrace:list --limit=1 --full-id
```

#### Test API Endpoints

```bash
# Test GET requests
php artisan chronotrace:record /api/users
php artisan chronotrace:record /api/users/123

# Test POST requests with data
php artisan chronotrace:record /api/users \
  --method=POST \
  --data='{"name":"Test User","email":"test@example.com"}'

# Test PUT/PATCH requests
php artisan chronotrace:record /api/users/123 \
  --method=PUT \
  --data='{"name":"Updated Name"}'

# Test DELETE requests
php artisan chronotrace:record /api/users/123 \
  --method=DELETE
```

#### Debug Complex Workflows

```bash
# Record a complex business process
php artisan chronotrace:record /orders/checkout \
  --method=POST \
  --data='{
    "items": [{"id": 1, "quantity": 2}],
    "payment": {"method": "credit_card"},
    "shipping": {"address": "123 Main St"}
  }' \
  --headers='{"Authorization":"Bearer test-token"}'

# Analyze the full workflow
TRACE_ID=$(php artisan chronotrace:list --limit=1 --full-id | grep "│" | head -1 | awk '{print $2}')
php artisan chronotrace:replay $TRACE_ID --detailed
```

### 3. Analyzing Development Traces

#### Database Performance Analysis

```bash
# Check for N+1 queries
php artisan chronotrace:replay {trace-id} --db

# View SQL bindings for debugging
php artisan chronotrace:replay {trace-id} --db --bindings

# Look for slow queries
php artisan chronotrace:replay {trace-id} --db | grep -E "[0-9]{3,}ms"
```

#### Cache Analysis

```bash
# Check cache efficiency
php artisan chronotrace:replay {trace-id} --cache

# Look for cache misses that could be optimized
php artisan chronotrace:replay {trace-id} --cache | grep "MISS"
```

#### External Service Integration

```bash
# Monitor API calls to external services
php artisan chronotrace:replay {trace-id} --http

# Check for failed external requests
php artisan chronotrace:replay {trace-id} --http | grep -E "(Failed|4[0-9][0-9]|5[0-9][0-9])"
```

## Test-Driven Development with ChronoTrace

### 1. Generate Tests from User Stories

```bash
# Record user workflow
php artisan chronotrace:record /complete-user-registration \
  --method=POST \
  --data='{"email":"user@example.com","password":"password123"}'

# Generate test from the workflow
php artisan chronotrace:replay {trace-id} --generate-test --test-path=tests/Feature

# Review and customize the generated test
cat tests/Feature/ChronoTrace_{trace-id}_Test.php
```

### 2. Regression Testing

```bash
# Create regression tests for bug fixes
php artisan chronotrace:record /bug-endpoint-before-fix
# ... fix the bug ...
php artisan chronotrace:record /bug-endpoint-after-fix

# Generate test that validates the fix
php artisan chronotrace:replay {after-fix-trace-id} --generate-test --test-path=tests/Regression
```

### 3. Integration Testing

```bash
# Test complex integrations
php artisan chronotrace:record /api/payment/process \
  --method=POST \
  --data='{"amount": 100, "method": "stripe"}' \
  --headers='{"Authorization":"Bearer test-token"}'

# Generate integration test
php artisan chronotrace:replay {trace-id} --generate-test --test-path=tests/Integration
```

## Debugging Workflows

### 1. API Debugging

```bash
# Record API request with full detail
php artisan chronotrace:record /api/problematic-endpoint \
  --method=POST \
  --data='{"test": "data"}' \
  --headers='{"Content-Type":"application/json","Authorization":"Bearer token"}'

# Analyze with maximum detail
php artisan chronotrace:replay {trace-id} --detailed --context --headers --content --bindings
```

### 2. Performance Debugging

```bash
# Record slow endpoint
php artisan chronotrace:record /slow-dashboard-page

# Analyze performance issues
php artisan chronotrace:replay {trace-id} | grep -E "(Duration|Memory|Query.*[0-9]{3,}ms)"

# Focus on database performance
php artisan chronotrace:replay {trace-id} --db --bindings | grep -E "[0-9]{3,}ms"
```

### 3. Queue Job Debugging

```bash
# Record endpoint that dispatches jobs
php artisan chronotrace:record /trigger-background-jobs \
  --method=POST \
  --data='{"process": "batch_emails"}'

# Analyze job processing
php artisan chronotrace:replay {trace-id} --jobs

# Check for job failures
php artisan chronotrace:replay {trace-id} --jobs | grep -E "(Failed|Error)"
```

## Code Review Process

### 1. Pre-Review Testing

```bash
# Create script for feature branch testing
#!/bin/bash
# test-feature-branch.sh

echo "Testing feature branch with ChronoTrace..."

# Test main user flows
php artisan chronotrace:record /api/main-feature
php artisan chronotrace:record /api/main-feature --method=POST --data='{"test": true}'

# Get trace IDs
TRACES=$(php artisan chronotrace:list --limit=2 --full-id | grep "│" | awk '{print $2}')

echo "Generated traces for review:"
for TRACE in $TRACES; do
    echo "  Trace: $TRACE"
    echo "  Command: php artisan chronotrace:replay $TRACE"
done

# Generate summary report
echo "Performance Summary:"
for TRACE in $TRACES; do
    php artisan chronotrace:replay $TRACE | grep -E "(Duration|Memory|Response Status)"
done
```

### 2. Code Review Checklist

```bash
# Performance review script
#!/bin/bash
# performance-review.sh

TRACE_ID=$1

if [ -z "$TRACE_ID" ]; then
    echo "Usage: $0 <trace-id>"
    exit 1
fi

echo "=== Performance Review for $TRACE_ID ==="

# Check overall performance
echo "Overall Performance:"
php artisan chronotrace:replay $TRACE_ID | grep -E "(Duration|Memory|Response Status)"

echo
echo "Database Performance:"
DB_QUERIES=$(php artisan chronotrace:replay $TRACE_ID --db | grep -c "Query:")
SLOW_QUERIES=$(php artisan chronotrace:replay $TRACE_ID --db | grep -c "[0-9]{3,}ms")
echo "  Total queries: $DB_QUERIES"
echo "  Slow queries (>100ms): $SLOW_QUERIES"

echo
echo "Cache Performance:"
CACHE_HITS=$(php artisan chronotrace:replay $TRACE_ID --cache | grep -c "HIT")
CACHE_MISSES=$(php artisan chronotrace:replay $TRACE_ID --cache | grep -c "MISS")
echo "  Cache hits: $CACHE_HITS"
echo "  Cache misses: $CACHE_MISSES"

echo
echo "External Dependencies:"
HTTP_REQUESTS=$(php artisan chronotrace:replay $TRACE_ID --http | grep -c "HTTP Request:")
HTTP_FAILURES=$(php artisan chronotrace:replay $TRACE_ID --http | grep -c -E "(Failed|[45][0-9][0-9])")
echo "  HTTP requests: $HTTP_REQUESTS"
echo "  HTTP failures: $HTTP_FAILURES"

# Generate recommendations
echo
echo "=== Recommendations ==="
if [ $SLOW_QUERIES -gt 0 ]; then
    echo "❌ Optimize slow database queries"
fi

if [ $CACHE_MISSES -gt $CACHE_HITS ]; then
    echo "❌ Consider adding more caching"
fi

if [ $HTTP_FAILURES -gt 0 ]; then
    echo "❌ Review external service error handling"
fi

if [ $SLOW_QUERIES -eq 0 ] && [ $HTTP_FAILURES -eq 0 ]; then
    echo "✅ Performance looks good!"
fi
```

## Continuous Integration Integration

### 1. CI Pipeline Test

```yaml
# .github/workflows/chronotrace.yml
name: ChronoTrace Tests

on:
  pull_request:
    branches: [ main ]

jobs:
  chronotrace:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.3'
        
    - name: Install dependencies
      run: composer install --no-progress --prefer-dist --optimize-autoloader
      
    - name: Install ChronoTrace
      run: php artisan chronotrace:install
      
    - name: Validate ChronoTrace setup
      run: php artisan chronotrace:diagnose
      
    - name: Test critical endpoints
      run: |
        php artisan chronotrace:record /api/health
        php artisan chronotrace:record /api/users --method=GET
        
    - name: Analyze traces for performance
      run: |
        TRACES=$(php artisan chronotrace:list --limit=10 --full-id | grep "│" | awk '{print $2}')
        for TRACE in $TRACES; do
          echo "Analyzing trace: $TRACE"
          php artisan chronotrace:replay $TRACE | grep -E "(Duration|Memory)"
        done
```

### 2. Performance Gate

```bash
#!/bin/bash
# performance-gate.sh - CI performance check

MAX_DURATION=2000  # 2 seconds
MAX_QUERIES=20
MAX_MEMORY=50      # MB

TRACE_ID=$(php artisan chronotrace:list --limit=1 --full-id | grep "│" | head -1 | awk '{print $2}')

# Check duration
DURATION=$(php artisan chronotrace:replay $TRACE_ID | grep "Duration:" | awk '{print $3}' | sed 's/ms//')
if [ "$DURATION" -gt "$MAX_DURATION" ]; then
    echo "❌ Performance gate failed: Duration ${DURATION}ms > ${MAX_DURATION}ms"
    exit 1
fi

# Check query count
QUERY_COUNT=$(php artisan chronotrace:replay $TRACE_ID --db | grep -c "Query:")
if [ "$QUERY_COUNT" -gt "$MAX_QUERIES" ]; then
    echo "❌ Performance gate failed: Query count $QUERY_COUNT > $MAX_QUERIES"
    exit 1
fi

# Check memory usage
MEMORY=$(php artisan chronotrace:replay $TRACE_ID | grep "Memory Usage:" | awk '{print $3}' | sed 's/MB//')
if (( $(echo "$MEMORY > $MAX_MEMORY" | bc -l) )); then
    echo "❌ Performance gate failed: Memory ${MEMORY}MB > ${MAX_MEMORY}MB"
    exit 1
fi

echo "✅ Performance gate passed"
```

## Local Development Scripts

### 1. Daily Development Setup

```bash
#!/bin/bash
# daily-dev-setup.sh

echo "🚀 Starting daily development setup..."

# Validate ChronoTrace
php artisan chronotrace:diagnose

# Clean old traces
php artisan chronotrace:purge --days=1 --confirm

# Test main application flows
echo "Testing main application flows..."
php artisan chronotrace:record /
php artisan chronotrace:record /api/health
php artisan chronotrace:record /login --method=GET

echo "Recent traces:"
php artisan chronotrace:list --limit=5

echo "✅ Development environment ready!"
```

### 2. Feature Testing Script

```bash
#!/bin/bash
# test-feature.sh

FEATURE_PATH=$1

if [ -z "$FEATURE_PATH" ]; then
    echo "Usage: $0 <feature-endpoint>"
    echo "Example: $0 /api/new-feature"
    exit 1
fi

echo "Testing feature: $FEATURE_PATH"

# Test different HTTP methods
echo "Testing GET..."
php artisan chronotrace:record "$FEATURE_PATH" --method=GET

echo "Testing POST..."
php artisan chronotrace:record "$FEATURE_PATH" --method=POST --data='{"test": true}'

# Get latest traces
TRACES=$(php artisan chronotrace:list --limit=2 --full-id | grep "│" | awk '{print $2}')

echo "Analysis:"
for TRACE in $TRACES; do
    echo "=== Trace: $TRACE ==="
    php artisan chronotrace:replay $TRACE | grep -E "(Response Status|Duration|Memory)"
    
    # Check for issues
    ERRORS=$(php artisan chronotrace:replay $TRACE | grep -c -E "(Error|Failed|5[0-9][0-9])")
    if [ $ERRORS -gt 0 ]; then
        echo "❌ Issues detected in trace $TRACE"
        php artisan chronotrace:replay $TRACE
    else
        echo "✅ Trace $TRACE looks good"
    fi
    echo
done
```

### 3. Performance Comparison Script

```bash
#!/bin/bash
# compare-performance.sh

ENDPOINT=$1
BEFORE_TRACE=$2

if [ -z "$ENDPOINT" ] || [ -z "$BEFORE_TRACE" ]; then
    echo "Usage: $0 <endpoint> <before-trace-id>"
    echo "This script compares performance before and after changes"
    exit 1
fi

echo "Recording new trace for comparison..."
php artisan chronotrace:record "$ENDPOINT"

AFTER_TRACE=$(php artisan chronotrace:list --limit=1 --full-id | grep "│" | head -1 | awk '{print $2}')

echo "=== Performance Comparison ==="
echo "Before trace: $BEFORE_TRACE"
echo "After trace:  $AFTER_TRACE"
echo

echo "Before performance:"
php artisan chronotrace:replay $BEFORE_TRACE | grep -E "(Duration|Memory|Response Status)"

echo
echo "After performance:"
php artisan chronotrace:replay $AFTER_TRACE | grep -E "(Duration|Memory|Response Status)"

echo
echo "Database comparison:"
echo "Before queries:"
php artisan chronotrace:replay $BEFORE_TRACE --db | grep -c "Query:"
echo "After queries:"
php artisan chronotrace:replay $AFTER_TRACE --db | grep -c "Query:"
```

## Best Practices for Development

### 1. Trace Naming Convention

Use descriptive commit messages and branch names to make traces easier to identify:

```bash
# Before making changes, record baseline
git checkout -b feature/user-dashboard
php artisan chronotrace:record /dashboard --method=GET
# Note trace ID in commit or PR description
```

### 2. Regular Cleanup

```bash
# Add to your daily routine
php artisan chronotrace:purge --days=7 --confirm
```

### 3. Performance Budgets

Set performance expectations:

```bash
# Check if endpoint meets performance budget
TRACE_ID=$(php artisan chronotrace:record /api/endpoint && php artisan chronotrace:list --limit=1 --full-id | grep "│" | head -1 | awk '{print $2}')
DURATION=$(php artisan chronotrace:replay $TRACE_ID | grep "Duration:" | awk '{print $3}' | sed 's/ms//')

if [ "$DURATION" -gt 1000 ]; then
    echo "❌ Performance budget exceeded: ${DURATION}ms > 1000ms"
else
    echo "✅ Performance budget met: ${DURATION}ms"
fi
```

## Team Workflows

### 1. Onboarding New Developers

```bash
#!/bin/bash
# onboard-developer.sh

echo "🎯 Developer Onboarding with ChronoTrace"

# Setup ChronoTrace
php artisan chronotrace:install
php artisan chronotrace:diagnose

# Record key application flows
echo "Recording key application flows for learning..."
php artisan chronotrace:record /
php artisan chronotrace:record /api/users
php artisan chronotrace:record /api/orders

echo "Use these traces to understand the application:"
php artisan chronotrace:list --full-id

echo
echo "Try these commands:"
echo "  php artisan chronotrace:replay {trace-id} --detailed"
echo "  php artisan chronotrace:replay {trace-id} --db"
echo "  php artisan chronotrace:replay {trace-id} --http"
```

### 2. Code Review Preparation

```bash
#!/bin/bash
# prepare-for-review.sh

echo "Preparing traces for code review..."

# Test all modified endpoints
git diff --name-only main | grep -E "(routes|Controller)" | while read file; do
    echo "Testing endpoints in $file"
    # Extract and test routes (simplified)
done

# Generate performance report
php artisan chronotrace:list --limit=10 > traces-for-review.txt
echo "Traces saved to traces-for-review.txt"
```

---

**Next Steps:**
- [Production Monitoring](production-monitoring.md)
- [Configuration Examples](configuration-examples.md)
- [Advanced Features](../docs/api-reference.md)