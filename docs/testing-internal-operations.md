# Testing Internal Operations

This guide explains how to test ChronoTrace with internal Laravel operations using the `chronotrace:test-internal` command.

## Overview

The `chronotrace:test-internal` command addresses a common limitation where `chronotrace:record` primarily captures external HTTP events. This command allows you to test ChronoTrace's ability to capture internal Laravel operations like database queries, cache operations, and custom events.

## Usage

```bash
php artisan chronotrace:test-internal [options]
```

### Available Options

- `--with-db`: Include database operation tests
- `--with-cache`: Include cache operation tests  
- `--with-events`: Include custom event tests

### Examples

Test all internal operations:
```bash
php artisan chronotrace:test-internal --with-db --with-cache --with-events
```

Test only database operations:
```bash
php artisan chronotrace:test-internal --with-db
```

Test cache and events:
```bash
php artisan chronotrace:test-internal --with-cache --with-events
```

## What Gets Tested

### Database Operations (`--with-db`)
- Creates a test table
- Performs INSERT, UPDATE, SELECT, and DELETE operations
- Tests both Eloquent ORM and Query Builder operations

### Cache Operations (`--with-cache`)
- Sets cache values with different drivers
- Retrieves cached data
- Tests cache invalidation
- **Note**: May fail in minimal environments without cache table setup

### Custom Events (`--with-events`)
- Fires custom Laravel events
- Tests event listener registration
- Validates event data capture

## Understanding the Output

When you run the command, you'll see:

1. **Trace ID Generation**: A unique identifier for this test session
2. **Operation Results**: Success/failure status for each tested operation
3. **ChronoTrace Activity**: Debug information showing what ChronoTrace captured
4. **Usage Instructions**: How to replay or generate tests from the captured trace

Example output:
```
🧪 Testing ChronoTrace with internal Laravel operations...
📎 Starting trace: ct_neOhAT0HI3v0a8Rg_1754050787
🗄️  Testing database operations...
💾 Testing cache operations...
📡 Testing custom events...
✅ Internal operations test completed!
📊 Trace ID: ct_neOhAT0HI3v0a8Rg_1754050787
💡 Use: php artisan chronotrace:replay ct_neOhAT0HI3v0a8Rg_1754050787 to view the captured trace
🧪 Use: php artisan chronotrace:replay ct_neOhAT0HI3v0a8Rg_1754050787 --generate-test to create a test file
```

## Next Steps

After running the test:

1. **View the captured trace**:
   ```bash
   php artisan chronotrace:replay [trace-id]
   ```

2. **Generate a test file** from the captured operations:
   ```bash
   php artisan chronotrace:replay [trace-id] --generate-test
   ```

3. **Analyze the results** to understand what ChronoTrace captured during internal operations

## Troubleshooting

### Common Issues

**Cache operations failing**: This is expected in minimal test environments. The database operations and events should still work correctly.

**No operations captured**: Check that ChronoTrace is enabled in your configuration and that the middleware is properly installed.

**Permission errors**: Ensure your application has proper database and file system permissions.

### Debugging Tips

- Use `--verbose` flag for more detailed output
- Check your ChronoTrace configuration in `config/chronotrace.php`
- Verify that required listeners are registered in your EventServiceProvider

## Related Commands

- [`chronotrace:record`](commands.md#record) - Record HTTP requests
- [`chronotrace:replay`](commands.md#replay) - Replay captured traces
- [`chronotrace:list`](commands.md#list) - List available traces
- [`chronotrace:diagnose`](commands.md#diagnose) - Diagnose configuration issues
