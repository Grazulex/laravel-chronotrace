# Custom Storage Configuration Examples

This comprehensive guide shows how to set up and configure different storage backends for Laravel ChronoTrace, including S3, MinIO, and custom solutions.

## AWS S3 Storage Setup

### 1. Create and Configure S3 Bucket

**Create S3 Bucket:**
```bash
# Using AWS CLI
aws s3 mb s3://your-chronotrace-bucket --region us-east-1

# Enable versioning (optional)
aws s3api put-bucket-versioning --bucket your-chronotrace-bucket \
    --versioning-configuration Status=Enabled

# Set lifecycle policy for automatic cleanup
aws s3api put-bucket-lifecycle-configuration --bucket your-chronotrace-bucket \
    --lifecycle-configuration file://lifecycle-policy.json
```

**lifecycle-policy.json:**
```json
{
    "Rules": [
        {
            "ID": "ChronoTraceCleanup",
            "Status": "Enabled",
            "Filter": {
                "Prefix": "traces/"
            },
            "Expiration": {
                "Days": 30
            },
            "NoncurrentVersionExpiration": {
                "NoncurrentDays": 7
            }
        }
    ]
}
```

### 2. Configure IAM Permissions

**Create IAM Policy:**
```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "ChronoTraceS3Access",
            "Effect": "Allow",
            "Action": [
                "s3:GetObject",
                "s3:PutObject",
                "s3:DeleteObject",
                "s3:ListBucket"
            ],
            "Resource": [
                "arn:aws:s3:::your-chronotrace-bucket",
                "arn:aws:s3:::your-chronotrace-bucket/*"
            ]
        }
    ]
}
```

**Create IAM Role (for EC2/ECS):**
```bash
# Create role
aws iam create-role --role-name ChronoTraceRole \
    --assume-role-policy-document file://trust-policy.json

# Attach policy
aws iam attach-role-policy --role-name ChronoTraceRole \
    --policy-arn arn:aws:iam::123456789012:policy/ChronoTraceS3Policy

# Create instance profile
aws iam create-instance-profile --instance-profile-name ChronoTraceProfile
aws iam add-role-to-instance-profile --instance-profile-name ChronoTraceProfile \
    --role-name ChronoTraceRole
```

**trust-policy.json:**
```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Principal": {
                "Service": "ec2.amazonaws.com"
            },
            "Action": "sts:AssumeRole"
        }
    ]
}
```

### 3. Laravel Configuration

**Environment Variables:**
```env
# AWS S3 Configuration
AWS_ACCESS_KEY_ID=AKIA...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=us-east-1

# ChronoTrace S3 Settings
CHRONOTRACE_STORAGE=s3
CHRONOTRACE_S3_BUCKET=your-chronotrace-bucket
CHRONOTRACE_S3_REGION=us-east-1
CHRONOTRACE_S3_PREFIX=traces
```

**config/chronotrace.php:**
```php
return [
    'storage' => env('CHRONOTRACE_STORAGE', 's3'),
    
    's3' => [
        'bucket' => env('CHRONOTRACE_S3_BUCKET', 'chronotrace'),
        'region' => env('CHRONOTRACE_S3_REGION', 'us-east-1'),
        'path_prefix' => env('CHRONOTRACE_S3_PREFIX', 'traces'),
    ],
    
    'compression' => [
        'enabled' => true,
        'level' => 6, // Good balance of compression and speed
    ],
    
    'async_storage' => true, // Recommended for S3
];
```

### 4. Validation and Testing

```bash
# Test S3 connectivity
aws s3 ls s3://your-chronotrace-bucket/

# Test ChronoTrace S3 storage
php artisan chronotrace:diagnose

# Record a test trace
php artisan chronotrace:record /test

# Verify trace was stored in S3
aws s3 ls s3://your-chronotrace-bucket/traces/ --recursive
```

## MinIO Self-Hosted S3 Setup

### 1. MinIO Server Installation

