# API Reference

This guide provides reference documentation for Laravel ChronoTrace's programmatic API.

## Service Classes

### TraceRecorder

The main service for recording traces programmatically.

```php
use Grazulex\LaravelChronotrace\Services\TraceRecorder;

$recorder = app(TraceRecorder::class);
```

#### Methods

**`startRecording(): void`**

Start recording a new trace.

```php
$recorder->startRecording();
```

**`stopRecording(): ?string`**

Stop recording and return the trace ID.

```php
$traceId = $recorder->stopRecording();
```

**`isRecording(): bool`**

Check if currently recording.

```php
if ($recorder->isRecording()) {
    // Recording is active
}
```

**`captureEvent(string $type, array $data): void`**

Manually capture a custom event.

```php
$recorder->captureEvent('custom', [
    'action' => 'user_login',
    'user_id' => 123,
    'timestamp' => microtime(true),
]);
```

### TraceStorage

Interface for storing and retrieving traces.

```php
use Grazulex\LaravelChronotrace\Storage\TraceStorage;

$storage = app(TraceStorage::class);
```

#### Methods

**`store(string $traceId, array $data): bool`**

Store a trace.

```php
$success = $storage->store($traceId, $traceData);
```

**`retrieve(string $traceId): ?TraceData`**

Retrieve a trace by ID.

```php
$trace = $storage->retrieve($traceId);
```

**`list(): array`**

List all stored traces.

```php
$traces = $storage->list();
```

**`purgeOldTraces(int $days): int`**

Remove traces older than specified days.

```php
$deletedCount = $storage->purgeOldTraces(30);
```

### PIIScrubber

Service for scrubbing sensitive data.

```php
use Grazulex\LaravelChronotrace\Services\PIIScrubber;

$scrubber = app(PIIScrubber::class);
```

#### Methods

**`scrub(array $data): array`**

Scrub sensitive data from an array.

```php
$cleanData = $scrubber->scrub([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => 'secret123',
]);

// Result: ['name' => 'John Doe', 'email' => '[SCRUBBED]', 'password' => '[SCRUBBED]']
```

**`addPattern(string $pattern, string $replacement): void`**

Add a custom scrubbing pattern.

```php
$scrubber->addPattern('/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/', '[CREDIT_CARD]');
```

## Model Classes

### TraceData

Main trace data model.

```php
use Grazulex\LaravelChronotrace\Models\TraceData;

$trace = new TraceData([
    'traceId' => 'abc123',
    'timestamp' => now(),
    'environment' => 'production',
    'request' => $requestData,
    'response' => $responseData,
    'database' => $databaseEvents,
    'cache' => $cacheEvents,
    'http' => $httpEvents,
    'jobs' => $jobEvents,
]);
```

#### Properties

- **`string $traceId`** - Unique trace identifier
- **`string $timestamp`** - When the trace was recorded
- **`string $environment`** - Application environment
- **`TraceRequest $request`** - Request information
- **`TraceResponse $response`** - Response information
- **`array $database`** - Database events
- **`array $cache`** - Cache events
- **`array $http`** - HTTP events
- **`array $jobs`** - Queue job events

### TraceRequest

Request information model.

```php
use Grazulex\LaravelChronotrace\Models\TraceRequest;

$request = new TraceRequest([
    'method' => 'GET',
    'url' => 'https://example.com/api/users',
    'headers' => $headers,
    'body' => $body,
    'query' => $queryParams,
]);
```

#### Properties

- **`string $method`** - HTTP method
- **`string $url`** - Request URL
- **`array $headers`** - Request headers (PII scrubbed)
- **`mixed $body`** - Request body (PII scrubbed)
- **`array $query`** - Query parameters (PII scrubbed)

### TraceResponse

Response information model.

```php
use Grazulex\LaravelChronotrace\Models\TraceResponse;

$response = new TraceResponse([
    'status' => 200,
    'headers' => $headers,
    'body' => $body,
    'duration' => 245,
    'memoryUsage' => 18874368,
]);
```

#### Properties

- **`int $status`** - HTTP status code
- **`array $headers`** - Response headers
- **`mixed $body`** - Response body (PII scrubbed)
- **`float $duration`** - Response time in milliseconds
- **`int $memoryUsage`** - Memory usage in bytes

