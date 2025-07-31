# Production Monitoring Examples

This guide shows how to effectively use ChronoTrace for production monitoring and issue detection.

## Production Setup

### Initial Installation and Validation

```bash
# Install and configure ChronoTrace
composer require --dev grazulex/laravel-chronotrace
php artisan chronotrace:install

# Validate production configuration
php artisan chronotrace:diagnose

# Test middleware in production environment
php artisan chronotrace:test-middleware
```

## Production Configuration

### Recommended Production Setup

```env
# Minimal performance impact
CHRONOTRACE_ENABLED=true
CHRONOTRACE_MODE=record_on_error
CHRONOTRACE_STORAGE=s3
CHRONOTRACE_S3_BUCKET=prod-chronotrace
CHRONOTRACE_S3_REGION=us-west-2
CHRONOTRACE_RETENTION_DAYS=30
CHRONOTRACE_AUTO_PURGE=true

# Optimize for performance
CHRONOTRACE_ASYNC_STORAGE=true
CHRONOTRACE_QUEUE_CONNECTION=redis
CHRONOTRACE_QUEUE=chronotrace

# Capture only essential events
CHRONOTRACE_CAPTURE_DATABASE=true
CHRONOTRACE_CAPTURE_CACHE=false
CHRONOTRACE_CAPTURE_HTTP=true
CHRONOTRACE_CAPTURE_JOBS=true
CHRONOTRACE_CAPTURE_EVENTS=false
```

### High-Traffic Configuration

For applications with high traffic, use sampling:

```env
CHRONOTRACE_MODE=sample
CHRONOTRACE_SAMPLE_RATE=0.0001  # 0.01% of requests
```

Or target specific critical endpoints:

```php
// config/chronotrace.php
'mode' => 'targeted',
'targets' => [
    'routes' => [
        'api/v1/payments/*',
        'api/v1/orders/*',
        'checkout/*',
    ],
],
```

## Error Monitoring

### Automatic Error Capture

With `record_on_error` mode, ChronoTrace automatically captures traces when 5xx errors occur:

```bash
# List recent error traces
php artisan chronotrace:list --limit=20

# Analyze a specific error
php artisan chronotrace:replay {error-trace-id}
```

### Error Analysis Workflow

**1. Get Error Overview:**
```bash
php artisan chronotrace:replay {trace-id}
```

Look for:
- Response status (500, 502, 503, etc.)
- Duration (timeouts?)
- Memory usage (memory issues?)

**2. Check Database Issues:**
```bash
php artisan chronotrace:replay {trace-id} --db
```

Common database problems:
- Deadlocks
- Connection timeouts
- Slow queries causing timeouts
- Failed transactions

**3. Check External Services:**
```bash
php artisan chronotrace:replay {trace-id} --http
```

External service issues:
- API timeouts
- Service unavailability (404, 503)
- Authentication failures (401, 403)

**4. Check Background Jobs:**
```bash
php artisan chronotrace:replay {trace-id} --jobs
```

Job-related issues:
- Job failures
- Queue connection problems
- Job timeouts

## Monitoring Scripts

### Daily Error Report

```bash
#!/bin/bash
# daily-error-report.sh

echo "=== Daily ChronoTrace Error Report ==="
echo "Date: $(date)"
echo

# First validate ChronoTrace is working
php artisan chronotrace:diagnose > /dev/null 2>&1
if [ $? -ne 0 ]; then
    echo "❌ ChronoTrace configuration issues detected"
    php artisan chronotrace:diagnose
    exit 1
fi

# Count traces from last 24 hours
TRACE_COUNT=$(php artisan chronotrace:list --limit=1000 | grep "$(date +%Y-%m-%d)" | wc -l)
echo "Total traces today: $TRACE_COUNT"

if [ $TRACE_COUNT -eq 0 ]; then
    echo "No errors recorded today!"
    exit 0
fi

echo
echo "Recent error traces:"
php artisan chronotrace:list --limit=10

echo
echo "=== Analysis Recommendations ==="
echo "Run these commands to analyze errors:"

php artisan chronotrace:list --limit=5 | tail -n +4 | head -n -1 | while read line; do
    trace_id=$(echo $line | awk '{print $2}')
    echo "php artisan chronotrace:replay $trace_id"
done
```

