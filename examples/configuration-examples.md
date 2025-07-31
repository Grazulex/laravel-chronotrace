# Configuration Examples

This guide provides practical configuration examples for different environments and use cases.

## Environment Configurations

### Development Environment

**Goal**: Maximum debugging information with minimal performance impact

**Configuration:**
```php
// config/chronotrace.php
return [
    'enabled' => true,
    'mode' => 'always',           // Capture everything
    'storage' => 'local',
    'path' => storage_path('chronotrace'),
    'retention_days' => 7,        // Short retention for disk space
    'debug' => true,              // Enable debug logging
    
    'capture' => [
        'database' => true,
        'cache' => true,
        'http' => true,
        'jobs' => true,
        'events' => true,         // Enable for full debugging
    ],
    
    'compression' => [
        'enabled' => false,       // Disable for faster writes
    ],
    
    'async_storage' => false,     // Synchronous for immediate availability
    
    'scrub' => [
        'password',
        'token',
        'secret',                 // Minimal scrubbing for debugging
    ],
];
```

**Environment Variables:**
```env
CHRONOTRACE_ENABLED=true
CHRONOTRACE_MODE=always
CHRONOTRACE_STORAGE=local
CHRONOTRACE_RETENTION_DAYS=7
CHRONOTRACE_DEBUG=true
CHRONOTRACE_CAPTURE_EVENTS=true
```

### Staging Environment

**Goal**: Production-like setup with enhanced monitoring

**Configuration:**
```php
// config/chronotrace.php
return [
    'enabled' => true,
    'mode' => 'sample',           // Sample requests
    'sample_rate' => 0.01,        // 1% of requests
    'storage' => 's3',
    'retention_days' => 15,
    'debug' => false,
    
    's3' => [
        'bucket' => 'staging-chronotrace',
        'region' => 'us-east-1',
        'path_prefix' => 'traces',
    ],
    
    'capture' => [
        'database' => true,
        'cache' => true,
        'http' => true,
        'jobs' => true,
        'events' => false,        // Disabled for performance
    ],
    
    'compression' => [
        'enabled' => true,
        'level' => 6,
    ],
    
    'async_storage' => true,
    'queue_connection' => 'redis',
    'queue_name' => 'chronotrace',
    
    'scrub' => [
        'password',
        'token',
        'secret',
        'email',
        'phone',
        'credit_card',
    ],
];
```

**Environment Variables:**
```env
CHRONOTRACE_ENABLED=true
CHRONOTRACE_MODE=sample
CHRONOTRACE_SAMPLE_RATE=0.01
CHRONOTRACE_STORAGE=s3
CHRONOTRACE_S3_BUCKET=staging-chronotrace
CHRONOTRACE_S3_REGION=us-east-1
CHRONOTRACE_RETENTION_DAYS=15
```

### Production Environment

**Goal**: Error monitoring with minimal performance impact

**Configuration:**
```php
// config/chronotrace.php
return [
    'enabled' => true,
    'mode' => 'record_on_error',  // Only capture errors
    'storage' => 's3',
    'retention_days' => 30,
    'debug' => false,
    
    's3' => [
        'bucket' => 'prod-chronotrace',
        'region' => 'us-west-2',
        'path_prefix' => 'traces',
        'server_side_encryption' => 'AES256',
    ],
    
    'capture' => [
        'database' => true,
        'cache' => false,         // Disabled for performance
        'http' => true,           // Keep for external dependencies
        'jobs' => true,
        'events' => false,
    ],
    
    'compression' => [
        'enabled' => true,
        'level' => 9,             // Maximum compression
    ],
    
    'async_storage' => true,
    'queue_connection' => 'redis',
    'queue_name' => 'chronotrace',
    
    'scrub' => [
        'password',
        'token',
        'secret',
        'key',
        'authorization',
        'cookie',
        'session',
        'credit_card',
        'ssn',
        'email',
        'phone',
        'address',
    ],
    
    'auto_purge' => true,
];
```

**Environment Variables:**
```env
CHRONOTRACE_ENABLED=true
CHRONOTRACE_MODE=record_on_error
CHRONOTRACE_STORAGE=s3
CHRONOTRACE_S3_BUCKET=prod-chronotrace
CHRONOTRACE_S3_REGION=us-west-2
CHRONOTRACE_RETENTION_DAYS=30
CHRONOTRACE_AUTO_PURGE=true
```

