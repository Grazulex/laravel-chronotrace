# Laravel ChronoTrace Documentation

Welcome to the Laravel ChronoTrace documentation! This package allows you to record and replay Laravel requests deterministically, capturing all database queries, cache operations, HTTP requests, and queue jobs.

## Table of Contents

- [Installation](installation.md)
- [Configuration](configuration.md)
- [Commands](commands.md)
- [Event Capturing](event-capturing.md)
- [Storage](storage.md)
- [Security & PII Scrubbing](security.md)
- [API Reference](api-reference.md)
- [Troubleshooting](troubleshooting.md)

## Quick Start

1. **Install the package**:
   ```bash
   composer require --dev grazulex/laravel-chronotrace
   ```

2. **Publish the configuration**:
   ```bash
   php artisan vendor:publish --tag=chronotrace-config
   ```

3. **Start recording traces**:
   ```bash
   php artisan chronotrace:record /api/users
   ```

4. **List your traces**:
   ```bash
   php artisan chronotrace:list
   ```

5. **Replay a trace**:
   ```bash
   php artisan chronotrace:replay {trace-id}
   ```

## What Gets Captured

Laravel ChronoTrace automatically captures:

- 📊 **Database queries** - All SQL queries with bindings and execution time
- 🗄️ **Cache operations** - Hits, misses, writes, and deletions
- 🌐 **HTTP requests** - External API calls made during the request
- ⚙️ **Queue jobs** - Job processing, failures, and completions
- 📧 **Mail events** - Email sending operations
- 🔔 **Notifications** - Push notifications and other alerts
- 🎉 **Custom events** - Laravel events fired during execution

## Use Cases

- **Debug production issues** by replaying exact scenarios locally
- **Performance analysis** to identify slow queries and N+1 problems
- **API monitoring** to track external service dependencies
- **Quality assurance** by capturing real user flows
- **Team onboarding** by providing realistic development scenarios

## Need Help?

- Check our [Troubleshooting Guide](troubleshooting.md)
- View practical [Examples](../examples/README.md)
- Report issues on [GitHub](https://github.com/Grazulex/laravel-chronotrace/issues)