### Performance Alert Script

```bash
#!/bin/bash
# performance-alert.sh

THRESHOLD=5000  # 5 seconds in milliseconds

echo "Checking for slow requests..."

php artisan chronotrace:list --limit=50 | tail -n +4 | head -n -1 | while read line; do
    trace_id=$(echo $line | awk '{print $2}')
    
    # Get duration from trace
    duration=$(php artisan chronotrace:replay $trace_id | grep "Duration:" | awk '{print $3}' | sed 's/ms//')
    
    if [ "$duration" -gt "$THRESHOLD" ]; then
        echo "ALERT: Slow request detected"
        echo "Trace ID: $trace_id"
        echo "Duration: ${duration}ms"
        echo "Analyze with: php artisan chronotrace:replay $trace_id"
        echo "---"
    fi
done
```

### External Service Health Check

```bash
#!/bin/bash
# external-service-health.sh

echo "=== External Service Health Report ==="

# Check recent traces for HTTP failures
php artisan chronotrace:list --limit=20 | tail -n +4 | head -n -1 | while read line; do
    trace_id=$(echo $line | awk '{print $2}')
    
    # Check for HTTP failures
    failures=$(php artisan chronotrace:replay $trace_id --http | grep -c "Connection Failed\|→ 5[0-9][0-9]\|→ 4[0-9][0-9]")
    
    if [ "$failures" -gt 0 ]; then
        echo "Service issues detected in trace: $trace_id"
        php artisan chronotrace:replay $trace_id --http | grep -E "(Connection Failed|→ [45][0-9][0-9])"
        echo "---"
    fi
done
```

## Automated Monitoring

### Laravel Scheduler Integration

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Daily error summary
    $schedule->exec('bash /path/to/daily-error-report.sh')
             ->daily()
             ->at('09:00')
             ->emailOutputTo('team@company.com');
    
    // Hourly performance check
    $schedule->exec('bash /path/to/performance-alert.sh')
             ->hourly()
             ->when(function () {
                 return app()->environment('production');
             });
    
    // Weekly cleanup
    $schedule->command('chronotrace:purge --days=30 --confirm')
             ->weekly()
             ->sundays()
             ->at('02:00');
}
```

### Slack Notifications

```php
// app/Console/Commands/ChronoTraceAlert.php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Grazulex\LaravelChronotrace\Storage\TraceStorage;
use Illuminate\Support\Facades\Http;

class ChronoTraceAlert extends Command
{
    protected $signature = 'chronotrace:alert';
    protected $description = 'Check for recent errors and send alerts';

    public function handle(TraceStorage $storage)
    {
        $traces = $storage->list();
        $recentTraces = array_slice($traces, 0, 10);
        
        $errorCount = 0;
        $slowCount = 0;
        
        foreach ($recentTraces as $trace) {
            $traceData = $storage->retrieve($trace['trace_id']);
            
            if ($traceData->response->status >= 500) {
                $errorCount++;
            }
            
            if ($traceData->response->duration > 5000) {
                $slowCount++;
            }
        }
        
        if ($errorCount > 0 || $slowCount > 0) {
            $this->sendSlackAlert($errorCount, $slowCount);
        }
    }
    
    private function sendSlackAlert(int $errors, int $slow): void
    {
        $message = "🚨 ChronoTrace Alert\n";
        $message .= "Errors in last 10 requests: {$errors}\n";
        $message .= "Slow requests (>5s): {$slow}\n";
        $message .= "Check traces with: `php artisan chronotrace:list`";
        
        Http::post(config('services.slack.webhook_url'), [
            'text' => $message,
        ]);
    }
}
```

## Dashboard Integration

### Metrics Collection

```php
// app/Services/ChronoTraceMetrics.php
<?php