## Use Case Configurations

### High-Traffic API Monitoring

**Scenario**: Monitor critical API endpoints without affecting performance

```php
return [
    'enabled' => true,
    'mode' => 'targeted',
    
    'targets' => [
        'routes' => [
            'api/v1/orders/*',
            'api/v1/payments/*',
            'api/v1/checkout/*',
        ],
    ],
    
    'capture' => [
        'database' => true,
        'cache' => false,         // Too verbose for high traffic
        'http' => true,           // Monitor external services
        'jobs' => true,
        'events' => false,
    ],
    
    'async_storage' => true,      // Essential for performance
    'compression' => ['enabled' => true, 'level' => 9],
    
    'scrub' => [
        'password', 'token', 'secret', 'credit_card', 'ssn',
        'email', 'phone', 'authorization', 'cookie',
    ],
];
```

### E-commerce Platform

**Scenario**: Monitor checkout flow and payment processing

```php
return [
    'enabled' => true,
    'mode' => 'targeted',
    
    'targets' => [
        'routes' => [
            'checkout/*',
            'payment/*',
            'orders/*',
        ],
        'jobs' => [
            'ProcessPayment',
            'SendOrderConfirmation',
            'UpdateInventory',
        ],
    ],
    
    'capture' => [
        'database' => true,       // Track order data
        'cache' => true,          // Monitor cart caching
        'http' => true,           // Payment gateway calls
        'jobs' => true,           // Order processing
        'events' => false,
    ],
    
    'scrub' => [
        'password', 'token', 'secret', 'credit_card', 'cvv',
        'ssn', 'bank_account', 'routing_number', 'email',
        'phone', 'billing_address', 'shipping_address',
    ],
];
```

### SaaS Application

**Scenario**: Monitor user actions and system performance

```php
return [
    'enabled' => true,
    'mode' => 'sample',
    'sample_rate' => 0.005,       // 0.5% sample rate
    
    'capture' => [
        'database' => true,
        'cache' => true,
        'http' => true,           // Third-party integrations
        'jobs' => true,           // Background processing
        'events' => false,
    ],
    
    'targets' => [
        'routes' => [
            'dashboard/*',
            'api/v1/reports/*',
            'api/v1/exports/*',
        ],
    ],
    
    'scrub' => [
        'password', 'token', 'secret', 'api_key',
        'webhook_secret', 'oauth_token', 'refresh_token',
        'email', 'phone', 'customer_data',
    ],
];
```

### Microservices Architecture

**Scenario**: Monitor service-to-service communication

```php
return [
    'enabled' => true,
    'mode' => 'record_on_error',
    
    'capture' => [
        'database' => true,
        'cache' => false,         // Each service has its own cache
        'http' => true,           // Critical for service communication
        'jobs' => true,
        'events' => false,
    ],
    
    'scrub' => [
        'password', 'token', 'secret', 'service_token',
        'internal_api_key', 'jwt_token', 'authorization',
    ],
    
    // Service-specific storage
    's3' => [
        'bucket' => 'chronotrace-' . env('SERVICE_NAME'),
        'path_prefix' => env('SERVICE_NAME') . '/traces',
    ],
];
```

## Storage Configurations

### AWS S3 with KMS Encryption

```php
's3' => [
    'bucket' => 'chronotrace-encrypted',
    'region' => 'us-east-1',
    'path_prefix' => 'traces',
    'server_side_encryption' => 'aws:kms',
    'kms_key_id' => 'arn:aws:kms:us-east-1:123456789:key/12345678-1234-1234-1234-123456789012',
],
```

### MinIO Self-Hosted

```php
's3' => [
    'bucket' => 'chronotrace',
    'region' => 'us-east-1',
    'endpoint' => 'https://minio.internal.company.com',
    'path_prefix' => 'traces',
    'use_ssl' => true,
],
```

**Environment Variables:**
```env
CHRONOTRACE_S3_ENDPOINT=https://minio.internal.company.com
CHRONOTRACE_S3_BUCKET=chronotrace
AWS_ACCESS_KEY_ID=minio_access_key
AWS_SECRET_ACCESS_KEY=minio_secret_key
```

### Local Storage with Network Mount

