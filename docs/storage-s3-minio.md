# Configuration S3/MinIO pour ChronoTrace

## Implémentation actuelle

✅ **ChronoTrace supporte maintenant complètement S3 et MinIO** avec l'implémentation suivante :

### Configuration automatique
- ✅ Mapping automatique `'storage' => 's3'` vers le disk Laravel
- ✅ Configuration dynamique du disk S3/MinIO
- ✅ Support des endpoints personnalisés (MinIO)
- ✅ Support des préfixes de chemin
- ✅ Compatible avec l'API S3 et les alternatives

## Configuration

### 1. AWS S3

**config/chronotrace.php :**
```php
'storage' => 's3',
's3' => [
    'bucket' => 'my-chronotrace-bucket',
    'region' => 'us-east-1',
    'path_prefix' => 'traces',
],
```

**Variables d'environnement :**
```env
CHRONOTRACE_STORAGE=s3
CHRONOTRACE_S3_BUCKET=my-chronotrace-bucket
CHRONOTRACE_S3_REGION=us-east-1
CHRONOTRACE_S3_PREFIX=traces

# Credentials AWS
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
```

### 2. MinIO

**config/chronotrace.php :**
```php
'storage' => 's3',
's3' => [
    'bucket' => 'chronotrace',
    'region' => 'us-east-1',
    'endpoint' => 'https://minio.example.com',
    'path_prefix' => 'traces',
],
```

**Variables d'environnement :**
```env
CHRONOTRACE_STORAGE=s3
CHRONOTRACE_S3_BUCKET=chronotrace
CHRONOTRACE_S3_REGION=us-east-1
CHRONOTRACE_S3_ENDPOINT=https://minio.example.com
CHRONOTRACE_S3_PREFIX=traces

# Credentials MinIO
AWS_ACCESS_KEY_ID=minio_access_key
AWS_SECRET_ACCESS_KEY=minio_secret_key
```

## Fonctionnalités supportées

### ✅ Fonctionnalités implémentées
- [x] Stockage des traces en ZIP
- [x] Liste des traces par date
- [x] Purge automatique
- [x] Compression
- [x] Stockage asynchrone
- [x] Support des endpoints personnalisés (MinIO)
- [x] Configuration automatique du disk Laravel
- [x] Visibilité privée par défaut

### 🔄 Configuration automatique
Le ServiceProvider configure automatiquement :
```php
// Disk S3 créé dynamiquement : 'chronotrace_s3'
'filesystems.disks.chronotrace_s3' => [
    'driver' => 's3',
    'bucket' => config('chronotrace.s3.bucket'),
    'region' => config('chronotrace.s3.region'),
    'endpoint' => config('chronotrace.s3.endpoint'), // Pour MinIO
    'use_path_style_endpoint' => true, // Si endpoint personnalisé
    'root' => config('chronotrace.s3.path_prefix'),
    'visibility' => 'private',
]
```

## Test de la configuration

```bash
# Test avec AWS S3
aws s3 ls s3://my-chronotrace-bucket/

# Test avec MinIO
mc ls minio/chronotrace/

# Test ChronoTrace
php artisan chronotrace:list
```

## Permissions S3 requises

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

## Installation MinIO (Docker)

```bash
# MinIO simple
docker run -d \
  --name minio \
  -p 9000:9000 \
  -p 9001:9001 \
  -e MINIO_ROOT_USER=admin \
  -e MINIO_ROOT_PASSWORD=password123 \
  minio/minio server /data --console-address ":9001"

# Créer le bucket
mc alias set local http://localhost:9000 admin password123
mc mb local/chronotrace
```

## Dépannage

### Erreur : "Bucket not found"
```bash
# Vérifier la configuration
php artisan config:show chronotrace.s3

# Créer le bucket
aws s3 mb s3://my-chronotrace-bucket
```

### Erreur : "Access Denied"
```bash
# Vérifier les credentials
aws s3 ls s3://my-chronotrace-bucket/

# Test de connectivité MinIO
curl -I https://minio.example.com
```

### Erreur : "Invalid endpoint"
```bash
# Pour MinIO, vérifier l'endpoint
ping minio.example.com

# Test avec curl
curl -I https://minio.example.com/health/live
```

## Performance

### Optimisations recommandées

**Compression :**
```php
'compression' => [
    'enabled' => true,
    'level' => 9, // Maximum pour S3
],
```

**Stockage asynchrone :**
```php
'async_storage' => true,
'queue_connection' => 'redis',
```

**Multipart upload automatique :**
- Activé automatiquement pour les fichiers > 5MB
- Améliore la fiabilité et les performances

## Sécurité

### Recommandations
1. **Chiffrement au repos :** Activer dans S3/MinIO
2. **HTTPS :** Toujours utiliser des endpoints HTTPS
3. **IAM Roles :** Préférer aux clés d'accès en production
4. **VPC Endpoints :** Pour AWS S3 en production
5. **Monitoring :** Activer les logs d'accès

### Configuration sécurisée
```php
's3' => [
    'bucket' => 'chronotrace-encrypted',
    'region' => 'us-east-1',
    'endpoint' => 'https://s3.amazonaws.com', // HTTPS requis
    'server_side_encryption' => 'AES256',
],
```