namespace App\Services;

use Grazulex\LaravelChronotrace\Storage\TraceStorage;
use Carbon\Carbon;

class ChronoTraceMetrics
{
    public function __construct(private TraceStorage $storage)
    {
    }
    
    public function getDailyMetrics(): array
    {
        $traces = $this->storage->list();
        $today = Carbon::today();
        
        $metrics = [
            'total_traces' => 0,
            'error_count' => 0,
            'average_duration' => 0,
            'slow_requests' => 0,
            'external_failures' => 0,
        ];
        
        $totalDuration = 0;
        
        foreach ($traces as $trace) {
            $traceData = $this->storage->retrieve($trace['trace_id']);
            $traceDate = Carbon::createFromTimestamp($trace['created_at']);
            
            if (!$traceDate->isSameDay($today)) {
                continue;
            }
            
            $metrics['total_traces']++;
            $totalDuration += $traceData->response->duration;
            
            if ($traceData->response->status >= 500) {
                $metrics['error_count']++;
            }
            
            if ($traceData->response->duration > 5000) {
                $metrics['slow_requests']++;
            }
            
            // Count HTTP failures
            foreach ($traceData->http as $httpEvent) {
                if (str_contains($httpEvent['type'], 'failed') || 
                    ($httpEvent['status'] ?? 200) >= 400) {
                    $metrics['external_failures']++;
                }
            }
        }
        
        if ($metrics['total_traces'] > 0) {
            $metrics['average_duration'] = $totalDuration / $metrics['total_traces'];
        }
        
        return $metrics;
    }
}
```

### API Endpoint for Metrics

```php
// routes/api.php
Route::get('/metrics/chronotrace', function (ChronoTraceMetrics $metrics) {
    return response()->json($metrics->getDailyMetrics());
})->middleware(['auth:api', 'admin']);
```

### Grafana Dashboard

Create a Grafana dashboard that queries your metrics API:

```json
{
  "dashboard": {
    "title": "ChronoTrace Monitoring",
    "panels": [
      {
        "title": "Error Rate",
        "type": "stat",
        "targets": [
          {
            "url": "https://yourapp.com/api/metrics/chronotrace",
            "jsonPath": "$.error_count"
          }
        ]
      },
      {
        "title": "Average Response Time",
        "type": "stat",
        "targets": [
          {
            "url": "https://yourapp.com/api/metrics/chronotrace",
            "jsonPath": "$.average_duration"
          }
        ]
      }
    ]
  }
}
```

## Incident Response

### When Alerts Fire

**1. Initial Assessment:**
```bash
# Quick overview of recent activity
php artisan chronotrace:list --limit=10

# Check if this is a pattern or isolated incident
php artisan chronotrace:list --limit=50 | grep "$(date +%Y-%m-%d)"
```

**2. Error Analysis:**
```bash
# Get the most recent error trace
TRACE_ID=$(php artisan chronotrace:list --limit=1 | tail -1 | awk '{print $2}')

# Full analysis
php artisan chronotrace:replay $TRACE_ID

# Focus on likely issues
php artisan chronotrace:replay $TRACE_ID --db --http
```

**3. Root Cause Investigation:**

Database issues:
```bash
php artisan chronotrace:replay $TRACE_ID --db | grep -E "(ROLLBACK|Failed|Error|timeout)"
```

External service issues:
```bash
php artisan chronotrace:replay $TRACE_ID --http | grep -E "(Failed|5[0-9][0-9]|timeout)"
```

**4. Document and Fix:**
```bash
# Save trace for later analysis
php artisan chronotrace:replay $TRACE_ID > incident-$(date +%Y%m%d-%H%M).log

