# Security & PII Scrubbing

ChronoTrace includes comprehensive security features to protect sensitive data while capturing useful debugging information. This guide covers PII scrubbing, data protection, and security best practices.

## PII Scrubbing

### Default Scrubbed Fields

ChronoTrace automatically scrubs these fields by default:

```php
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
],
```

### How Scrubbing Works

**Before Scrubbing:**
```json
{
    "user": {
        "name": "John Doe",
        "email": "john@example.com",
        "password": "secret123",
        "api_token": "abc123xyz789"
    }
}
```

**After Scrubbing:**
```json
{
    "user": {
        "name": "John Doe",
        "email": "[SCRUBBED]",
        "password": "[SCRUBBED]",
        "api_token": "[SCRUBBED]"
    }
}
```

### Custom Scrubbing Configuration

**Add Additional Fields:**
```php
'scrub' => [
    'password',
    'token',
    'secret',
    // Add your custom fields
    'internal_id',
    'bank_account',
    'passport_number',
    'driver_license',
],
```

**Environment Variable Override:**
```env
CHRONOTRACE_SCRUB_FIELDS=password,token,secret,custom_field
```

### Advanced Scrubbing Rules

**Pattern-Based Scrubbing:**
```php
// In your service provider
use Grazulex\LaravelChronotrace\Services\PIIScrubber;

$this->app->extend(PIIScrubber::class, function ($scrubber) {
    // Add pattern-based scrubbing
    $scrubber->addPattern('/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/', '[CREDIT_CARD]');
    $scrubber->addPattern('/\b\d{3}-\d{2}-\d{4}\b/', '[SSN]');
    
    return $scrubber;
});
```

## Data Protection

### Encryption at Rest

**S3 Encryption:**
```php
's3' => [
    'bucket' => 'chronotrace',
    'region' => 'us-east-1',
    'server_side_encryption' => 'AES256',
    // Or use KMS
    'server_side_encryption' => 'aws:kms',
    'kms_key_id' => 'arn:aws:kms:us-east-1:123456789:key/12345678-1234-1234-1234-123456789012',
],
```

**Local File Encryption:**
```php
// Custom implementation in service provider
'encryption' => [
    'enabled' => true,
    'key' => env('CHRONOTRACE_ENCRYPTION_KEY'),
    'cipher' => 'AES-256-CBC',
],
```

### Access Control

**Storage Bucket Policies:**
```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "ChronoTraceAccess",
            "Effect": "Allow",
            "Principal": {
                "AWS": "arn:aws:iam::ACCOUNT:role/chronotrace-role"
            },
            "Action": [
                "s3:GetObject",
                "s3:PutObject",
                "s3:DeleteObject"
            ],
            "Resource": "arn:aws:s3:::chronotrace-bucket/*",
            "Condition": {
                "StringEquals": {
                    "s3:x-amz-server-side-encryption": "AES256"
                }
            }
        }
    ]
}
```

**File System Permissions:**
```bash
# Restrictive permissions for local storage
chmod 750 storage/chronotrace/
chown www-data:chronotrace-group storage/chronotrace/

# Ensure only application can read traces
find storage/chronotrace/ -type f -exec chmod 640 {} \;
```

## Network Security

### TLS/SSL Configuration

**S3 Endpoints:**
```php
's3' => [
    'use_ssl' => true,
    'endpoint' => 'https://s3.amazonaws.com',
    // Force TLS 1.2+
    'http' => [
        'verify' => true,
        'timeout' => 30,
        'connect_timeout' => 10,
    ],
],
```

**Custom Endpoints:**
```php
// For Minio or custom S3-compatible storage
's3' => [
    'endpoint' => 'https://secure-minio.internal.company.com',
    'use_ssl' => true,
    'verify_ssl' => true,
],
```

### VPC Configuration

**AWS VPC Endpoint:**
```json
{
    "VpcEndpointType": "Gateway",
    "ServiceName": "com.amazonaws.us-east-1.s3",
    "VpcId": "vpc-12345678",
    "RouteTableIds": ["rtb-12345678"]
}
```

## Audit and Monitoring

### Access Logging

**S3 Access Logs:**
```json
{
    "LoggingEnabled": {
        "TargetBucket": "chronotrace-access-logs",
        "TargetPrefix": "access-logs/"
    }
}
```

**Application Logging:**
```php
// Add to your service provider
use Illuminate\Support\Facades\Log;

app(TraceStorage::class)->onAccess(function ($traceId, $action, $user) {
    Log::info('ChronoTrace access', [
        'trace_id' => $traceId,
        'action' => $action,
        'user' => $user,
        'ip' => request()->ip(),
        'user_agent' => request()->userAgent(),
    ]);
});
```

### Monitoring Alerts

**CloudWatch Alarms:**
```json
{
    "AlarmName": "ChronoTrace-UnauthorizedAccess",
    "MetricName": "4xxError",
    "Namespace": "AWS/S3",
    "Statistic": "Sum",
    "Period": 300,
    "EvaluationPeriods": 1,
    "Threshold": 5,
    "ComparisonOperator": "GreaterThanThreshold"
}
```

## Compliance Considerations

### GDPR Compliance

**Data Minimization:**
```php
'scrub' => [
    // GDPR-relevant fields
    'email',
    'phone',
    'address',
    'name',           // Consider based on use case
    'ip_address',
    'user_agent',     // May contain identifying info
],
```

