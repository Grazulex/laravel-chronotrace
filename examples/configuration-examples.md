# Configuration Examples for Laravel ChronoTrace

This guide provides practical configuration examples for different environments, use cases, and deployment scenarios.

## Environment-Specific Configurations

### Development Environment

**Goal**: Maximum debugging information with immediate feedback

```php
// config/chronotrace.php for development
return [
    'enabled' => true,
    'mode' => 'always',           // Capture everything for debugging
    'storage' => 'local',
    'path' => storage_path('chronotrace'),
    'retention_days' => 7,        // Short retention to save disk space
    'debug' => true,              // Enable debug logging
    
    'capture' => [
        'database' => true,
        'cache' => true,
        'http' => true,
        'jobs' => true,
        'events' => true,         // Enable for full debugging visibility
        'request' => true,
        'response' => true,
        'filesystem' => true,     // Include file operations for debugging
    ],
    
    'compression' => [
        'enabled' => false,       // Disable for faster writes and easier reading
    ],
    
    'async_storage' => false,     // Synchronous for immediate availability
    
    'scrub' => [
        'password',
        'token',
        'secret',                 // Minimal scrubbing for easier debugging
    ],
];
```

**Environment Variables for Development:**
```env
# Basic Configuration
CHRONOTRACE_ENABLED=true
CHRONOTRACE_MODE=always
CHRONOTRACE_STORAGE=local
CHRONOTRACE_RETENTION_DAYS=7
CHRONOTRACE_DEBUG=true

# Capture Settings
CHRONOTRACE_CAPTURE_DATABASE=true
CHRONOTRACE_CAPTURE_CACHE=true
CHRONOTRACE_CAPTURE_HTTP=true
CHRONOTRACE_CAPTURE_JOBS=true
CHRONOTRACE_CAPTURE_EVENTS=true

# Performance Settings
CHRONOTRACE_ASYNC_STORAGE=false
```

### Staging Environment

**Goal**: Representative production testing with moderate capture

```php
// config/chronotrace.php for staging
return [
    'enabled' => true,
    'mode' => 'sample',           // Sample requests for performance testing
    'sample_rate' => 0.05,        // 5% of requests
    'storage' => 's3',            // Use S3 to match production
    'retention_days' => 15,
    'debug' => false,
    
    'capture' => [
        'database' => true,
        'cache' => true,
        'http' => true,
        'jobs' => true,
        'events' => false,        // Disable verbose events
        'request' => true,
        'response' => true,
        'filesystem' => false,    // Disable for performance
    ],
    
    'compression' => [
        'enabled' => true,
        'level' => 6,
    ],
    
    'async_storage' => true,      // Test async behavior
    'queue_connection' => 'redis',
    
    's3' => [
        'bucket' => 'staging-chronotrace',
        'region' => 'us-east-1',
        'path_prefix' => 'staging/traces',
    ],
    
    'scrub' => [
        'password', 'token', 'secret', 'key', 'authorization',
        'cookie', 'session', 'credit_card', 'ssn', 'email', 'phone',
    ],
];
```

**Environment Variables for Staging:**
```env
# Basic Configuration
CHRONOTRACE_ENABLED=true
CHRONOTRACE_MODE=sample
CHRONOTRACE_SAMPLE_RATE=0.05
CHRONOTRACE_STORAGE=s3
CHRONOTRACE_RETENTION_DAYS=15

# S3 Configuration
CHRONOTRACE_S3_BUCKET=staging-chronotrace
CHRONOTRACE_S3_REGION=us-east-1
CHRONOTRACE_S3_PREFIX=staging/traces

# Performance Configuration
CHRONOTRACE_ASYNC_STORAGE=true
CHRONOTRACE_QUEUE_CONNECTION=redis
CHRONOTRACE_QUEUE=chronotrace-staging
```

### Production Environment

**Goal**: Minimal performance impact with error capture only

```php
// config/chronotrace.php for production
return [
    'enabled' => true,
    'mode' => 'record_on_error',  // Only capture on 5xx errors
    'storage' => 's3',
    'retention_days' => 30,
    'debug' => false,
    
    'capture' => [
        'database' => true,
        'cache' => true,
        'http' => true,
        'jobs' => true,
        'events' => false,        // Disabled for performance
        'request' => true,
        'response' => true,
        'filesystem' => false,    // Disabled for performance
    ],
    
    'compression' => [
        'enabled' => true,
        'level' => 6,             // Good compression/speed balance
        'max_payload_size' => 1024 * 1024, // 1MB
    ],
    
    'async_storage' => true,      // Essential for production
    'queue_connection' => 'redis',
    'queue_fallback' => true,     // Fallback to sync if queue fails
    
    's3' => [
        'bucket' => 'production-chronotrace',
        'region' => 'us-east-1',
        'path_prefix' => 'production/traces',
    ],
    
    'scrub' => [
        'password', 'token', 'secret', 'key', 'authorization',
        'cookie', 'session', 'credit_card', 'ssn', 'email', 'phone',
        'address', 'ip_address', 'user_agent',
        // Add application-specific sensitive fields
        'internal_id', 'customer_number', 'account_id',
    ],
];
```

