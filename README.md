# Laravel ChronoTrace

<div align="center">
  <img src="new_logo.png" alt="Laravel ChronoTrace" width="200">
  <p><strong>⏱️ Record and replay Laravel requests deterministically — capture all database queries, cache operations, HTTP calls, and queue jobs for debugging and analysis.</strong></p>

  [![Latest Version](https://img.shields.io/packagist/v/grazulex/laravel-chronotrace.svg?style=flat-square)](https://packagist.org/packages/grazulex/laravel-chronotrace)
  [![Total Downloads](https://img.shields.io/packagist/dt/grazulex/laravel-chronotrace.svg?style=flat-square)](https://packagist.org/packages/grazulex/laravel-chronotrace)
  [![License](https://img.shields.io/github/license/grazulex/laravel-chronotrace.svg?style=flat-square)](https://github.com/Grazulex/laravel-chronotrace/blob/main/LICENSE.md)
  [![PHP Version](https://img.shields.io/badge/php-8.3%2B-777bb4?style=flat-square&logo=php)](https://php.net/)
  [![Laravel Version](https://img.shields.io/badge/laravel-12.x%20|%2013.x-ff2d20?style=flat-square&logo=laravel)](https://laravel.com/)
  [![Tests](https://img.shields.io/github/actions/workflow/status/grazulex/laravel-chronotrace/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/Grazulex/laravel-chronotrace/actions)
  [![Code Style](https://img.shields.io/badge/code%20style-pint-000000?style=flat-square&logo=laravel)](https://github.com/laravel/pint)
</div>

---

## 📖 Overview

**Laravel ChronoTrace** is a powerful debugging and monitoring tool for Laravel applications that allows you to:

- **🎯 Capture** HTTP requests and their complete execution context (DB queries, cache operations, external HTTP calls, queue jobs)
- **🔄 Replay** traces to analyze what happened during specific requests
- **🔍 Debug** production issues with comprehensive event logs
- **📊 Monitor** application performance and identify bottlenecks

Perfect for debugging hard-to-reproduce issues, performance analysis, and understanding complex application flows.

---

## ✨ Features

- **⏺️ Smart Recording** – Multiple recording modes: always, sample rate, error-only, or targeted routes
- **📊 Comprehensive Event Capture** – Database queries, cache operations, HTTP requests, queue jobs, and custom events
- **🔄 Detailed Replay** – View complete execution flow with timestamps and performance metrics
- **🎯 Flexible Filtering** – Focus on specific event types (DB, cache, HTTP, jobs) during analysis
- **💾 Multiple Storage Options** – Local storage, S3, or custom storage adapters
- **🔐 PII Scrubbing** – Automatically mask sensitive data (passwords, tokens, emails, etc.)
- **⚡ Async Storage** – Queue-based storage for minimal performance impact
- **🗂️ Automatic Cleanup** – Configurable retention policies and automatic purging

---

## 📦 Installation

```bash
composer require --dev grazulex/laravel-chronotrace
```

**Requirements:**
- PHP 8.3+
- Laravel 12.x / 13.x

---

## 🚀 Quick Start

### 1️⃣ Install and Configure

```bash
composer require --dev grazulex/laravel-chronotrace
php artisan chronotrace:install
```

### 2️⃣ Configure Recording Mode

Edit `config/chronotrace.php` or set environment variables:

```env
CHRONOTRACE_ENABLED=true
CHRONOTRACE_MODE=record_on_error  # always | sample | record_on_error | targeted
CHRONOTRACE_STORAGE=local         # local | s3
```

### 3️⃣ Record Traces

**For debugging real application issues:**

```bash
# Record a specific endpoint
php artisan chronotrace:record /api/users

# Record with POST data
php artisan chronotrace:record /api/users \
  --method=POST \
  --data='{"name":"John","email":"john@example.com"}'

# Record with custom headers
php artisan chronotrace:record /api/protected \
  --method=GET \
  --headers='{"Authorization":"Bearer token123"}'
```

**For testing ChronoTrace configuration:**

```bash
# Test that ChronoTrace captures internal operations
php artisan chronotrace:test-internal

# Test specific operation types
php artisan chronotrace:test-internal --with-db --with-cache
```

> **💡 Key Difference**: `chronotrace:record` captures real HTTP requests for debugging actual issues, while `chronotrace:test-internal` validates that ChronoTrace is properly configured and working.

### 4️⃣ View Your Traces

```bash
# List all traces
php artisan chronotrace:list

# List with full trace IDs
php artisan chronotrace:list --full-id

# Replay a specific trace (use ID from list command)
php artisan chronotrace:replay abc12345-def6-7890-abcd-ef1234567890
```

### 5️⃣ Filter Events and Generate Tests

```bash
# View only database queries
php artisan chronotrace:replay {trace-id} --db

# View only cache operations
php artisan chronotrace:replay {trace-id} --cache

# View only HTTP requests
php artisan chronotrace:replay {trace-id} --http

# View only queue jobs
php artisan chronotrace:replay {trace-id} --jobs

# View detailed information with context, headers, and content
php artisan chronotrace:replay {trace-id} --detailed

# Show SQL query bindings for debugging
php artisan chronotrace:replay {trace-id} --db --bindings

# Generate Pest tests from traces
php artisan chronotrace:replay {trace-id} --generate-test

# Generate tests in custom directory
php artisan chronotrace:replay {trace-id} --generate-test --test-path=tests/Integration

# Output as JSON for programmatic processing
php artisan chronotrace:replay {trace-id} --format=json
```

---

## 🔧 Storage & Configuration

- **Local Storage**: `storage/chronotrace/{date}/{trace-id}/`
- **S3/Minio**: Support for distributed setups with configurable buckets
- **Automatic Cleanup**: TTL-based purge policies (default: 15 days)
- **Compression**: Configurable compression for large traces
- **PII Scrubbing**: Automatic masking of sensitive fields

---

## 📊 What Gets Captured

Each trace includes comprehensive information:

```
=== TRACE INFORMATION ===
🆔 Trace ID: abc12345-def6-7890-abcd-ef1234567890
🕒 Timestamp: 2024-01-15 14:30:22
🌍 Environment: production
🔗 Request URL: https://app.example.com/api/users
📊 Response Status: 200
⏱️  Duration: 245ms
💾 Memory Usage: 18.45 KB

=== CAPTURED EVENTS ===
📊 DATABASE EVENTS
  🔍 Query: SELECT * FROM users WHERE active = ? (15ms)
  🔍 Query: SELECT * FROM roles WHERE user_id IN (?, ?) (8ms)

🗄️  CACHE EVENTS  
  ❌ Cache MISS: users:list (store: redis)
  💾 Cache WRITE: users:list (store: redis)

🌐 HTTP EVENTS
  📤 HTTP Request: GET https://api.external.com/validation
  📥 HTTP Response: 200 (1,234 bytes)

⚙️  JOB EVENTS
  🔄 Job STARTED: ProcessUserRegistration
  ✅ Job COMPLETED: ProcessUserRegistration
```

---

## 🔧 Available Commands

### Recording & Analysis Commands
- **`chronotrace:record`** – Record real HTTP requests for debugging actual application issues
- **`chronotrace:list`** – List stored traces with metadata and filtering options
- **`chronotrace:replay`** – Replay and analyze captured traces with advanced filtering and output formats
- **`chronotrace:purge`** – Remove old traces based on retention policy

### Setup & Testing Commands  
- **`chronotrace:install`** – Install and configure ChronoTrace middleware
- **`chronotrace:test-internal`** – Test ChronoTrace configuration with internal Laravel operations
- **`chronotrace:test-middleware`** – Test middleware installation and activation
- **`chronotrace:diagnose`** – Diagnose configuration and potential issues with comprehensive checks

### Command Examples

```bash
# Installation and setup
chronotrace:install --force

# Validate ChronoTrace is working
chronotrace:test-internal --with-db --with-cache

# Record real application traces  
chronotrace:record /api/users --method=GET
chronotrace:record /checkout --method=POST --data='{"cart_id": 123}'
chronotrace:record /api/protected --headers='{"Authorization":"Bearer token"}'

# Advanced recording with timeout
chronotrace:record /api/slow-endpoint --timeout=60

# List and analyze traces
chronotrace:list --limit=10 --full-id
chronotrace:replay {trace-id} --db --cache --bindings
chronotrace:replay {trace-id} --detailed --context --headers
chronotrace:replay {trace-id} --generate-test --test-path=tests/Integration

# Output in different formats
chronotrace:replay {trace-id} --format=json
chronotrace:replay {trace-id} --format=raw

# Diagnostics and testing
chronotrace:diagnose
chronotrace:test-middleware

# Test internal operations when chronotrace:record doesn't capture internal events
chronotrace:test-internal --with-db --with-cache --with-events
chronotrace:test-internal --with-db  # Test only database operations

# Maintenance and cleanup
chronotrace:purge --days=7 --confirm
```  

---

## 📊 Use Cases

- **Bug Reproduction** – No more “can’t reproduce locally” issues  
- **Test Generation** – Build realistic tests from production data  
- **Performance Audits** – Find slow queries, N+1s and cache misses  
- **Configuration Validation** – Diagnose setup issues with built-in tools
- **Onboarding** – Help new devs understand complex flows via execution graphs  

---

## 🔐 Security & Privacy

- PII scrubbing by default (configurable fields)  
- Trace encryption at rest  
- Trace TTL & purge policies  
- Audit log of trace access  

---

## 🤝 Contributing

We welcome contributions! See [CONTRIBUTING.md](CONTRIBUTING.md) for details.

---

## 📚 Documentation

Pour une documentation complète et détaillée, consultez notre **[Wiki officiel](https://github.com/Grazulex/laravel-chronotrace/wiki)** :

- **[Installation Guide](https://github.com/Grazulex/laravel-chronotrace/wiki/Installation)** - Guide complet d'installation et de configuration
- **[Configuration](https://github.com/Grazulex/laravel-chronotrace/wiki/Configuration)** - Toutes les options de configuration disponibles
- **[Recording Modes](https://github.com/Grazulex/laravel-chronotrace/wiki/Recording-Modes)** - Modes d'enregistrement et cas d'usage
- **[Commands Reference](https://github.com/Grazulex/laravel-chronotrace/wiki/Commands-Reference)** - Documentation complète de toutes les commandes
- **[Storage Options](https://github.com/Grazulex/laravel-chronotrace/wiki/Storage-Options)** - Configuration du stockage local et S3
- **[Advanced Usage](https://github.com/Grazulex/laravel-chronotrace/wiki/Advanced-Usage)** - Techniques avancées et cas d'usage complexes
- **[Troubleshooting](https://github.com/Grazulex/laravel-chronotrace/wiki/Troubleshooting)** - Solutions aux problèmes courants
- **[API Reference](https://github.com/Grazulex/laravel-chronotrace/wiki/API-Reference)** - Documentation de l'API interne

---

**Laravel ChronoTrace** is open-sourced software licensed under the [MIT license](LICENSE.md).

<div align="center">
  <p>Made with ❤️ for the Laravel community</p>
</div>