## Event Listeners

### DatabaseEventListener

Captures database-related events.

**Events Listened:**
- `Illuminate\Database\Events\QueryExecuted`
- `Illuminate\Database\Events\TransactionBeginning`
- `Illuminate\Database\Events\TransactionCommitted`
- `Illuminate\Database\Events\TransactionRolledBack`

**Captured Data:**
```php
[
    'type' => 'query',
    'sql' => 'SELECT * FROM users WHERE id = ?',
    'bindings' => [1],
    'time' => 15.23,
    'connection' => 'mysql',
    'timestamp' => 1642266622.123,
]
```

### CacheEventListener

Captures cache-related events.

**Events Listened:**
- `Illuminate\Cache\Events\CacheHit`
- `Illuminate\Cache\Events\CacheMissed`
- `Illuminate\Cache\Events\KeyWritten`
- `Illuminate\Cache\Events\KeyForgotten`

**Captured Data:**
```php
[
    'type' => 'hit',
    'key' => 'users:1',
    'value_size' => 1024,
    'store' => 'redis',
    'timestamp' => 1642266622.123,
]
```

### HttpEventListener

Captures HTTP client events.

**Events Listened:**
- HTTP request events
- HTTP response events
- Connection failure events

**Captured Data:**
```php
[
    'type' => 'request_sending',
    'method' => 'GET',
    'url' => 'https://api.external.com/data',
    'headers' => $scrubbed_headers,
    'body_size' => 256,
    'timestamp' => 1642266622.123,
]
```

### QueueEventListener

Captures queue job events.

**Events Listened:**
- `Illuminate\Queue\Events\JobProcessing`
- `Illuminate\Queue\Events\JobProcessed`
- `Illuminate\Queue\Events\JobFailed`

**Captured Data:**
```php
[
    'type' => 'job_processing',
    'job_name' => 'App\\Jobs\\ProcessPayment',
    'queue' => 'default',
    'connection' => 'redis',
    'attempts' => 1,
    'timestamp' => 1642266622.123,
]
```

## Middleware

### ChronoTraceMiddleware

HTTP middleware for automatic trace recording.

**Usage:**
```php
// Global middleware
protected $middleware = [
    \Grazulex\LaravelChronotrace\Middleware\ChronoTraceMiddleware::class,
];

// Route middleware
Route::middleware(['chronotrace'])->group(function () {
    Route::get('/api/users', [UserController::class, 'index']);
});
```

**Configuration:**
The middleware respects the recording mode configuration:
- `always` - Records all requests
- `sample` - Records based on sample rate
- `record_on_error` - Records only on 5xx errors
- `targeted` - Records only targeted routes

## Jobs

### StoreTraceJob

Queue job for asynchronous trace storage.

```php
use Grazulex\LaravelChronotrace\Jobs\StoreTraceJob;

dispatch(new StoreTraceJob($traceId, $traceData));
```

**Properties:**
- **`string $traceId`** - Trace identifier
- **`array $traceData`** - Complete trace data

## Configuration API

### Accessing Configuration

```php
// Get full configuration
$config = config('chronotrace');

// Get specific values
$enabled = config('chronotrace.enabled');
$mode = config('chronotrace.mode');
$storage = config('chronotrace.storage');
```

### Runtime Configuration

```php
// Temporarily disable recording
config(['chronotrace.enabled' => false]);

// Change recording mode
config(['chronotrace.mode' => 'always']);

// Modify scrubbing rules
config(['chronotrace.scrub' => ['password', 'custom_field']]);
```

## Helper Functions

### Recording Helper

```php
// Manual recording wrapper
function recordTrace(callable $callback): ?string
{
    $recorder = app(TraceRecorder::class);
    
    $recorder->startRecording();
    
    try {
        $callback();
    } finally {
        return $recorder->stopRecording();
    }
}

// Usage
$traceId = recordTrace(function () {
    // Your code here
    User::create(['name' => 'John']);
});
```

### Storage Helper

```php
// Quick storage access
function getTrace(string $traceId): ?TraceData
{
    return app(TraceStorage::class)->retrieve($traceId);
}

function listTraces(): array
{
    return app(TraceStorage::class)->list();
}
```

## Events and Hooks

