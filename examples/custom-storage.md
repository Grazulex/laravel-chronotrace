# Custom Storage Examples

This guide shows how to set up and configure different storage backends for ChronoTrace.

## S3 Storage Setup

### AWS S3 Configuration

**1. Create S3 Bucket:**
```bash
# Using AWS CLI
aws s3 mb s3://your-chronotrace-bucket --region us-east-1

# Set proper permissions
aws s3api put-bucket-policy --bucket your-chronotrace-bucket --policy file://bucket-policy.json
```

**bucket-policy.json:**
```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Principal": {
                "AWS": "arn:aws:iam::123456789012:role/chronotrace-role"
            },
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