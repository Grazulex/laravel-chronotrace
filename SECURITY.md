# Security Policy

## Supported Versions

We release patches for security vulnerabilities in the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |
| < 1.0   | :x:                |

## Reporting a Vulnerability

We take the security of Laravel Statecraft seriously. If you believe you have found a security vulnerability, please report it to us as described below.

### How to Report

**Please do not report security vulnerabilities through public GitHub issues.**

Instead, please report them via email to: **grazulex@gmail.com**

### What to Include

When reporting a vulnerability, please include the following information:

- **Description**: A clear description of the vulnerability
- **Impact**: What an attacker could achieve by exploiting this vulnerability
- **Reproduction**: Step-by-step instructions to reproduce the issue
- **Environment**: Laravel version, PHP version, and any other relevant details
- **Proof of Concept**: If possible, include a minimal code example or proof of concept

### What to Expect

- **Acknowledgment**: We will acknowledge receipt of your vulnerability report within 48 hours
- **Initial Response**: We will provide an initial response within 7 days, including our assessment of the report
- **Resolution**: We aim to resolve critical vulnerabilities within 30 days
- **Disclosure**: We will coordinate with you on the responsible disclosure timeline

### Security Update Process

1. **Validation**: We validate and reproduce the reported vulnerability
2. **Fix Development**: We develop and test a fix
3. **Release**: We release a patch version with the security fix
4. **Disclosure**: We publish a security advisory with details about the vulnerability

## Security Best Practices

When using Laravel Statecraft, please follow these security best practices:

### Input Validation

- Always validate state transitions and guard/action inputs
- Use Laravel's validation rules for any user-provided data
- Sanitize any metadata passed to state transitions

### Authorization

- Implement proper authorization checks in your guards
- Use Laravel's authorization features (policies, gates)
- Never trust client-side validation alone

### Configuration

- Review your `config/statecraft.php` configuration
- Ensure generated code paths are secure
- Use environment variables for sensitive configuration

### Database Security

- Use Laravel's Eloquent ORM to prevent SQL injection
- Validate all database queries in custom guards/actions
- Follow Laravel's database security guidelines

## Scope

This security policy applies to:

- The main Laravel Statecraft package
- Generated code from the package's commands
- Configuration files and examples

This policy does not cover:

- Third-party packages used by Laravel Statecraft
- User-implemented guards and actions
- Custom state machine definitions (YAML files)

## Updates to This Policy

This security policy may be updated from time to time. We will announce significant changes through our release notes.

## Contact

For questions about this security policy, please contact us at: **jms@grazulex.be**

---

**Thank you for helping keep Laravel Statecraft secure!**