**Right to Erasure:**
```php
// Implement trace deletion for specific users
class GDPRErasureService
{
    public function eraseUserTraces(string $userId): void
    {
        // Find and delete all traces containing user data
        $storage = app(TraceStorage::class);
        $traces = $storage->findByUserId($userId);
        
        foreach ($traces as $traceId) {
            $storage->delete($traceId);
            Log::info('GDPR: Erased trace', ['trace_id' => $traceId, 'user_id' => $userId]);
        }
    }
}
```

### HIPAA Compliance

**Enhanced Scrubbing:**
```php
'scrub' => [
    // Healthcare-related fields
    'ssn',
    'patient_id',
    'medical_record_number',
    'diagnosis',
    'medication',
    'date_of_birth',
    'insurance_number',
],
```

**Audit Requirements:**
```php
// Comprehensive audit logging
class HIPAAAuditLogger
{
    public function logAccess(string $traceId, string $action): void
    {
        Log::channel('audit')->info('ChronoTrace HIPAA access', [
            'trace_id' => $traceId,
            'action' => $action,
            'user_id' => auth()->id(),
            'timestamp' => now()->toISOString(),
            'session_id' => session()->getId(),
            'ip_address' => request()->ip(),
        ]);
    }
}
```

## Environment-Specific Security

### Development Environment

```php
'scrub' => [
    // Minimal scrubbing for debugging
    'password',
    'token',
    'secret',
],
'debug' => true,
'retention_days' => 7,
```

### Staging Environment

```php
'scrub' => [
    // Production-like scrubbing
    'password',
    'token',
    'secret',
    'email',
    'phone',
],
'debug' => false,
'retention_days' => 15,
```

### Production Environment

```php
'scrub' => [
    // Comprehensive scrubbing
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
    'ip_address',
],
'debug' => false,
'retention_days' => 30,
'encryption' => ['enabled' => true],
```

## Security Best Practices

### 1. Principle of Least Privilege

**IAM Roles:**
```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "s3:GetObject",
                "s3:PutObject",
                "s3:DeleteObject"
            ],
            "Resource": "arn:aws:s3:::chronotrace-bucket/traces/*",
            "Condition": {
                "StringLike": {
                    "s3:x-amz-server-side-encryption": "*"
                }
            }
        }
    ]
}
```

### 2. Regular Security Reviews

**Monthly Checklist:**
- [ ] Review scrubbing rules effectiveness
- [ ] Audit access patterns
- [ ] Check for sensitive data leaks
- [ ] Validate encryption status
- [ ] Review retention policies

### 3. Incident Response

**Security Incident Handling:**
```php
class SecurityIncidentHandler
{
    public function handleDataLeak(string $traceId): void
    {
        // Immediate actions
        $this->quarantineTrace($traceId);
        $this->notifySecurityTeam($traceId);
        $this->auditRelatedTraces($traceId);
        
        // Investigation
        $this->analyzeScrubberFailure();
        $this->reviewAccessLogs();
        
        // Remediation
        $this->updateScrubberRules();
        $this->enhanceMonitoring();
    }
}
```

### 4. Data Classification

**Trace Classification:**
```php
'classification' => [
    'levels' => [
        'public' => [],
        'internal' => ['user_id', 'session_id'],
        'confidential' => ['email', 'phone'],
        'restricted' => ['ssn', 'credit_card'],
    ],
    'default_level' => 'internal',
],
```

## Troubleshooting Security Issues

### Scrubbing Not Working

**Check Configuration:**
```bash
php artisan config:show chronotrace.scrub
```

**Test Scrubber:**
```php
$scrubber = app(PIIScrubber::class);
$testData = ['password' => 'secret123', 'email' => 'test@example.com'];
$scrubbed = $scrubber->scrub($testData);
dump($scrubbed);
```

### Access Control Issues

**Verify Permissions:**
```bash
# For S3
aws s3api head-object --bucket chronotrace-bucket --key traces/test.json

# For local storage
ls -la storage/chronotrace/
```

### Encryption Problems

**Test Encryption:**
```php
// Verify encryption configuration
$config = config('chronotrace.encryption');
if ($config['enabled']) {
    $key = $config['key'];
    $cipher = $config['cipher'];
    
    $encrypted = encrypt('test data', false);
    $decrypted = decrypt($encrypted, false);
    
    assert($decrypted === 'test data');
}
```

## Security Monitoring

### Key Metrics to Monitor

1. **Access Patterns**: Unusual access times or locations
2. **Data Volume**: Unexpected increases in trace sizes
3. **Scrubbing Failures**: Fields that should be scrubbed but aren't
4. **Storage Access**: Unauthorized access attempts
5. **Encryption Status**: Unencrypted traces

### Automated Security Checks

```bash
#!/bin/bash
# security-check.sh

echo "Running ChronoTrace security checks..."

# Check for unencrypted traces
find storage/chronotrace/ -name "*.json" -exec grep -l "password.*:" {} \;

# Verify proper permissions
find storage/chronotrace/ -type f ! -perm 640 -ls

# Check for large traces (potential data leaks)
find storage/chronotrace/ -name "*.json" -size +1M -ls

echo "Security check complete."
```

## Next Steps

- [Review API reference](api-reference.md)
- [Check troubleshooting guide](troubleshooting.md)
- [Explore examples](../examples/README.md)