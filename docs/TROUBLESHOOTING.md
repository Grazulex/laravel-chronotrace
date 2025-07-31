# Guide de Résolution des Problèmes ChronoTrace

## Problème : Erreur Queue Connection

### Symptômes
```
InvalidArgumentException: The [default] queue connection has not been configured.
at vendor/laravel/framework/src/Illuminate/Queue/QueueManager.php:169
```

### Diagnostic Rapide
```bash
php artisan chronotrace:diagnose
```

### Solutions par Ordre de Priorité

#### 1. Configuration Automatique (Recommandée)
```bash
# Dans votre .env, ne spécifiez pas CHRONOTRACE_QUEUE_CONNECTION
# ChronoTrace détectera automatiquement une queue disponible

# Activez le fallback vers stockage synchrone
CHRONOTRACE_QUEUE_FALLBACK=true
```

#### 2. Configuration Queue Sync
```bash
# Dans .env
QUEUE_CONNECTION=sync
CHRONOTRACE_QUEUE_CONNECTION=sync
```

#### 3. Configuration Queue Database
```bash
# Dans .env
QUEUE_CONNECTION=database
DB_CONNECTION=sqlite  # ou mysql, pgsql
CHRONOTRACE_QUEUE_CONNECTION=database

# Puis exécuter
php artisan queue:table
php artisan migrate
```

#### 4. Désactiver Stockage Asynchrone
```bash
# Dans .env
CHRONOTRACE_ASYNC_STORAGE=false
```

### Configuration Laravel Queue.php

Si nécessaire, ajoutez une connexion dédiée dans `config/queue.php` :

```php
'connections' => [
    // ... autres connexions
    
    'chronotrace' => [
        'driver' => 'sync',
    ],
],
```

Puis dans `.env` :
```bash
CHRONOTRACE_QUEUE_CONNECTION=chronotrace
```

## Problème : Permissions de Stockage

### Symptômes
- Erreurs "Permission denied" lors de l'écriture
- Aucune trace générée

### Solutions
```bash
# Créer le répertoire et définir les permissions
mkdir -p storage/chronotrace
chmod 755 storage/chronotrace
chown www-data:www-data storage/chronotrace  # Si nécessaire
```

## Problème : Configuration S3/MinIO

### Variables d'Environnement Requises
```bash
# S3 Standard
CHRONOTRACE_STORAGE=s3
CHRONOTRACE_S3_BUCKET=your-bucket
CHRONOTRACE_S3_REGION=us-east-1
AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret

# MinIO
CHRONOTRACE_STORAGE=minio
CHRONOTRACE_S3_BUCKET=chronotrace
CHRONOTRACE_S3_REGION=us-east-1
CHRONOTRACE_S3_ENDPOINT=http://localhost:9000
AWS_ACCESS_KEY_ID=minioadmin
AWS_SECRET_ACCESS_KEY=minioadmin
```

## Mode Debug

Pour activer le debug et voir les logs détaillés :

```bash
# Dans .env
CHRONOTRACE_DEBUG=true
```

Puis consultez `storage/logs/laravel.log` pour voir les messages de debug ChronoTrace.

## Test de Configuration

```bash
# Tester la configuration complète
php artisan chronotrace:diagnose

# Tester spécifiquement le middleware
php artisan chronotrace:test-middleware

# Lister les traces existantes
php artisan chronotrace:list

# Enregistrer une trace de test
php artisan chronotrace:record http://localhost:8000/
```

## Problème : Middleware Ne Génère Pas de Traces

### Diagnostic
```bash
# 1. Tester le middleware
php artisan chronotrace:test-middleware

# 2. Activer le debug
# Dans .env
CHRONOTRACE_DEBUG=true

# 3. Tester une requête et vérifier les logs
tail -f storage/logs/laravel.log | grep ChronoTrace
```

### Solutions

#### 1. Vérifier l'Enregistrement du Middleware
Dans `bootstrap/app.php` (Laravel 11+):
```php
use Grazulex\LaravelChronotrace\Middleware\ChronoTraceMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    // ... autres configurations
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            ChronoTraceMiddleware::class,
        ]);
        $middleware->api(append: [
            ChronoTraceMiddleware::class,
        ]);
    })
    // ... reste de la configuration
```

#### 2. Vérifier la Configuration
```bash
# Dans .env
CHRONOTRACE_ENABLED=true
CHRONOTRACE_MODE=always  # Pour tester
CHRONOTRACE_DEBUG=true
```

#### 3. Mode de Capture
- `always`: Capture toutes les requêtes
- `record_on_error`: Capture seulement les erreurs (500+)
- `sample`: Capture selon sample_rate
- `targeted`: Capture seulement les routes ciblées

## Configurations Recommandées par Environnement

### Développement Local
```bash
CHRONOTRACE_ENABLED=true
CHRONOTRACE_MODE=always
CHRONOTRACE_ASYNC_STORAGE=false
CHRONOTRACE_DEBUG=true
QUEUE_CONNECTION=sync
```

### Staging/Testing
```bash
CHRONOTRACE_ENABLED=true
CHRONOTRACE_MODE=sample
CHRONOTRACE_SAMPLE_RATE=0.1
CHRONOTRACE_ASYNC_STORAGE=true
QUEUE_CONNECTION=database
```

### Production
```bash
CHRONOTRACE_ENABLED=true
CHRONOTRACE_MODE=record_on_error
CHRONOTRACE_ASYNC_STORAGE=true
QUEUE_CONNECTION=redis  # ou sqs
CHRONOTRACE_RETENTION_DAYS=7
```

## Dépannage Avancé

### Vérifier la Configuration Queue
```bash
php artisan config:show queue
```

### Tester les Queues Laravel
```bash
php artisan queue:work --once
```

### Vérifier les Logs
```bash
tail -f storage/logs/laravel.log | grep ChronoTrace
```

### Test Manuel d'une Queue
```php
// Dans tinker
Queue::push(new \App\Jobs\TestJob());
```

## Contact Support

Si les problèmes persistent :
1. Exécutez `php artisan chronotrace:diagnose`
2. Partagez la sortie complète
3. Incluez votre configuration `.env` (sans les secrets)
4. Incluez la version de Laravel et PHP utilisée
