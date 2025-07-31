# Laravel ChronoTrace Examples

This directory contains comprehensive examples demonstrating how to use Laravel ChronoTrace effectively in various scenarios, from development to production.

## 📁 Examples Overview

- **[Basic Usage](basic-usage.md)** - Complete beginner's guide to recording and replaying traces
- **[Configuration Examples](configuration-examples.md)** - Environment-specific configurations for development, staging, and production
- **[Event Filtering](event-filtering.md)** - Advanced filtering techniques for database, cache, HTTP, and job events
- **[Custom Storage](custom-storage.md)** - Setting up S3, MinIO, and custom storage solutions
- **[Production Monitoring](production-monitoring.md)** - Real-world production monitoring, incident response, and alerting
- **[Development Workflow](development-workflow.md)** - Integrating ChronoTrace into your development process and CI/CD pipelines

## 🚀 Quick Start Example

Get started with ChronoTrace in under 5 minutes:

```bash
# 1. Install and configure
composer require --dev grazulex/laravel-chronotrace
php artisan chronotrace:install

# 2. Verify installation
php artisan chronotrace:diagnose

# 3. Record your first trace
php artisan chronotrace:record /api/users

# 4. List traces to get the ID
php artisan chronotrace:list --full-id

# 5. Replay the trace with detailed analysis
php artisan chronotrace:replay {your-trace-id} --detailed

# 6. Generate a test from the trace
php artisan chronotrace:replay {your-trace-id} --generate-test
```
## 📊 Comprehensive Use Cases

### 🐛 Debugging Production Issues

**Scenario**: A 500 error occurs in production that you can't reproduce locally.

```bash
# ChronoTrace automatically captured the error (record_on_error mode)
php artisan chronotrace:list --limit=10

# Get the error trace ID and analyze comprehensively
php artisan chronotrace:replay {error-trace-id} --detailed

# Focus on likely causes
php artisan chronotrace:replay {error-trace-id} --db --bindings  # Database issues
php artisan chronotrace:replay {error-trace-id} --http           # External API failures
php artisan chronotrace:replay {error-trace-id} --jobs           # Queue job problems

# Generate a regression test to prevent future occurrences
php artisan chronotrace:replay {error-trace-id} --generate-test --test-path=tests/Regression
```

### 🚀 Performance Optimization

**Scenario**: Your dashboard is loading slowly and you need to identify bottlenecks.

```bash
# Record the slow endpoint
php artisan chronotrace:record /dashboard --timeout=30

# Analyze performance comprehensively
php artisan chronotrace:replay {trace-id} --detailed

# Check for N+1 queries
php artisan chronotrace:replay {trace-id} --db --bindings | grep -E "[0-9]{3,}ms"

# Check cache efficiency
php artisan chronotrace:replay {trace-id} --cache | grep -E "(MISS|HIT)"

# Monitor external API call times
php artisan chronotrace:replay {trace-id} --http | grep -E "[0-9]{3,}ms"
```

### 🔍 API Development and Testing

**Scenario**: You're developing a new API endpoint and want to ensure it works correctly.

```bash
# Test different HTTP methods and data
php artisan chronotrace:record /api/v1/orders --method=GET
php artisan chronotrace:record /api/v1/orders \
  --method=POST \
  --data='{"product_id": 1, "quantity": 2}' \
  --headers='{"Authorization":"Bearer test-token"}'

# Verify all endpoints work correctly
php artisan chronotrace:replay {get-trace-id} --detailed
php artisan chronotrace:replay {post-trace-id} --detailed

# Generate comprehensive API tests
php artisan chronotrace:replay {post-trace-id} --generate-test --test-path=tests/Api
```

### 🏢 E-commerce Checkout Flow Analysis

**Scenario**: You want to understand and optimize your checkout process.

