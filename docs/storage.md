# Storage

ChronoTrace supports multiple storage backends for storing trace data. This guide covers configuration and management of different storage options.

## Storage Backends

### Local Storage (Default)

**Configuration:**
```php
'storage' => 'local',
'path' => storage_path('chronotrace'),
```

**Environment Variables:**
```env
CHRONOTRACE_STORAGE=local
CHRONOTRACE_PATH="/var/www/storage/chronotrace"
```

**Directory Structure:**
```
storage/chronotrace/
├── 2024-01-15/
│   ├── abc12345-def6-7890-abcd-ef1234567890/
│   │   ├── trace.json
│   │   └── metadata.json
│   └── def67890-abc1-2345-6789-abcdef123456/
│       ├── trace.json
│       └── metadata.json
└── 2024-01-16/
    └── ...
```

**Pros:**
- Simple setup
- No external dependencies
- Fast access
- Good for development

**Cons:**
- Limited to single server
- Manual backup management
- Storage space limitations

### S3 Storage

**Configuration:**
```php
'storage' => 's3',
's3' => [
    'bucket' => 'my-chronotrace-bucket',
    'region' => 'us-east-1',
    'endpoint' => null, // Use default AWS S3
    'path_prefix' => 'traces',
],
```

**Environment Variables:**
```env
CHRONOTRACE_STORAGE=s3
CHRONOTRACE_S3_BUCKET=my-chronotrace-bucket
CHRONOTRACE_S3_REGION=us-east-1
CHRONOTRACE_S3_PREFIX=traces

# AWS Credentials (use IAM roles in production)
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
```

**Required Permissions:**
```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "s3:GetObject",
                "s3:PutObject",
                "s3:DeleteObject",
                "s3:ListBucket"
            ],
            "Resource": [
                "arn:aws:s3:::my-chronotrace-bucket",
                "arn:aws:s3:::my-chronotrace-bucket/*"
            ]
        }
    ]
}
```

**Pros:**
- Scalable storage
- Built-in durability
- Cross-region replication
- Lifecycle management
- Team access

**Cons:**
- Additional latency
- AWS costs
- Network dependency

### Minio Storage

**Configuration:**
```php
'storage' => 's3',
's3' => [
    'bucket' => 'chronotrace',
    'region' => 'us-east-1',
    'endpoint' => 'https://minio.example.com',
    'path_prefix' => 'traces',
],
```

**Environment Variables:**
```env
CHRONOTRACE_STORAGE=s3
CHRONOTRACE_S3_BUCKET=chronotrace
CHRONOTRACE_S3_REGION=us-east-1
CHRONOTRACE_S3_ENDPOINT=https://minio.example.com
CHRONOTRACE_S3_PREFIX=traces
```

**Pros:**
- Self-hosted S3 alternative
- Lower costs than AWS S3
- Full control over data
- S3-compatible API

**Cons:**
- Infrastructure management
- Backup responsibility
- Scaling complexity

## Storage Configuration

### Compression

**Configuration:**
```php
'compression' => [
    'enabled' => true,
    'level' => 6,              // Compression level (1-9)
    'max_payload_size' => 1024 * 1024, // 1MB threshold
],
```

**Benefits:**
- Reduced storage costs
- Faster transfer times
- Better performance for large traces

**Considerations:**
- CPU overhead during compression
- Decompression time during replay

### Async Storage

**Configuration:**
```php
'async_storage' => true,
'queue_connection' => 'redis',
'queue_name' => 'chronotrace',
```

**Benefits:**
- Non-blocking request processing
- Better application performance
- Handles storage failures gracefully

**Requirements:**
- Active queue worker
- Reliable queue backend

**Queue Worker:**
```bash
php artisan queue:work --queue=chronotrace
```

## Retention and Cleanup

### Automatic Purging

**Configuration:**
```php
'retention_days' => 15,
'auto_purge' => true,
```

**Manual Purging:**
```bash
# Purge traces older than 15 days
php artisan chronotrace:purge --days=15

# Force purge without confirmation
php artisan chronotrace:purge --days=15 --confirm
```

### Scheduled Cleanup

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('chronotrace:purge --days=30 --confirm')
             ->daily()
             ->at('02:00');
}
```

## Storage Management

### Monitoring Storage Usage

**Local Storage:**
```bash
# Check total size
du -sh storage/chronotrace/

# List largest traces
find storage/chronotrace/ -name "*.json" -exec ls -lh {} + | sort -k5 -hr | head -10
```

**S3 Storage:**
```bash
# Using AWS CLI
aws s3 ls s3://my-chronotrace-bucket/ --recursive --human-readable --summarize