### Custom Event Capture

```php
// In your service provider
use Grazulex\LaravelChronotrace\Services\TraceRecorder;

$this->app->bind(TraceRecorder::class, function ($app) {
    $recorder = new TraceRecorder();
    
    // Add custom event capture
    $recorder->onEvent('user.login', function ($event) {
        return [
            'type' => 'user_login',
            'user_id' => $event->user->id,
            'ip_address' => request()->ip(),
            'timestamp' => microtime(true),
        ];
    });
    
    return $recorder;
});
```

### Storage Hooks

```php
// In your service provider
use Grazulex\LaravelChronotrace\Storage\TraceStorage;

$this->app->extend(TraceStorage::class, function ($storage) {
    // Add hooks
    $storage->onStore(function ($traceId, $data) {
        Log::info('Trace stored', ['trace_id' => $traceId]);
    });
    
    $storage->onRetrieve(function ($traceId) {
        Log::info('Trace accessed', ['trace_id' => $traceId]);
    });
    
    return $storage;
});
```

## Custom Implementations

### Custom Storage Driver

```php
use Grazulex\LaravelChronotrace\Storage\TraceStorage;

class DatabaseTraceStorage extends TraceStorage
{
    public function store(string $traceId, array $data): bool
    {
        return DB::table('chronotrace_traces')->insert([
            'trace_id' => $traceId,
            'data' => json_encode($data),
            'created_at' => now(),
        ]);
    }
    
    public function retrieve(string $traceId): ?TraceData
    {
        $record = DB::table('chronotrace_traces')
            ->where('trace_id', $traceId)
            ->first();
            
        return $record ? TraceData::fromArray(json_decode($record->data, true)) : null;
    }
    
    public function list(): array
    {
        return DB::table('chronotrace_traces')
            ->select('trace_id', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }
    
    public function purgeOldTraces(int $days): int
    {
        return DB::table('chronotrace_traces')
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }
}
```

### Custom Event Listener

```php
use Grazulex\LaravelChronotrace\Services\TraceRecorder;

class CustomEventListener
{
    public function __construct(private TraceRecorder $recorder)
    {
    }
    
    public function handle($event): void
    {
        if (!$this->recorder->isRecording()) {
            return;
        }
        
        $this->recorder->captureEvent('custom', [
            'event_type' => get_class($event),
            'data' => $this->extractEventData($event),
            'timestamp' => microtime(true),
        ]);
    }
    
    private function extractEventData($event): array
    {
        // Extract relevant data from your event
        return [];
    }
}
```

## Testing APIs

### Unit Testing

```php
use Grazulex\LaravelChronotrace\Services\TraceRecorder;

class RecorderTest extends TestCase
{
    public function test_can_start_and_stop_recording()
    {
        $recorder = app(TraceRecorder::class);
        
        $this->assertFalse($recorder->isRecording());
        
        $recorder->startRecording();
        $this->assertTrue($recorder->isRecording());
        
        $traceId = $recorder->stopRecording();
        $this->assertNotNull($traceId);
        $this->assertFalse($recorder->isRecording());
    }
}
```

### Integration Testing

```php
class ChronoTraceIntegrationTest extends TestCase
{
    public function test_middleware_captures_requests()
    {
        config(['chronotrace.mode' => 'always']);
        
        $this->get('/api/users');
        
        $traces = app(TraceStorage::class)->list();
        $this->assertNotEmpty($traces);
    }
}
```

## Error Handling

### Exception Classes

ChronoTrace uses standard Laravel exceptions. Common scenarios:

```php
try {
    $trace = app(TraceStorage::class)->retrieve($traceId);
} catch (\Exception $e) {
    // Handle storage errors
    Log::error('Failed to retrieve trace', [
        'trace_id' => $traceId,
        'error' => $e->getMessage(),
    ]);
}
```

### Graceful Degradation

ChronoTrace is designed to fail gracefully:

```php
// Recording failures don't affect application flow
try {
    $recorder->startRecording();
    // Your application logic
} catch (\Exception $e) {
    // ChronoTrace error - log but continue
    Log::warning('ChronoTrace recording failed', ['error' => $e->getMessage()]);
}
```

---

For more examples and usage patterns, see the [Examples](../examples/README.md) documentation.