```bash
# Record the complete checkout flow
php artisan chronotrace:record /checkout/initiate \
  --method=POST \
  --data='{"cart_id": "abc123"}'

php artisan chronotrace:record /checkout/payment \
  --method=POST \
  --data='{"payment_method": "stripe", "amount": 99.99}' \
  --headers='{"Authorization":"Bearer customer-token"}'

php artisan chronotrace:record /checkout/complete \
  --method=POST \
  --data='{"order_id": "ord_456"}'

# Analyze the complete flow
TRACES=$(php artisan chronotrace:list --limit=3 --full-id | grep "│" | awk '{print $2}')
for TRACE in $TRACES; do
    echo "=== Analyzing checkout step: $TRACE ==="
    php artisan chronotrace:replay $TRACE | grep -E "(Duration|Memory|Response Status)"
done

# Generate integration tests for the checkout flow
for TRACE in $TRACES; do
    php artisan chronotrace:replay $TRACE --generate-test --test-path=tests/Checkout
done
```

## 🛠️ Development Workflow Examples

### Daily Development Routine

```bash
#!/bin/bash
# daily-dev-start.sh - Run this each morning

echo "🌅 Starting daily development with ChronoTrace..."

# Clean up old traces
php artisan chronotrace:purge --days=3 --confirm

# Validate setup
php artisan chronotrace:diagnose

# Test core application flows
echo "Testing core application health..."
php artisan chronotrace:record / --method=GET
php artisan chronotrace:record /api/health --method=GET

echo "✅ Development environment ready!"
echo "Recent traces:"
php artisan chronotrace:list --limit=5
```

### Feature Development Cycle

```bash
#!/bin/bash
# feature-development.sh <feature-endpoint>

ENDPOINT=$1
if [ -z "$ENDPOINT" ]; then
    echo "Usage: $0 <endpoint>"
    echo "Example: $0 /api/new-feature"
    exit 1
fi

echo "🔄 Testing feature: $ENDPOINT"

# Record baseline before changes
echo "Recording baseline..."
php artisan chronotrace:record "$ENDPOINT"
BASELINE_TRACE=$(php artisan chronotrace:list --limit=1 --full-id | grep "│" | head -1 | awk '{print $2}')

echo "Baseline trace: $BASELINE_TRACE"
echo "Make your changes, then run this script again with a different endpoint to compare."

# After changes, record again and compare
echo "Performance analysis:"
php artisan chronotrace:replay $BASELINE_TRACE | grep -E "(Duration|Memory|Response Status)"
```

### Code Review Preparation

```bash
#!/bin/bash
# prepare-code-review.sh

echo "📋 Preparing traces for code review..."

# Record key endpoints affected by changes
git diff --name-only main | grep -E "(Controller|routes)" | while read file; do
    echo "Testing changes in: $file"
    # Add logic to extract and test relevant endpoints
done

# Generate performance report
echo "=== Performance Report ===" > code-review-traces.md
echo "Generated on: $(date)" >> code-review-traces.md
echo "" >> code-review-traces.md

php artisan chronotrace:list --limit=10 | while read line; do
    if [[ $line == *"│"* ]]; then
        trace_id=$(echo $line | awk '{print $2}')
        echo "## Trace: $trace_id" >> code-review-traces.md
        php artisan chronotrace:replay $trace_id | grep -E "(Duration|Memory|Response Status)" >> code-review-traces.md
        echo "" >> code-review-traces.md
    fi
done

echo "Code review report saved to: code-review-traces.md"
```

## 🔧 Advanced Usage Patterns

### Automated Performance Monitoring

```bash
#!/bin/bash
# performance-monitor.sh - Run this hourly/daily

PERFORMANCE_THRESHOLD=2000  # 2 seconds

echo "🔍 Monitoring application performance..."

# Record critical endpoints
CRITICAL_ENDPOINTS=(
    "/api/dashboard"
    "/api/orders"
    "/checkout/process"
    "/api/reports/sales"
)

for endpoint in "${CRITICAL_ENDPOINTS[@]}"; do
    echo "Testing: $endpoint"
    php artisan chronotrace:record "$endpoint"
    
    # Get the latest trace
    TRACE_ID=$(php artisan chronotrace:list --limit=1 --full-id | grep "│" | head -1 | awk '{print $2}')
    
    # Check performance
    DURATION=$(php artisan chronotrace:replay $TRACE_ID | grep "Duration:" | awk '{print $3}' | sed 's/ms//')
    
    if [ "$DURATION" -gt "$PERFORMANCE_THRESHOLD" ]; then
        echo "⚠️  Performance alert: $endpoint took ${DURATION}ms (threshold: ${PERFORMANCE_THRESHOLD}ms)"
        echo "Trace ID: $TRACE_ID"
        
        # Send to monitoring system or log
        logger "ChronoTrace Performance Alert: $endpoint exceeded threshold"
    else
        echo "✅ $endpoint performance OK: ${DURATION}ms"
    fi
done
```