**Environment Variables for Production:**
```env
# Basic Configuration
CHRONOTRACE_ENABLED=true
CHRONOTRACE_MODE=record_on_error
CHRONOTRACE_STORAGE=s3
CHRONOTRACE_RETENTION_DAYS=30
CHRONOTRACE_DEBUG=false

# S3 Configuration
CHRONOTRACE_S3_BUCKET=production-chronotrace
CHRONOTRACE_S3_REGION=us-east-1
CHRONOTRACE_S3_PREFIX=production/traces

# Performance Configuration
CHRONOTRACE_ASYNC_STORAGE=true
CHRONOTRACE_QUEUE_CONNECTION=redis
CHRONOTRACE_QUEUE=chronotrace-prod
CHRONOTRACE_QUEUE_FALLBACK=true

# Event Capture (production optimized)
CHRONOTRACE_CAPTURE_DATABASE=true
CHRONOTRACE_CAPTURE_CACHE=true
CHRONOTRACE_CAPTURE_HTTP=true
CHRONOTRACE_CAPTURE_JOBS=true
CHRONOTRACE_CAPTURE_EVENTS=false
```

## Use Case-Specific Configurations

### High-Traffic Application

**Configuration for applications with > 1M requests/day:**

```php
return [
    'enabled' => true,
    'mode' => 'targeted',         // Only capture specific routes
    'storage' => 's3',
    'retention_days' => 15,       // Shorter retention for cost
    
    'targets' => [
        'routes' => [
            'checkout/*',          // Critical business flows only
            'payment/*',
            'api/orders/*',
            'admin/reports/*',
        ],
        'jobs' => [
            'ProcessPayment',
            'SendOrderConfirmation',
            'GenerateReport',
        ],
    ],
    
    'capture' => [
        'database' => true,
        'cache' => false,         // Disable cache capture for performance
        'http' => true,           // Keep external API monitoring
        'jobs' => true,
        'events' => false,
        'filesystem' => false,
    ],
    
    'compression' => [
        'enabled' => true,
        'level' => 9,             // Maximum compression for cost savings
    ],
    
    'async_storage' => true,
    'queue_connection' => 'redis',
];
```

### API-Only Application

**Configuration for REST API or microservice:**

```php
return [
    'enabled' => true,
    'mode' => 'sample',
    'sample_rate' => 0.01,        // 1% sampling for API monitoring
    'storage' => 's3',
    'retention_days' => 20,
    
    'capture' => [
        'database' => true,
        'cache' => true,
        'http' => true,           // Critical for API dependencies
        'jobs' => true,
        'events' => false,
        'request' => true,        // Important for API debugging
        'response' => true,       // Important for API debugging
    ],
    
    'compression' => [
        'enabled' => true,
        'level' => 6,
    ],
    
    // Enhanced scrubbing for API
    'scrub' => [
        'password', 'token', 'secret', 'key', 'authorization',
        'api_key', 'bearer', 'access_token', 'refresh_token',
        'client_secret', 'webhook_secret',
    ],
];
```

### E-commerce Application

**Configuration optimized for e-commerce platforms:**

```php
return [
    'enabled' => true,
    'mode' => 'targeted',
    'storage' => 's3',
    'retention_days' => 30,       // Longer retention for transaction analysis
    
    'targets' => [
        'routes' => [
            'cart/*',
            'checkout/*',
            'payment/*',
            'orders/*',
            'api/products/*',
            'admin/orders/*',
            'admin/payments/*',
        ],
        'jobs' => [
            'ProcessPayment',
            'SendOrderConfirmation',
            'ProcessRefund',
            'UpdateInventory',
            'SendShippingNotification',
        ],
    ],
    
    'capture' => [
        'database' => true,
        'cache' => true,          // Important for product/inventory caching
        'http' => true,           // Payment gateways, shipping APIs
        'jobs' => true,           // Order processing workflows
        'events' => false,
        'request' => true,
        'response' => true,
    ],
    
    // E-commerce specific scrubbing
    'scrub' => [
        'password', 'token', 'secret', 'key', 'authorization',
        'credit_card', 'cvv', 'card_number', 'expiry',
        'ssn', 'tax_id', 'bank_account',
        'shipping_address', 'billing_address',
        'customer_phone', 'customer_email',
    ],
];
```