# Check bucket size
aws cloudwatch get-metric-statistics \
  --namespace AWS/S3 \
  --metric-data MetricName=BucketStorageBytesData,Unit=Bytes \
  --dimensions Name=BucketName,Value=my-chronotrace-bucket
```

### Backup Strategies

**Local Storage Backup:**
```bash
# Rsync to backup location
rsync -av storage/chronotrace/ /backup/chronotrace/

# Tar compress for archival
tar -czf chronotrace-backup-$(date +%Y%m%d).tar.gz storage/chronotrace/
```

**S3 Cross-Region Replication:**
```json
{
    "Role": "arn:aws:iam::account:role/replication-role",
    "Rules": [
        {
            "Status": "Enabled",
            "Prefix": "traces/",
            "Destination": {
                "Bucket": "arn:aws:s3:::my-chronotrace-backup",
                "StorageClass": "STANDARD_IA"
            }
        }
    ]
}
```

## Performance Optimization

### Local Storage Optimization

**File System:**
- Use SSD storage for better I/O performance
- Consider separate disk for trace storage
- Monitor disk space usage

**Permissions:**
```bash
# Ensure proper permissions
chmod -R 755 storage/chronotrace/
chown -R www-data:www-data storage/chronotrace/
```

### S3 Optimization

**Transfer Acceleration:**
```env
# Enable S3 Transfer Acceleration
CHRONOTRACE_S3_ENDPOINT=s3-accelerate.amazonaws.com
```

**Multipart Upload:**
- Automatically used for large traces
- Improves upload reliability
- Better performance for large files

**Storage Classes:**
```php
// For archival traces, use cheaper storage classes
's3' => [
    'storage_class' => 'STANDARD_IA', // or 'GLACIER'
],
```

## Troubleshooting

### Local Storage Issues

**Permission Errors:**
```bash
sudo chown -R www-data:www-data storage/chronotrace/
sudo chmod -R 755 storage/chronotrace/
```

**Disk Space:**
```bash
df -h
# Clean up old traces if needed
php artisan chronotrace:purge --days=7 --confirm
```

### S3 Storage Issues

**Credentials:**
```bash
# Test AWS credentials
aws s3 ls s3://my-chronotrace-bucket/

# Check IAM permissions
aws iam simulate-principal-policy \
  --policy-source-arn arn:aws:iam::account:user/chronotrace-user \
  --action-names s3:GetObject s3:PutObject \
  --resource-arns arn:aws:s3:::my-chronotrace-bucket/*
```

**Connectivity:**
```bash
# Test endpoint connectivity
curl -I https://s3.amazonaws.com
curl -I https://my-minio-endpoint.com
```

**Debugging:**
```php
// Enable S3 debug logging
'debug' => true,
```

### Queue Issues

**Worker Status:**
```bash
# Check if workers are running
ps aux | grep "queue:work"

# Start queue worker
php artisan queue:work --queue=chronotrace --verbose
```

**Failed Jobs:**
```bash
# List failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

## Best Practices

### Development Environment

```php
'storage' => 'local',
'path' => storage_path('chronotrace'),
'compression' => ['enabled' => false],
'async_storage' => false,
'retention_days' => 7,
```

### Staging Environment

```php
'storage' => 's3',
'compression' => ['enabled' => true, 'level' => 6],
'async_storage' => true,
'retention_days' => 15,
```

### Production Environment

```php
'storage' => 's3',
'compression' => ['enabled' => true, 'level' => 9],
'async_storage' => true,
'retention_days' => 30,
'auto_purge' => true,
```

### Security Considerations

1. **Access Control**: Limit access to storage buckets
2. **Encryption**: Enable encryption at rest
3. **Network Security**: Use VPC endpoints for S3
4. **Credentials**: Use IAM roles instead of access keys
5. **Monitoring**: Enable access logging

## Custom Storage Adapters

To implement a custom storage adapter, extend the `TraceStorage` class:

```php
use Grazulex\LaravelChronotrace\Storage\TraceStorage;

class CustomStorage extends TraceStorage
{
    public function store(string $traceId, array $data): bool
    {
        // Implement custom storage logic
    }

    public function retrieve(string $traceId): ?TraceData
    {
        // Implement retrieval logic
    }

    public function list(): array
    {
        // Implement listing logic
    }

    public function purgeOldTraces(int $days): int
    {
        // Implement cleanup logic
    }
}
```

Register in service provider:

```php
$this->app->singleton(TraceStorage::class, CustomStorage::class);
```

## Next Steps

- [Configure security and PII scrubbing](security.md)
- [Learn about API reference](api-reference.md)
- [Check troubleshooting guide](troubleshooting.md)