### Database Query Analysis

```bash
#!/bin/bash
# analyze-queries.sh <trace-id>

TRACE_ID=$1
if [ -z "$TRACE_ID" ]; then
    echo "Usage: $0 <trace-id>"
    echo "Analyzes database performance for a specific trace"
    exit 1
fi

echo "🔍 Database Analysis for Trace: $TRACE_ID"
echo "=" | head -c 50; echo

# Overall database metrics
echo "📊 Query Statistics:"
TOTAL_QUERIES=$(php artisan chronotrace:replay $TRACE_ID --db | grep -c "Query:")
SLOW_QUERIES=$(php artisan chronotrace:replay $TRACE_ID --db | grep -c "[0-9]{3,}ms")
echo "  Total queries: $TOTAL_QUERIES"
echo "  Slow queries (>100ms): $SLOW_QUERIES"

# Find the slowest query
echo ""
echo "🐌 Slowest Query:"
php artisan chronotrace:replay $TRACE_ID --db --bindings | grep -E "[0-9]{3,}ms" | head -1

# Check for potential N+1 queries
echo ""
echo "⚠️  Potential N+1 Queries:"
php artisan chronotrace:replay $TRACE_ID --db | grep "SELECT.*WHERE.*IN" | head -3

# Transaction analysis
echo ""
echo "🔄 Transactions:"
php artisan chronotrace:replay $TRACE_ID --db | grep -E "(Transaction|COMMIT|ROLLBACK)"

if [ $SLOW_QUERIES -gt 0 ]; then
    echo ""
    echo "💡 Recommendations:"
    echo "  - Review slow queries for optimization opportunities"
    echo "  - Consider adding database indexes"
    echo "  - Check for N+1 query patterns"
    echo "  - Consider query caching for repeated queries"
fi
```

### External Service Health Check

```bash
#!/bin/bash
# external-services-health.sh

echo "🌐 External Services Health Check"
echo "=" | head -c 40; echo

# Record endpoints that make external calls
EXTERNAL_ENDPOINTS=(
    "/api/weather"
    "/api/payment/validate"
    "/api/shipping/rates"
)

for endpoint in "${EXTERNAL_ENDPOINTS[@]}"; do
    echo "Testing: $endpoint"
    php artisan chronotrace:record "$endpoint"
    
    TRACE_ID=$(php artisan chronotrace:list --limit=1 --full-id | grep "│" | head -1 | awk '{print $2}')
    
    # Check HTTP events
    HTTP_FAILURES=$(php artisan chronotrace:replay $TRACE_ID --http | grep -c -E "(Failed|[45][0-9][0-9])")
    HTTP_REQUESTS=$(php artisan chronotrace:replay $TRACE_ID --http | grep -c "HTTP Request:")
    
    if [ $HTTP_FAILURES -gt 0 ]; then
        echo "❌ $endpoint: $HTTP_FAILURES failures out of $HTTP_REQUESTS requests"
        php artisan chronotrace:replay $TRACE_ID --http | grep -E "(Failed|[45][0-9][0-9])"
    else
        echo "✅ $endpoint: All external requests successful"
    fi
    echo ""
done
```

## 🎯 Environment-Specific Examples

### Development Environment

```bash
# Development workflow optimized for debugging
export CHRONOTRACE_MODE=always
export CHRONOTRACE_DEBUG=true
export CHRONOTRACE_ASYNC_STORAGE=false

# Record everything for comprehensive debugging
php artisan chronotrace:record /api/debug-endpoint --method=POST --data='{"debug": true}'

# Analyze with full context
php artisan chronotrace:replay {trace-id} --detailed --context --headers --content --bindings
```