### Multi-Tenant Application

**Configuration for SaaS applications with multiple tenants:**

```php
return [
    'enabled' => true,
    'mode' => 'record_on_error',
    'storage' => 's3',
    'retention_days' => 25,
    
    'capture' => [
        'database' => true,
        'cache' => true,
        'http' => true,
        'jobs' => true,
        'events' => false,
        'request' => true,
        'response' => true,
    ],
    
    's3' => [
        'bucket' => 'multitenant-chronotrace',
        'region' => 'us-east-1',
        'path_prefix' => 'traces/{tenant_id}', // Separate by tenant
    ],
    
    // Enhanced tenant data scrubbing
    'scrub' => [
        'password', 'token', 'secret', 'key', 'authorization',
        'tenant_id', 'organization_id', 'company_id',
        'customer_data', 'user_data', 'personal_info',
        'email', 'phone', 'address',
    ],
];
```

## Storage-Specific Configurations

### Local Storage (Development/Small Teams)

```php
return [
    'storage' => 'local',
    'path' => storage_path('chronotrace'),
    'retention_days' => 7,
    
    'compression' => [
        'enabled' => false,       // Faster writes for development
    ],
    
    'async_storage' => false,     // Immediate availability
];
```

### S3 Storage (Production)

```php
return [
    'storage' => 's3',
    
    's3' => [
        'bucket' => env('CHRONOTRACE_S3_BUCKET', 'chronotrace'),
        'region' => env('CHRONOTRACE_S3_REGION', 'us-east-1'),
        'endpoint' => env('CHRONOTRACE_S3_ENDPOINT'), // For custom S3 providers
        'path_prefix' => env('CHRONOTRACE_S3_PREFIX', 'traces'),
    ],
    
    'compression' => [
        'enabled' => true,
        'level' => 6,
    ],
    
    'async_storage' => true,
];
```

**S3 Environment Variables:**
```env
# AWS S3 Configuration
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
CHRONOTRACE_S3_BUCKET=your-chronotrace-bucket
CHRONOTRACE_S3_REGION=us-east-1
CHRONOTRACE_S3_PREFIX=traces
```

### MinIO Storage (Self-Hosted S3)

```php
return [
    'storage' => 's3',
    
    's3' => [
        'bucket' => env('CHRONOTRACE_S3_BUCKET', 'chronotrace'),
        'region' => env('CHRONOTRACE_S3_REGION', 'us-east-1'),
        'endpoint' => env('CHRONOTRACE_S3_ENDPOINT', 'https://minio.yourcompany.com'),
        'path_prefix' => env('CHRONOTRACE_S3_PREFIX', 'traces'),
    ],
];
```

**MinIO Environment Variables:**
```env
# MinIO Configuration
AWS_ACCESS_KEY_ID=minio-access-key
AWS_SECRET_ACCESS_KEY=minio-secret-key
CHRONOTRACE_S3_BUCKET=chronotrace
CHRONOTRACE_S3_REGION=us-east-1
CHRONOTRACE_S3_ENDPOINT=https://minio.yourcompany.com
CHRONOTRACE_S3_PREFIX=traces
```

## Performance Optimization Configurations

### High Performance (Minimal Overhead)

```php
return [
    'enabled' => true,
    'mode' => 'record_on_error',  // Minimal capture
    'storage' => 's3',
    
    'capture' => [
        'database' => true,       // Essential for debugging
        'cache' => false,         // Disable for performance
        'http' => true,           // Keep external monitoring
        'jobs' => false,          // Disable if not critical
        'events' => false,
        'filesystem' => false,
    ],
    
    'compression' => [
        'enabled' => true,
        'level' => 9,             // Maximum compression
    ],
    
    'async_storage' => true,      // Essential
    'queue_connection' => 'redis',
    'queue_fallback' => false,    // Don't fallback to sync
];
```

### Debugging Focus (Maximum Information)

```php
return [
    'enabled' => true,
    'mode' => 'always',           // Capture everything
    'storage' => 'local',
    
    'capture' => [
        'database' => true,
        'cache' => true,
        'http' => true,
        'jobs' => true,
        'events' => true,         // Full event capture
        'request' => true,
        'response' => true,
        'filesystem' => true,
    ],
    
    'compression' => [
        'enabled' => false,       // No compression for easier debugging
    ],
    
    'async_storage' => false,     // Immediate availability
    'debug' => true,
    
    'scrub' => [
        'password',               // Minimal scrubbing
    ],
];
```