**Docker Compose Setup:**
```yaml
version: '3.8'
services:
  minio:
    image: minio/minio:latest
    container_name: chronotrace-minio
    command: server /data --console-address ":9001"
    environment:
      MINIO_ROOT_USER: chronotrace-admin
      MINIO_ROOT_PASSWORD: chronotrace-secret-key
    ports:
      - "9000:9000"
      - "9001:9001"
    volumes:
      - minio_data:/data
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:9000/minio/health/live"]
      interval: 30s
      timeout: 20s
      retries: 3

volumes:
  minio_data:
```

**Start MinIO:**
```bash
docker-compose up -d minio
```

### 2. Configure MinIO Bucket

**Create Bucket via Web Interface:**
1. Open http://localhost:9001
2. Login with admin credentials
3. Create bucket named `chronotrace`
4. Set bucket policy to allow read/write

**Or via MinIO Client:**
```bash
# Install MinIO client
wget https://dl.min.io/client/mc/release/linux-amd64/mc
chmod +x mc
sudo mv mc /usr/local/bin/

# Configure MinIO client
mc alias set local http://localhost:9000 chronotrace-admin chronotrace-secret-key

# Create bucket
mc mb local/chronotrace

# Set bucket policy
mc policy set public local/chronotrace
```

### 3. Laravel MinIO Configuration

**Environment Variables:**
```env
# MinIO Configuration
AWS_ACCESS_KEY_ID=chronotrace-admin
AWS_SECRET_ACCESS_KEY=chronotrace-secret-key
AWS_DEFAULT_REGION=us-east-1

# ChronoTrace MinIO Settings
CHRONOTRACE_STORAGE=s3
CHRONOTRACE_S3_BUCKET=chronotrace
CHRONOTRACE_S3_REGION=us-east-1
CHRONOTRACE_S3_ENDPOINT=http://localhost:9000
CHRONOTRACE_S3_PREFIX=traces
```

**config/chronotrace.php:**
```php
return [
    'storage' => env('CHRONOTRACE_STORAGE', 's3'),
    
    's3' => [
        'bucket' => env('CHRONOTRACE_S3_BUCKET', 'chronotrace'),
        'region' => env('CHRONOTRACE_S3_REGION', 'us-east-1'),
        'endpoint' => env('CHRONOTRACE_S3_ENDPOINT', 'http://localhost:9000'),
        'path_prefix' => env('CHRONOTRACE_S3_PREFIX', 'traces'),
    ],
    
    'compression' => [
        'enabled' => true,
        'level' => 6,
    ],
];
```

### 4. Production MinIO Setup

**Production docker-compose.yml:**
```yaml
version: '3.8'
services:
  minio:
    image: minio/minio:latest
    command: server /data --console-address ":9001"
    environment:
      MINIO_ROOT_USER: ${MINIO_ROOT_USER}
      MINIO_ROOT_PASSWORD: ${MINIO_ROOT_PASSWORD}
    ports:
      - "9000:9000"
      - "9001:9001"
    volumes:
      - /data/minio:/data
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:9000/minio/health/live"]
      interval: 30s
      timeout: 20s
      retries: 3

  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx.conf:/etc/nginx/nginx.conf
      - ./ssl:/etc/nginx/ssl
    depends_on:
      - minio
```

**nginx.conf for MinIO:**
```nginx
events {
    worker_connections 1024;
}

http {
    upstream minio {
        server minio:9000;
    }

    server {
        listen 80;
        server_name minio.yourcompany.com;
        return 301 https://$server_name$request_uri;
    }

    server {
        listen 443 ssl;
        server_name minio.yourcompany.com;

        ssl_certificate /etc/nginx/ssl/minio.crt;
        ssl_certificate_key /etc/nginx/ssl/minio.key;

        location / {
            proxy_pass http://minio;
            proxy_set_header Host $http_host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
        }
    }
}
```

## Multi-Environment Storage Strategy

### Development Environment

**Local Storage for Development:**
```php
// config/chronotrace.php
if (app()->environment('local')) {
    return [
        'storage' => 'local',
        'path' => storage_path('chronotrace'),
        'compression' => ['enabled' => false],
        'async_storage' => false,
    ];
}
```

### Staging with Shared MinIO

