# Laravel ChronoTrace

<div align="center">
  <img src="new_logo.png" alt="Laravel ChronoTrace" width="200">
  <p><strong>⏱️ Record and replay any Laravel request or job deterministically — and auto-generate reproducible tests from production traces.</strong></p>

  [![Latest Version](https://img.shields.io/packagist/v/grazulex/laravel-chronotrace.svg?style=flat-square)](https://packagist.org/packages/grazulex/laravel-chronotrace)
  [![Total Downloads](https://img.shields.io/packagist/dt/grazulex/laravel-chronotrace.svg?style=flat-square)](https://packagist.org/packages/grazulex/laravel-chronotrace)
  [![License](https://img.shields.io/github/license/grazulex/laravel-chronotrace.svg?style=flat-square)](https://github.com/Grazulex/laravel-chronotrace/blob/main/LICENSE.md)
  [![PHP Version](https://img.shields.io/badge/php-8.3%2B-777bb4?style=flat-square&logo=php)](https://php.net/)
  [![Laravel Version](https://img.shields.io/badge/laravel-11.x%20%7C%2012.x-ff2d20?style=flat-square&logo=laravel)](https://laravel.com/)
  [![Tests](https://img.shields.io/github/actions/workflow/status/grazulex/laravel-chronotrace/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/Grazulex/laravel-chronotrace/actions)
  [![Code Style](https://img.shields.io/badge/code%20style-pint-000000?style=flat-square&logo=laravel)](https://github.com/laravel/pint)
</div>

---

## 📖 Overview

**Laravel ChronoTrace** revolutionizes debugging and test generation for Laravel apps by allowing you to:

- **Capture** any HTTP request or queued job (including DB queries, cache, external HTTP calls, mails, notifications, and side-effects)  
- **Replay** it locally in a deterministic sandbox  
- **Auto-generate a Pest test** that reproduces the exact production scenario  

All with minimal impact on production performance.

---

## ✨ Features

- **⏺️ Record-on-error** – Capture traces automatically when 5xx errors occur  
- **🔁 Deterministic Replay** – Freeze time, randomness and IO for bit-perfect replays  
- **🗄 Minimal Dataset Generation** – Build an ephemeral SQLite dataset or use virtual DB replay  
- **📦 Trace Bundles** – Self-contained `.zip` bundles that include all data and IO needed  
- **🧪 Auto-generated Tests** – Create Pest tests from real-world traffic in one command  
- **🔍 N+1 Detection** – Identify lazy-loading issues and propose eager-load fixes  
- **📊 Execution Graphs** – Visualize flow of controllers, events, listeners, jobs and queries  
- **🔐 PII Scrubbing** – Mask sensitive fields by default (passwords, tokens, emails)

---

## 📦 Installation

```bash
composer require --dev grazulex/laravel-chronotrace
```

**Requirements:**
- PHP 8.3+
- Laravel 11.x | 12.x

---

## 🚀 Quick Start

### 1️⃣ Enable ChronoTrace

Publish config:
```bash
php artisan vendor:publish --tag=chronotrace-config
```

Default config (config/chronotrace.php):
```php
return [
  'mode' => 'record_on_error',    // only on 5xx errors
  'storage' => 's3',               // or 'local'
  'retention_days' => 15,
  'scrub' => ['password', 'token', 'authorization'],
];
```

### 2️⃣ Capture traces

Once enabled, ChronoTrace will record targeted requests/jobs as trace bundles.  
You can also manually record:
```bash
php artisan chronotrace:record --route=checkout
```

### 3️⃣ Pull trace bundles

```bash
php artisan chronotrace:pull --id=abc123 --dest=storage/chronotrace/
```

### 4️⃣ Replay deterministically

```bash
php artisan chronotrace:replay --trace=storage/chronotrace/abc123 --strategy=virtual
```

Or with ephemeral SQLite dataset:
```bash
php artisan chronotrace:replay --trace=... --strategy=sqlite
```

### 5️⃣ Generate a test

```bash
php artisan chronotrace:test --trace=storage/chronotrace/abc123 --name=CheckoutReplayTest
```

This generates:
```
tests/Feature/ChronoTrace/CheckoutReplayTest.php
database/chronotrace/seeds/abc123.sql
```

---

## 🔧 Storage & Retention

- Default store: `storage/chronotrace/{date}/{trace-id}/`
- S3/Minio drivers available for distributed setups
- Automatic TTL purge (default: 15 days)
- Each trace is compressed and self-contained (JSON + assets)

---

## 🧪 Example Generated Test

```php
it('replays production checkout bug', function () {
    ChronoTrace::replay('abc123');

    $response = $this->get('/checkout?cart_id=123');

    $response->assertStatus(500)
             ->assertSee('Payment gateway error');
});
```

---

## 🔍 Commands

- `chronotrace:record` – Enable recording for selected routes/jobs  
- `chronotrace:pull` – Retrieve a trace bundle from prod  
- `chronotrace:replay` – Replay a captured trace locally  
- `chronotrace:test` – Generate a Pest test from a trace  
- `chronotrace:list` – List available traces and their metadata  
- `chronotrace:purge` – Purge old traces  

---

## 📊 Use Cases

- **Bug Reproduction** – No more “can’t reproduce locally” issues  
- **Test Generation** – Build realistic tests from production data  
- **Performance Audits** – Find slow queries, N+1s and cache misses  
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

**Laravel ChronoTrace** is open-sourced software licensed under the [MIT license](LICENSE.md).

<div align="center">
  <p>Made with ❤️ for the Laravel community</p>
</div>