```php
'storage' => 'local',
'path' => '/mnt/shared/chronotrace',  // Network mounted storage

'compression' => [
    'enabled' => true,
    'level' => 6,                     // Compress for network efficiency
],
```

## Queue Configurations

### Redis Queue

```php
'async_storage' => true,
'queue_connection' => 'redis',
'queue_name' => 'chronotrace',
```

**config/queue.php:**
```php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'chronotrace',
        'queue' => 'chronotrace',
        'retry_after' => 90,
        'block_for' => null,
    ],
],
```

### Database Queue

```php
'async_storage' => true,
'queue_connection' => 'database',
'queue_name' => 'chronotrace',
```

### SQS Queue (AWS)

```php
'async_storage' => true,
'queue_connection' => 'sqs',
'queue_name' => 'chronotrace',
```

**config/queue.php:**
```php
'connections' => [
    'sqs' => [
        'driver' => 'sqs',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'prefix' => 'https://sqs.us-east-1.amazonaws.com/123456789',
        'queue' => 'chronotrace',
        'region' => 'us-east-1',
    ],
],
```

## Performance Optimization Configurations

### High-Performance Setup

```php
return [
    'async_storage' => true,
    'compression' => [
        'enabled' => true,
        'level' => 6,               // Balance compression vs CPU
        'max_payload_size' => 512 * 1024, // 512KB threshold
    ],
    
    'capture' => [
        'database' => true,
        'cache' => false,           // Disable verbose events
        'http' => true,
        'jobs' => true,
        'events' => false,
    ],
    
    'mode' => 'record_on_error',    // Minimal overhead
];
```

### Memory-Constrained Environment

```php
return [
    'compression' => [
        'enabled' => true,
        'level' => 9,               // Maximum compression
        'max_payload_size' => 256 * 1024, // Lower threshold
    ],
    
    'retention_days' => 7,          // Shorter retention
    'auto_purge' => true,
    
    'capture' => [
        'database' => true,
        'cache' => false,
        'http' => true,
        'jobs' => false,            // Disable to save memory
        'events' => false,
    ],
];
```

## Multi-Environment Configuration

### Using Environment Variables

**.env.example:**
```env
# ChronoTrace Configuration
CHRONOTRACE_ENABLED=true
CHRONOTRACE_MODE=record_on_error
CHRONOTRACE_SAMPLE_RATE=0.001
CHRONOTRACE_STORAGE=local
CHRONOTRACE_S3_BUCKET=
CHRONOTRACE_S3_REGION=us-east-1
CHRONOTRACE_RETENTION_DAYS=15
CHRONOTRACE_DEBUG=false
```

**.env.local:**
```env
CHRONOTRACE_MODE=always
CHRONOTRACE_DEBUG=true
CHRONOTRACE_RETENTION_DAYS=7
CHRONOTRACE_CAPTURE_EVENTS=true
```

**.env.production:**
```env
CHRONOTRACE_MODE=record_on_error
CHRONOTRACE_STORAGE=s3
CHRONOTRACE_S3_BUCKET=prod-chronotrace
CHRONOTRACE_RETENTION_DAYS=30
CHRONOTRACE_DEBUG=false
```

### Environment-Specific Config Files

**config/chronotrace/local.php:**
```php
<?php
return [
    'mode' => 'always',
    'debug' => true,
    'capture' => ['events' => true],
];
```

**config/chronotrace/production.php:**
```php
<?php
return [
    'mode' => 'record_on_error',
    'storage' => 's3',
    'debug' => false,
    'capture' => ['events' => false],
];
```

**Main config file:**
```php
<?php
$config = [
    // Base configuration
    'enabled' => env('CHRONOTRACE_ENABLED', true),
    // ... other settings
];

// Load environment-specific overrides
$envConfig = config('chronotrace.' . app()->environment(), []);
return array_merge($config, $envConfig);
```

## Testing Configurations

Test your configuration:

```bash
# Verify configuration is loaded correctly
php artisan config:show chronotrace

# Test storage connectivity
php artisan chronotrace:list

# Test recording
php artisan chronotrace:record /test-endpoint

# Check queue processing (if async enabled)
php artisan queue:work --queue=chronotrace --once
```

## Next Steps

- [Learn about basic usage](basic-usage.md)
- [Explore event filtering examples](event-filtering.md)
- [Set up production monitoring](production-monitoring.md)