**Staging Configuration:**
```env
CHRONOTRACE_STORAGE=s3
CHRONOTRACE_S3_BUCKET=chronotrace-staging
CHRONOTRACE_S3_ENDPOINT=https://minio-staging.yourcompany.com
CHRONOTRACE_S3_PREFIX=staging/traces
```

### Production with AWS S3

**Production Configuration:**
```env
CHRONOTRACE_STORAGE=s3
CHRONOTRACE_S3_BUCKET=chronotrace-production
CHRONOTRACE_S3_REGION=us-east-1
CHRONOTRACE_S3_PREFIX=production/traces
```

## Custom Storage Driver

### Creating a Custom Storage Driver

**1. Create Custom Storage Class:**
```php
<?php

namespace App\Services\ChronoTrace;

use Grazulex\LaravelChronotrace\Storage\TraceStorageInterface;
use Grazulex\LaravelChronotrace\Models\TraceData;

class DatabaseTraceStorage implements TraceStorageInterface
{
    public function store(string $traceId, TraceData $data): bool
    {
        try {
            \DB::table('chronotrace_traces')->insert([
                'trace_id' => $traceId,
                'data' => json_encode($data),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to store trace in database', [
                'trace_id' => $traceId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    public function retrieve(string $traceId): ?TraceData
    {
        $record = \DB::table('chronotrace_traces')
            ->where('trace_id', $traceId)
            ->first();

        if (!$record) {
            return null;
        }

        $data = json_decode($record->data, true);
        return new TraceData($data);
    }

    public function list(): array
    {
        return \DB::table('chronotrace_traces')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->map(function ($record) {
                return [
                    'trace_id' => $record->trace_id,
                    'created_at' => strtotime($record->created_at),
                    'size' => strlen($record->data),
                ];
            })
            ->toArray();
    }

    public function delete(string $traceId): bool
    {
        return \DB::table('chronotrace_traces')
            ->where('trace_id', $traceId)
            ->delete() > 0;
    }
}
```

**2. Create Migration:**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('chronotrace_traces', function (Blueprint $table) {
            $table->id();
            $table->string('trace_id', 36)->unique();
            $table->longText('data');
            $table->timestamps();
            
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('chronotrace_traces');
    }
};
```

**3. Register Custom Storage:**
```php
// app/Providers/AppServiceProvider.php
use App\Services\ChronoTrace\DatabaseTraceStorage;
use Grazulex\LaravelChronotrace\Storage\TraceStorage;

public function register()
{
    if (config('chronotrace.storage') === 'database') {
        $this->app->singleton(TraceStorage::class, function ($app) {
            return new DatabaseTraceStorage();
        });
    }
}
```

## Storage Performance Optimization

### Compression Configuration

**Optimal Compression Settings:**
```php
'compression' => [
    'enabled' => true,
    'level' => 6, // Balance between compression ratio and speed
    'max_payload_size' => 1024 * 1024, // 1MB - store larger payloads separately
],
```

**Compression Level Guide:**
- **Level 1**: Fastest compression, larger files
- **Level 6**: Good balance (recommended)
- **Level 9**: Maximum compression, slower

### Async Storage Configuration

**Queue-Based Storage:**
```php
'async_storage' => true,
'queue_connection' => 'redis', // Use Redis for better performance
'queue_name' => 'chronotrace',
'queue_fallback' => true, // Fallback to sync if queue fails
```

**Queue Worker Optimization:**
```bash
# Run dedicated queue worker for ChronoTrace
php artisan queue:work --queue=chronotrace --sleep=3 --tries=3 --max-time=3600

# Monitor queue performance
php artisan queue:monitor chronotrace --max=100
```

## Storage Monitoring and Maintenance

### S3 Cost Optimization

**S3 Lifecycle Policy:**
```json
{
    "Rules": [
        {
            "ID": "ChronoTraceLifecycle",
            "Status": "Enabled",
            "Filter": {"Prefix": "traces/"},
            "Transitions": [
                {
                    "Days": 7,
                    "StorageClass": "STANDARD_IA"
                },
                {
                    "Days": 30,
                    "StorageClass": "GLACIER"
                }
            ],
            "Expiration": {
                "Days": 90
            }
        }
    ]
}
```

### Storage Health Monitoring

**Storage Health Check Script:**
```bash
#!/bin/bash
# storage-health-check.sh

