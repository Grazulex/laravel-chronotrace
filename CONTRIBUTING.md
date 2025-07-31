# Contributing to Laravel Chronotrace

Thank you for your interest in contributing to Laravel Chronotrace! We welcome contributions from the community to help make this package even better.

## Code of Conduct

By participating in this project, you are expected to uphold our code of conduct. Please be respectful and professional in all interactions.

## How to Contribute

### Reporting Bugs

If you find a bug, please create an issue on GitHub with:
- A clear description of the problem
- Steps to reproduce the issue
- Expected vs actual behavior
- PHP and Laravel version information
- Any relevant code samples

### Suggesting Features

We welcome feature suggestions! Please create an issue with:
- A clear description of the proposed feature
- Use cases and benefits
- Any implementation details you have in mind

### Pull Requests

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass: `composer run full`
6. Commit your changes: `git commit -m 'Add amazing feature'`
7. Push to the branch: `git push origin feature/amazing-feature`
8. Open a Pull Request

## Development Setup

1. Clone the repository:
   ```bash
   git clone https://github.com/grazulex/laravel-chronotrace.git
   cd laravel-chronotrace
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Run tests to ensure everything works:
   ```bash
   composer run full
   ```

## Code Standards

This project follows Laravel coding standards and uses several tools to maintain code quality:

- **Laravel Pint** for code formatting
- **PHPStan** for static analysis
- **Rector** for automated refactoring
- **Pest** for testing

Before submitting a PR, please run:
```bash
composer run full
```

## Writing Tests

- Write tests for all new functionality
- Use descriptive test names
- Follow the Arrange-Act-Assert pattern
- Add both unit and feature tests when appropriate

Example test:
```php
<?php

it('can do something amazing', function () {
    // Arrange
    $input = 'test data';
    
    // Act
    $result = doSomethingAmazing($input);
    
    // Assert
    expect($result)->toBe('expected output');
});
```

## Documentation

- Update README.md for new features
- Add inline documentation for public methods
- Include usage examples for new functionality

## Questions?

If you have questions about contributing, feel free to:
- Open an issue for discussion
- Start a discussion in the GitHub Discussions tab
- Contact the maintainers

Thank you for contributing! 🚀