## Security-Focused Configurations

### Maximum Security (Production)

```php
return [
    'enabled' => true,
    'mode' => 'record_on_error',
    'storage' => 's3',
    
    // Comprehensive PII scrubbing
    'scrub' => [
        // Authentication & Authorization
        'password', 'token', 'secret', 'key', 'authorization',
        'api_key', 'bearer', 'access_token', 'refresh_token',
        'client_secret', 'webhook_secret', 'session_id',
        
        // Personal Information
        'email', 'phone', 'ssn', 'tax_id', 'passport',
        'driver_license', 'national_id', 'social_security',
        
        // Financial Information
        'credit_card', 'cvv', 'card_number', 'expiry',
        'bank_account', 'routing_number', 'iban',
        
        // Location Information
        'address', 'street', 'city', 'postal_code',
        'latitude', 'longitude', 'ip_address',
        
        // Business Information
        'customer_id', 'account_id', 'user_id',
        'internal_id', 'reference_number',
        
        // Technical Information
        'user_agent', 'device_id', 'mac_address',
    ],
    
    'capture' => [
        'database' => true,
        'cache' => true,
        'http' => true,
        'jobs' => true,
        'events' => false,
        'request' => false,       // Disable request capture for security
        'response' => false,      // Disable response capture for security
        'filesystem' => false,
    ],
];
```

### Development Security (Balanced)

```php
return [
    'enabled' => true,
    'mode' => 'always',
    'storage' => 'local',
    
    'scrub' => [
        'password', 'token', 'secret', 'key',
        'credit_card', 'ssn', 'bank_account',
        // Allow emails and phones for debugging
    ],
    
    'capture' => [
        'database' => true,
        'cache' => true,
        'http' => true,
        'jobs' => true,
        'events' => true,
        'request' => true,        // Allow for debugging
        'response' => true,       // Allow for debugging
    ],
];
```

## Testing Configuration Examples

### Test Environment Configuration

```php
// config/chronotrace.php for testing
return [
    'enabled' => env('CHRONOTRACE_TEST_ENABLED', false), // Disabled by default
    'mode' => 'always',
    'storage' => 'local',
    'path' => storage_path('chronotrace/tests'),
    'retention_days' => 1,        // Very short retention
    
    'capture' => [
        'database' => true,
        'cache' => true,
        'http' => false,          // Disable external calls in tests
        'jobs' => true,
        'events' => false,
    ],
    
    'compression' => [
        'enabled' => false,       // Faster for tests
    ],
    
    'async_storage' => false,     // Synchronous for testing
    'debug' => false,
];
```

### CI/CD Configuration

```yaml
# .env.testing for CI/CD
CHRONOTRACE_ENABLED=true
CHRONOTRACE_MODE=always
CHRONOTRACE_STORAGE=local
CHRONOTRACE_RETENTION_DAYS=1
CHRONOTRACE_ASYNC_STORAGE=false
CHRONOTRACE_CAPTURE_HTTP=false
CHRONOTRACE_DEBUG=false
```

## Validation Commands

After configuring ChronoTrace, validate your setup:

```bash
# Validate configuration
php artisan chronotrace:diagnose

# Test middleware setup
php artisan chronotrace:test-middleware

# Test recording
php artisan chronotrace:record /test

# Check trace storage
php artisan chronotrace:list
```

## Environment Detection

Use conditional configuration for automatic environment detection:

```php
// config/chronotrace.php
$baseConfig = [
    'enabled' => env('CHRONOTRACE_ENABLED', true),
    // ... base configuration
];

// Environment-specific overrides
if (app()->environment('production')) {
    return array_merge($baseConfig, [
        'mode' => 'record_on_error',
        'storage' => 's3',
        'async_storage' => true,
        'debug' => false,
    ]);
}

if (app()->environment('staging')) {
    return array_merge($baseConfig, [
        'mode' => 'sample',
        'sample_rate' => 0.05,
        'storage' => 's3',
        'async_storage' => true,
    ]);
}

// Development/local defaults
return array_merge($baseConfig, [
    'mode' => 'always',
    'storage' => 'local',
    'async_storage' => false,
    'debug' => true,
]);
```

---

**Next Steps:**
- [Basic Usage Examples](basic-usage.md)
- [Production Monitoring](production-monitoring.md)
- [Development Workflow](development-workflow.md)
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