echo "🗄️ ChronoTrace Storage Health Check"
echo "==================================="

# Check configuration
echo "📊 Storage Configuration:"
php artisan config:show chronotrace.storage
php artisan config:show chronotrace.s3

# Test storage connectivity
echo ""
echo "🔗 Connectivity Test:"
php artisan chronotrace:diagnose | grep -A 10 "Storage Configuration"

# Check recent traces
echo ""
echo "📋 Recent Traces:"
TRACE_COUNT=$(php artisan chronotrace:list --limit=10 | grep -c "│")
echo "Recent traces: $TRACE_COUNT"

# For S3, check bucket size
if [ "$(php artisan config:show chronotrace.storage)" = "s3" ]; then
    BUCKET=$(php artisan config:show chronotrace.s3.bucket)
    echo ""
    echo "📦 S3 Bucket Size:"
    aws s3 ls s3://$BUCKET/traces/ --recursive --summarize | tail -2
fi

# Storage usage for local storage
if [ "$(php artisan config:show chronotrace.storage)" = "local" ]; then
    echo ""
    echo "💾 Local Storage Usage:"
    du -sh storage/chronotrace/
    echo "File count: $(find storage/chronotrace/ -type f | wc -l)"
fi
```

### Automated Cleanup

**Cleanup Script:**
```bash
#!/bin/bash
# cleanup-old-traces.sh

RETENTION_DAYS=${1:-30}

echo "🧹 Cleaning up traces older than $RETENTION_DAYS days"

# ChronoTrace built-in cleanup
php artisan chronotrace:purge --days=$RETENTION_DAYS --confirm

# For S3, verify cleanup worked
if [ "$(php artisan config:show chronotrace.storage)" = "s3" ]; then
    BUCKET=$(php artisan config:show chronotrace.s3.bucket)
    CUTOFF_DATE=$(date -d "$RETENTION_DAYS days ago" +%Y-%m-%d)
    
    echo "Verifying S3 cleanup..."
    OLD_FILES=$(aws s3 ls s3://$BUCKET/traces/ --recursive | \
                awk '$1 < "'$CUTOFF_DATE'" {print $4}' | wc -l)
    
    if [ $OLD_FILES -gt 0 ]; then
        echo "⚠️ Warning: $OLD_FILES old files still present"
    else
        echo "✅ S3 cleanup successful"
    fi
fi
```

## Troubleshooting Storage Issues

### Common S3 Issues

**Permission Errors:**
```bash
# Test S3 permissions
aws s3 cp test.txt s3://your-chronotrace-bucket/test.txt
aws s3 rm s3://your-chronotrace-bucket/test.txt