### Staging Environment

```bash
# Staging workflow for performance testing
export CHRONOTRACE_MODE=sample
export CHRONOTRACE_SAMPLE_RATE=0.1  # 10% sampling

# Run load tests and capture samples
for i in {1..50}; do
    php artisan chronotrace:record /api/load-test --method=GET &
done
wait

# Analyze performance across samples
php artisan chronotrace:list --limit=50 | while read line; do
    if [[ $line == *"│"* ]]; then
        trace_id=$(echo $line | awk '{print $2}')
        duration=$(php artisan chronotrace:replay $trace_id | grep "Duration:" | awk '{print $3}')
        echo "$trace_id: $duration"
    fi
done | sort -k2 -n
```

### Production Environment

```bash
# Production monitoring (only errors captured)
export CHRONOTRACE_MODE=record_on_error
export CHRONOTRACE_ASYNC_STORAGE=true

# Daily production health check
php artisan chronotrace:list --limit=20 | head -5
echo "Recent error count: $(php artisan chronotrace:list --limit=100 | wc -l)"

# If errors found, analyze the most recent
LATEST_ERROR=$(php artisan chronotrace:list --limit=1 --full-id | grep "│" | head -1 | awk '{print $2}')
if [ ! -z "$LATEST_ERROR" ]; then
    echo "Analyzing latest error: $LATEST_ERROR"
    php artisan chronotrace:replay $LATEST_ERROR --db --http --jobs
fi
```

## 📚 Learning Resources

### Understanding Your Application

Use ChronoTrace to understand how your application works:

```bash
# Record key user journeys
php artisan chronotrace:record /register --method=POST --data='{"email":"test@example.com"}'
php artisan chronotrace:record /login --method=POST --data='{"email":"test@example.com"}'
php artisan chronotrace:record /dashboard --method=GET

# Analyze the complete user flow
php artisan chronotrace:list --limit=3 | while read line; do
    if [[ $line == *"│"* ]]; then
        trace_id=$(echo $line | awk '{print $2}')
        echo "=== User Flow Step: $trace_id ==="
        php artisan chronotrace:replay $trace_id | grep -E "(Request URL|Response Status|Duration)"
        echo ""
    fi
done
```

### Team Onboarding

Help new developers understand the codebase:

```bash
#!/bin/bash
# onboard-new-developer.sh

echo "🎯 Welcome to the team! Let's explore the application with ChronoTrace."

# Setup ChronoTrace for the new developer
php artisan chronotrace:install
php artisan chronotrace:diagnose

echo "Recording key application workflows for learning..."

# Record main application flows
LEARNING_ENDPOINTS=(
    "/"
    "/api/users"
    "/api/orders"
    "/dashboard"
)

for endpoint in "${LEARNING_ENDPOINTS[@]}"; do
    echo "Recording: $endpoint"
    php artisan chronotrace:record "$endpoint"
done

echo ""
echo "🔍 Explore these traces to understand the application:"
php artisan chronotrace:list --full-id

echo ""
echo "💡 Try these commands to learn:"
echo "  php artisan chronotrace:replay {trace-id} --detailed"
echo "  php artisan chronotrace:replay {trace-id} --db"
echo "  php artisan chronotrace:replay {trace-id} --http"
echo "  php artisan chronotrace:replay {trace-id} --cache"
```

## 🔗 Next Steps

- **[Master basic usage](basic-usage.md)** - Start with fundamentals
- **[Configure for your environment](configuration-examples.md)** - Set up proper configuration
- **[Set up production monitoring](production-monitoring.md)** - Monitor production applications
- **[Optimize development workflow](development-workflow.md)** - Integrate into daily development
- **[Learn advanced filtering](event-filtering.md)** - Master event analysis
- **[Configure custom storage](custom-storage.md)** - Set up S3 or custom storage

---

**Need Help?** 
- Check the [troubleshooting guide](../docs/troubleshooting.md)
- Review the [API reference](../docs/api-reference.md)
- Open an issue on [GitHub](https://github.com/Grazulex/laravel-chronotrace/issues)