# After fix, capture a successful trace for comparison
php artisan chronotrace:record /same-endpoint-that-failed
```

### Incident Runbook

1. **Receive Alert** → Check ChronoTrace dashboard
2. **List Recent Traces** → `php artisan chronotrace:list --limit=20`
3. **Analyze Error Trace** → `php artisan chronotrace:replay {trace-id}`
4. **Identify Root Cause** → Use filtered views (--db, --http, --jobs)
5. **Apply Fix** → Based on findings
6. **Verify Fix** → Record new trace of same endpoint
7. **Document** → Update runbook with new learnings

## Long-term Monitoring

### Weekly Reports

```bash
#!/bin/bash
# weekly-report.sh

echo "=== Weekly ChronoTrace Report ==="
echo "Week ending: $(date)"
echo

# Count total traces
TOTAL=$(php artisan chronotrace:list --limit=10000 | wc -l)
echo "Total traces this week: $TOTAL"

# Calculate error rate
ERRORS=$(php artisan chronotrace:list --limit=1000 | while read line; do
    trace_id=$(echo $line | awk '{print $2}')
    php artisan chronotrace:replay $trace_id 2>/dev/null | grep "Response Status: 5[0-9][0-9]" && echo "error"
done | wc -l)

if [ $TOTAL -gt 0 ]; then
    ERROR_RATE=$(echo "scale=2; $ERRORS * 100 / $TOTAL" | bc)
    echo "Error rate: ${ERROR_RATE}%"
fi

echo
echo "=== Top Issues ==="
echo "1. Check for repeated error patterns"
echo "2. Review external service reliability"
echo "3. Monitor database performance trends"
echo "4. Analyze queue job failure rates"

# Cleanup recommendation
echo
echo "=== Maintenance ==="
echo "Consider running: php artisan chronotrace:purge --days=30 --confirm"
```

### Trend Analysis

```php
// Track metrics over time
class ChronoTraceTrends
{
    public function getWeeklyTrends(): array
    {
        $weeks = [];
        
        for ($i = 0; $i < 4; $i++) {
            $startDate = Carbon::now()->subWeeks($i + 1)->startOfWeek();
            $endDate = Carbon::now()->subWeeks($i)->endOfWeek();
            
            $weeks[] = [
                'week' => $startDate->format('Y-m-d'),
                'metrics' => $this->getMetricsForPeriod($startDate, $endDate),
            ];
        }
        
        return $weeks;
    }
    
    private function getMetricsForPeriod(Carbon $start, Carbon $end): array
    {
        // Calculate metrics for the period
        return [
            'total_requests' => 0,
            'error_rate' => 0,
            'avg_duration' => 0,
            'external_failures' => 0,
        ];
    }
}
```

## Best Practices for Production

1. **Start Conservative**: Begin with `record_on_error` mode
2. **Monitor Storage**: Keep an eye on storage costs and retention
3. **Automate Analysis**: Create scripts for common investigation patterns
4. **Set Up Alerts**: Don't wait for users to report issues
5. **Regular Reviews**: Weekly review of patterns and trends
6. **Document Learnings**: Build a knowledge base from trace analysis
7. **Test Recovery**: Practice incident response procedures

## Security Considerations

### PII in Production Traces

Ensure comprehensive scrubbing:

```php
'scrub' => [
    'password', 'token', 'secret', 'key', 'authorization',
    'cookie', 'session', 'credit_card', 'ssn', 'email',
    'phone', 'address', 'ip_address', 'user_agent',
    // Add application-specific fields
    'internal_id', 'customer_number', 'account_id',
],
```

### Access Control

Restrict access to production traces:

```php
// Only allow specific users/roles to access traces
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/traces', [TraceController::class, 'index']);
});
```

### Audit Trail

Log trace access:

```php
class TraceAccessMiddleware
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        
        if ($request->is('*/traces/*')) {
            Log::channel('audit')->info('Trace accessed', [
                'user_id' => auth()->id(),
                'trace_id' => $request->route('trace'),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }
        
        return $response;
    }
}
```

## Next Steps

- [Set up custom storage for your infrastructure](custom-storage.md)
- [Create development workflows](development-workflow.md)
- [Review security best practices](../docs/security.md)