# Check IAM permissions
aws iam simulate-principal-policy \
    --policy-source-arn arn:aws:iam::123456789012:role/ChronoTraceRole \
    --action-names s3:PutObject \
    --resource-arns arn:aws:s3:::your-chronotrace-bucket/*
```

**Connection Issues:**
```bash
# Test network connectivity
curl -I https://s3.amazonaws.com
nslookup s3.amazonaws.com

# Check AWS credentials
aws sts get-caller-identity
```

### Common MinIO Issues

**Connection Errors:**
```bash
# Check MinIO service
curl http://localhost:9000/minio/health/live

# Test MinIO client connectivity
mc ls local/chronotrace
```

**Permission Issues:**
```bash
# Check bucket policy
mc policy get local/chronotrace

# Set correct policy
mc policy set readwrite local/chronotrace
```

### Debugging Configuration

**Debug Storage Configuration:**
```bash
# Run comprehensive diagnostics
php artisan chronotrace:diagnose

# Test storage manually
php artisan tinker
>>> $storage = app(\Grazulex\LaravelChronotrace\Storage\TraceStorage::class);
>>> $storage->store('test', new \Grazulex\LaravelChronotrace\Models\TraceData([]));
>>> $storage->list();
```

---

**Next Steps:**
- [Learn advanced event filtering](event-filtering.md)
- [Set up production monitoring](production-monitoring.md)
- [Configure development workflows](development-workflow.md)
                "s3:DeleteObject",
                "s3:ListBucket"
            ],
            "Resource": [
                "arn:aws:s3:::your-chronotrace-bucket",
                "arn:aws:s3:::your-chronotrace-bucket/*"
            ]
        }
    ]
}
```

**2. Configure Laravel:**
```env
CHRONOTRACE_STORAGE=s3
CHRONOTRACE_S3_BUCKET=your-chronotrace-bucket
CHRONOTRACE_S3_REGION=us-east-1
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
```

**3. Test Configuration:**
```bash
# Test S3 access
aws s3 ls s3://your-chronotrace-bucket/

# Test ChronoTrace storage
php artisan chronotrace:list
```

### Multi-Environment S3 Setup

**Different buckets per environment:**
```env
# .env.local
CHRONOTRACE_S3_BUCKET=dev-chronotrace
CHRONOTRACE_S3_PREFIX=local-traces

# .env.staging  
CHRONOTRACE_S3_BUCKET=staging-chronotrace
CHRONOTRACE_S3_PREFIX=staging-traces

# .env.production
CHRONOTRACE_S3_BUCKET=prod-chronotrace
CHRONOTRACE_S3_PREFIX=prod-traces
```

## MinIO Storage Setup

### Self-Hosted MinIO

**1. Install MinIO:**
```bash
# Using Docker
docker run -d \
  --name minio \
  -p 9000:9000 \
  -p 9001:9001 \
  -e MINIO_ROOT_USER=admin \
  -e MINIO_ROOT_PASSWORD=password123 \
  minio/minio server /data --console-address ":9001"
```

**2. Create Bucket:**
```bash
# Using MinIO client
mc alias set local http://localhost:9000 admin password123
mc mb local/chronotrace
```

**3. Configure Laravel:**
```env
CHRONOTRACE_STORAGE=s3
CHRONOTRACE_S3_BUCKET=chronotrace
CHRONOTRACE_S3_REGION=us-east-1
CHRONOTRACE_S3_ENDPOINT=http://localhost:9000
AWS_ACCESS_KEY_ID=admin
AWS_SECRET_ACCESS_KEY=password123
```

### Production MinIO Setup

**docker-compose.yml:**
```yaml
version: '3'
services:
  minio:
    image: minio/minio
    ports:
      - "9000:9000"
      - "9001:9001"
    environment:
      MINIO_ROOT_USER: ${MINIO_ROOT_USER}
      MINIO_ROOT_PASSWORD: ${MINIO_ROOT_PASSWORD}
    volumes:
      - ./data:/data
    command: server /data --console-address ":9001"
    
  nginx:
    image: nginx
    ports:
      - "443:443"
    volumes:
      - ./nginx.conf:/etc/nginx/nginx.conf
      - ./ssl:/etc/ssl
    depends_on:
      - minio
```

**nginx.conf for SSL termination:**
```nginx
events {
    worker_connections 1024;
}

http {
    upstream minio {
        server minio:9000;
    }
    
    server {
        listen 443 ssl;
        server_name minio.yourdomain.com;
        
        ssl_certificate /etc/ssl/cert.pem;
        ssl_certificate_key /etc/ssl/key.pem;
        
        location / {
            proxy_pass http://minio;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
        }
    }
}
```

## Database Storage

### Custom Database Storage Implementation

```php
<?php
// app/Storage/DatabaseTraceStorage.php

namespace App\Storage;

use Grazulex\LaravelChronotrace\Storage\TraceStorage;
use Grazulex\LaravelChronotrace\Models\TraceData;
use Illuminate\Support\Facades\DB;

class DatabaseTraceStorage extends TraceStorage
{
    public function store(string $traceId, array $data): bool
    {
        try {
            DB::table('chronotrace_traces')->insert([
                'trace_id' => $traceId,
                'data' => json_encode($data),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            return true;
        } catch (\Exception $e) {
            logger('Failed to store trace', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    public function retrieve(string $traceId): ?TraceData
    {
        $record = DB::table('chronotrace_traces')
            ->where('trace_id', $traceId)
            ->first();
            
        if (!$record) {
            return null;
        }
        
        $data = json_decode($record->data, true);
        return TraceData::fromArray($data);
    }
    
    public function list(): array
    {
        return DB::table('chronotrace_traces')
            ->select('trace_id', 'created_at')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->map(function ($record) {
                return [
                    'trace_id' => $record->trace_id,
                    'created_at' => strtotime($record->created_at),
                    'size' => strlen($record->data ?? ''),
                ];
            })
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

**Migration:**
```php
<?php
// database/migrations/create_chronotrace_traces_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChronotraceTracesTable extends Migration
{
    public function up()
    {
        Schema::create('chronotrace_traces', function (Blueprint $table) {
            $table->id();
            $table->string('trace_id')->unique();
            $table->longText('data');
            $table->timestamps();
            
            $table->index('created_at');
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('chronotrace_traces');
    }
}
```

**Register in Service Provider:**
```php
// app/Providers/AppServiceProvider.php

use App\Storage\DatabaseTraceStorage;
use Grazulex\LaravelChronotrace\Storage\TraceStorage;

public function register()
{
    $this->app->singleton(TraceStorage::class, DatabaseTraceStorage::class);
}
```

## Redis Storage

### Redis-Based Storage

```php
<?php
// app/Storage/RedisTraceStorage.php

namespace App\Storage;

use Grazulex\LaravelChronotrace\Storage\TraceStorage;
use Grazulex\LaravelChronotrace\Models\TraceData;
use Illuminate\Support\Facades\Redis;

class RedisTraceStorage extends TraceStorage
{
    private string $prefix = 'chronotrace:traces:';
    private string $indexKey = 'chronotrace:index';
    
    public function store(string $traceId, array $data): bool
    {
        try {
            $key = $this->prefix . $traceId;
            
            // Store trace data
            Redis::set($key, json_encode($data));
            Redis::expire($key, config('chronotrace.retention_days', 15) * 86400);
            
            // Add to index
            Redis::zadd($this->indexKey, time(), $traceId);
            
            return true;
        } catch (\Exception $e) {
            logger('Failed to store trace in Redis', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    public function retrieve(string $traceId): ?TraceData
    {
        $key = $this->prefix . $traceId;
        $data = Redis::get($key);
        
        if (!$data) {
            return null;
        }
        
        return TraceData::fromArray(json_decode($data, true));
    }
    
    public function list(): array
    {
        // Get trace IDs from index (newest first)
        $traceIds = Redis::zrevrange($this->indexKey, 0, 99);
        
        $traces = [];
        foreach ($traceIds as $traceId) {
            $key = $this->prefix . $traceId;
            $score = Redis::zscore($this->indexKey, $traceId);
            $size = Redis::strlen($key);
            
            $traces[] = [
                'trace_id' => $traceId,
                'created_at' => (int) $score,
                'size' => $size,
            ];
        }
        
        return $traces;
    }
    
    public function purgeOldTraces(int $days): int
    {
        $cutoff = time() - ($days * 86400);
        
        // Get old trace IDs
        $oldTraceIds = Redis::zrangebyscore($this->indexKey, 0, $cutoff);
        
        $deleted = 0;
        foreach ($oldTraceIds as $traceId) {
            $key = $this->prefix . $traceId;
            if (Redis::del($key)) {
                Redis::zrem($this->indexKey, $traceId);
                $deleted++;
            }
        }
        
        return $deleted;
    }
}
```

## Network File System (NFS)

### NFS Storage Setup

**1. Mount NFS Share:**
```bash
# Create mount point
sudo mkdir -p /mnt/chronotrace

# Mount NFS share
sudo mount -t nfs nfs-server.example.com:/exports/chronotrace /mnt/chronotrace

# Add to /etc/fstab for persistence
echo "nfs-server.example.com:/exports/chronotrace /mnt/chronotrace nfs defaults 0 0" | sudo tee -a /etc/fstab
```

**2. Configure ChronoTrace:**
```env
CHRONOTRACE_STORAGE=local
CHRONOTRACE_PATH=/mnt/chronotrace
```

**3. Set Permissions:**
```bash
sudo chown -R www-data:www-data /mnt/chronotrace
sudo chmod -R 755 /mnt/chronotrace
```

## Google Cloud Storage

### GCS Setup

**1. Install GCS Package:**
```bash
composer require google/cloud-storage
```

**2. Create Service Account:**
```bash
# Using gcloud CLI
gcloud iam service-accounts create chronotrace-storage
gcloud projects add-iam-policy-binding PROJECT_ID \
    --member="serviceAccount:chronotrace-storage@PROJECT_ID.iam.gserviceaccount.com" \
    --role="roles/storage.objectAdmin"
```

**3. Custom GCS Storage:**
```php
<?php
// app/Storage/GcsTraceStorage.php

namespace App\Storage;

use Google\Cloud\Storage\StorageClient;
use Grazulex\LaravelChronotrace\Storage\TraceStorage;
use Grazulex\LaravelChronotrace\Models\TraceData;

class GcsTraceStorage extends TraceStorage
{
    private StorageClient $client;
    private string $bucketName;
    
    public function __construct()
    {
        $this->client = new StorageClient([
            'keyFilePath' => config('chronotrace.gcs.key_file'),
        ]);
        $this->bucketName = config('chronotrace.gcs.bucket');
    }
    
    public function store(string $traceId, array $data): bool
    {
        try {
            $bucket = $this->client->bucket($this->bucketName);
            $object = $bucket->upload(
                json_encode($data),
                ['name' => "traces/{$traceId}.json"]
            );
            
            return true;
        } catch (\Exception $e) {
            logger('Failed to store trace in GCS', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    // ... implement other methods
}
```

## Hybrid Storage

### Multi-Tier Storage Strategy

```php
<?php
// app/Storage/HybridTraceStorage.php

namespace App\Storage;

use Grazulex\LaravelChronotrace\Storage\TraceStorage;
use Grazulex\LaravelChronotrace\Models\TraceData;

class HybridTraceStorage extends TraceStorage
{
    private TraceStorage $hotStorage;   // Redis for recent traces
    private TraceStorage $coldStorage;  // S3 for archived traces
    private int $hotStorageDays = 7;
    
    public function __construct(
        RedisTraceStorage $hotStorage,
        S3TraceStorage $coldStorage
    ) {
        $this->hotStorage = $hotStorage;
        $this->coldStorage = $coldStorage;
    }
    
    public function store(string $traceId, array $data): bool
    {
        // Always store in hot storage first
        $hotResult = $this->hotStorage->store($traceId, $data);
        
        // For critical traces, also store in cold storage
        if ($this->isCriticalTrace($data)) {
            $this->coldStorage->store($traceId, $data);
        }
        
        return $hotResult;
    }
    
    public function retrieve(string $traceId): ?TraceData
    {
        // Try hot storage first
        $trace = $this->hotStorage->retrieve($traceId);
        
        if (!$trace) {
            // Fall back to cold storage
            $trace = $this->coldStorage->retrieve($traceId);
        }
        
        return $trace;
    }
    
    public function list(): array
    {
        $hotTraces = $this->hotStorage->list();
        $coldTraces = $this->coldStorage->list();
        
        // Merge and deduplicate
        $allTraces = array_merge($hotTraces, $coldTraces);
        $unique = [];
        
        foreach ($allTraces as $trace) {
            $unique[$trace['trace_id']] = $trace;
        }
        
        // Sort by created_at descending
        uasort($unique, function ($a, $b) {
            return $b['created_at'] <=> $a['created_at'];
        });
        
        return array_values($unique);
    }
    
    public function purgeOldTraces(int $days): int
    {
        // Move old hot traces to cold storage
        $this->archiveOldTraces();
        
        // Purge from both storages
        $hotDeleted = $this->hotStorage->purgeOldTraces($this->hotStorageDays);
        $coldDeleted = $this->coldStorage->purgeOldTraces($days);
        
        return $hotDeleted + $coldDeleted;
    }
    
    private function isCriticalTrace(array $data): bool
    {
        // Store errors and slow requests in cold storage
        return $data['response']['status'] >= 500 || 
               $data['response']['duration'] > 5000;
    }
    
    private function archiveOldTraces(): void
    {
        $oldTraces = $this->hotStorage->getTracesOlderThan($this->hotStorageDays);
        
        foreach ($oldTraces as $traceId => $data) {
            $this->coldStorage->store($traceId, $data);
        }
    }
}
```

## Storage Performance Optimization

### Compression

```php
<?php
// app/Storage/CompressedTraceStorage.php

namespace App\Storage;

class CompressedTraceStorage extends TraceStorage
{
    private TraceStorage $storage;
    
    public function store(string $traceId, array $data): bool
    {
        $json = json_encode($data);
        $compressed = gzcompress($json, 9);
        
        return $this->storage->store($traceId, base64_encode($compressed));
    }
    
    public function retrieve(string $traceId): ?TraceData
    {
        $compressed = $this->storage->retrieve($traceId);
        
        if (!$compressed) {
            return null;
        }
        
        $decompressed = gzuncompress(base64_decode($compressed));
        $data = json_decode($decompressed, true);
        
        return TraceData::fromArray($data);
    }
}
```

### Async Storage with Batching

```php
<?php
// app/Storage/BatchedTraceStorage.php

class BatchedTraceStorage extends TraceStorage
{
    private array $batch = [];
    private int $batchSize = 10;
    
    public function store(string $traceId, array $data): bool
    {
        $this->batch[$traceId] = $data;
        
        if (count($this->batch) >= $this->batchSize) {
            $this->flushBatch();
        }
        
        return true;
    }
    
    private function flushBatch(): void
    {
        if (empty($this->batch)) {
            return;
        }
        
        // Store all traces in batch
        foreach ($this->batch as $traceId => $data) {
            $this->actualStorage->store($traceId, $data);
        }
        
        $this->batch = [];
    }
    
    public function __destruct()
    {
        $this->flushBatch();
    }
}
```

## Storage Testing

### Test Storage Implementation

```php
<?php
// tests/Feature/StorageTest.php

class StorageTest extends TestCase
{
    public function test_can_store_and_retrieve_trace()
    {
        $storage = app(TraceStorage::class);
        $traceId = 'test-trace-' . time();
        
        $data = [
            'traceId' => $traceId,
            'timestamp' => now()->toISOString(),
            'environment' => 'testing',
            'request' => ['url' => '/test'],
            'response' => ['status' => 200],
        ];
        
        // Store trace
        $result = $storage->store($traceId, $data);
        $this->assertTrue($result);
        
        // Retrieve trace
        $retrieved = $storage->retrieve($traceId);
        $this->assertNotNull($retrieved);
        $this->assertEquals($traceId, $retrieved->traceId);
        
        // Cleanup
        $storage->purgeOldTraces(0);
    }
}
```

## Monitoring Storage Health

### Storage Metrics

```php
<?php
// app/Console/Commands/StorageHealthCheck.php

class StorageHealthCheck extends Command
{
    protected $signature = 'chronotrace:storage-health';
    
    public function handle(TraceStorage $storage)
    {
        $this->info('ChronoTrace Storage Health Check');
        
        // Test storage connectivity
        try {
            $traces = $storage->list();
            $this->info("✅ Storage accessible - {count($traces)} traces found");
        } catch (\Exception $e) {
            $this->error("❌ Storage error: {$e->getMessage()}");
            return 1;
        }
        
        // Test write/read
        $testId = 'health-check-' . time();
        $testData = ['test' => true, 'timestamp' => time()];
        
        if ($storage->store($testId, $testData)) {
            $this->info('✅ Write test passed');
            
            if ($storage->retrieve($testId)) {
                $this->info('✅ Read test passed');
            } else {
                $this->error('❌ Read test failed');
            }
            
            // Cleanup
            $storage->purgeOldTraces(0);
        } else {
            $this->error('❌ Write test failed');
        }
        
        return 0;
    }
}
```

## Next Steps

- [Set up development workflows](development-workflow.md)
- [Configure production monitoring](production-monitoring.md)
- [Review storage security](../docs/security.md)