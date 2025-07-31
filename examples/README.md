# Laravel ChronoTrace Examples

This directory contains practical examples demonstrating how to use Laravel ChronoTrace in various scenarios.

## 📁 Examples Overview

- **[Basic Usage](basic-usage.md)** - Getting started with recording and replaying traces
- **[Configuration Examples](configuration-examples.md)** - Different configuration setups for various environments
- **[Event Filtering](event-filtering.md)** - How to capture and filter specific types of events
- **[Custom Storage](custom-storage.md)** - Setting up S3 and custom storage solutions
- **[Production Monitoring](production-monitoring.md)** - Real-world production monitoring scenarios
- **[Development Workflow](development-workflow.md)** - Using ChronoTrace in your development process

## 🚀 Quick Start Example

Here's a simple example to get you started:

```bash
# 1. Install and configure
composer require --dev grazulex/laravel-chronotrace
php artisan chronotrace:install

# 2. Record a trace
php artisan chronotrace:record /api/users

# 3. List traces
php artisan chronotrace:list

# 4. Replay the trace
php artisan chronotrace:replay {your-trace-id}

# 5. Generate tests from traces
php artisan chronotrace:replay {your-trace-id} --generate-test
```

## 📊 Common Use Cases

### 🐛 Debugging Production Issues

```bash
# First, validate your setup
php artisan chronotrace:diagnose

# Record problematic endpoint with authentication
php artisan chronotrace:record /checkout/process \
  --method=POST \
  --data='{"cart_id": 123}' \
  --headers='{"Authorization":"Bearer prod-token"}'

# Analyze database queries
php artisan chronotrace:replay {trace-id} --db

# Check external API calls
php artisan chronotrace:replay {trace-id} --http

# Generate a test to prevent regressions
php artisan chronotrace:replay {trace-id} --generate-test --test-path=tests/Regression
```

### 🚀 Performance Analysis

```bash
# Record a slow endpoint
php artisan chronotrace:record /dashboard/reports

# View all captured events
php artisan chronotrace:replay {trace-id}

# Focus on database performance
php artisan chronotrace:replay {trace-id} --db
```

### 🔍 API Monitoring

```bash
# Record API calls with different methods
php artisan chronotrace:record /api/v1/orders --method=GET
php artisan chronotrace:record /api/v1/orders \
  --method=POST \
  --data='{"product_id": 1, "quantity": 2}' \
  --headers='{"Content-Type":"application/json"}'

# Analyze external dependencies
php artisan chronotrace:replay {trace-id} --http

# Generate comprehensive test suite
php artisan chronotrace:replay {trace-id} --generate-test --test-path=tests/Api
```

## 🛠️ Configuration Examples

### Development Environment

```php
// config/chronotrace.php
return [
    'enabled' => true,
    'mode' => 'always',  // Capture everything
    'storage' => 'local',
    'retention_days' => 7,
    'debug' => true,
    'capture' => [
        'database' => true,
        'cache' => true,
        'http' => true,
        'jobs' => true,
        'events' => true,  // Enable for full debugging
    ],
];
```

### Production Environment

```php
// config/chronotrace.php
return [
    'enabled' => true,
    'mode' => 'record_on_error',  // Only on errors
    'storage' => 's3',
    'retention_days' => 30,
    'async_storage' => true,
    'capture' => [
        'database' => true,
        'cache' => true,
        'http' => true,
        'jobs' => true,
        'events' => false,  // Disabled for performance
    ],
];
```

## 🔧 Advanced Examples

### 🛠️ Initial Setup and Validation

```bash
# Install with automatic configuration
php artisan chronotrace:install

# Force reconfigure if needed
php artisan chronotrace:install --force

# Validate installation
php artisan chronotrace:diagnose

# Test middleware setup
php artisan chronotrace:test-middleware
```

### Custom Event Filtering

```bash
# Record a complex workflow
php artisan chronotrace:record /orders/complete

# View only database events to find N+1 queries
php artisan chronotrace:replay {trace-id} --db

# Check cache efficiency
php artisan chronotrace:replay {trace-id} --cache

# Monitor external API usage
php artisan chronotrace:replay {trace-id} --http
```

### Test Generation from Traces

```bash
# Record a complex workflow
php artisan chronotrace:record /orders/complete

# Generate a basic Pest test
php artisan chronotrace:replay {trace-id} --generate-test

# Generate test in specific directory
php artisan chronotrace:replay {trace-id} --generate-test --test-path=tests/Integration

# Review generated test
cat tests/Generated/ChronoTrace_abc12345_Test.php

# Run the generated test
./vendor/bin/pest tests/Generated/ChronoTrace_abc12345_Test.php
```

### Automated Monitoring

Create a script for automated trace analysis:

```bash
#!/bin/bash
# scripts/analyze-traces.sh

echo "Validating ChronoTrace setup..."
php artisan chronotrace:diagnose

echo "Recording critical endpoints..."
php artisan chronotrace:record /api/checkout --method=POST --data='{"test": true}'
php artisan chronotrace:record /api/payment/process --method=POST --data='{"amount": 100}'

echo "Listing recent traces..."
php artisan chronotrace:list --limit=5 --full-id

echo "Generating tests for monitoring..."
# Extract trace IDs and generate tests
TRACES=$(php artisan chronotrace:list --limit=5 --full-id | grep "│" | head -2)
# Process each trace ID...

echo "Cleaning up old traces..."
php artisan chronotrace:purge --days=7 --confirm
```

## 📈 Best Practices

### 1. Use Appropriate Recording Modes

- **Development**: Use `always` mode for complete visibility
- **Staging**: Use `sample` mode with 1-5% rate
- **Production**: Use `record_on_error` mode for minimal overhead

### 2. Configure Storage Properly

- **Small teams**: Local storage is sufficient
- **Distributed teams**: Use S3 or shared storage
- **High traffic**: Enable async storage and compression

### 3. Regular Maintenance

```bash
# Set up automated cleanup
php artisan schedule:work

# Monitor storage usage
du -sh storage/chronotrace/

# Purge old traces regularly
php artisan chronotrace:purge --days=15 --confirm
```

## 🔍 Troubleshooting Examples

### Permission Issues

```bash
# Fix storage permissions
sudo chown -R www-data:www-data storage/chronotrace/
sudo chmod -R 755 storage/chronotrace/
```

### Storage Issues

```bash
# Test S3 configuration
aws s3 ls s3://your-chronotrace-bucket/

# Verify local storage
ls -la storage/chronotrace/
```

### Performance Issues

```bash
# Check trace sizes
php artisan chronotrace:list

# Enable compression for large traces
# Set compression.enabled = true in config
```

## 📚 Learn More

- [Configuration Guide](../docs/configuration.md)
- [Commands Reference](../docs/commands.md)
- [Event Capturing](../docs/event-capturing.md)
- [Storage Options](../docs/storage.md)

## 🤝 Contributing Examples

Have a useful example? We'd love to include it! Please:

1. Follow the existing format
2. Include clear explanations
3. Test your examples thoroughly
4. Submit a PR with your addition

---

**Need help?** Check our [documentation](../docs/README.md) or [open an issue](https://github.com/Grazulex/laravel-chronotrace/issues).