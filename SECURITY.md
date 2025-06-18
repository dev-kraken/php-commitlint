# Security Policy

## Supported Versions

We actively support the following versions of PHP CommitLint:

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |

## Reporting a Vulnerability

If you discover a security vulnerability in PHP CommitLint, please report it responsibly:

### For Critical Vulnerabilities

1. **Do NOT** open a public GitHub issue
2. **Email directly** to: security@devkraken.com
3. **Include** the following information:
   - Description of the vulnerability
   - Steps to reproduce
   - Potential impact
   - Suggested fix (if you have one)

### Response Timeline

- **Initial Response**: Within 48 hours
- **Status Update**: Within 7 days
- **Fix Release**: Depends on severity and complexity

### What to Expect

1. **Acknowledgment** of your report
2. **Assessment** of the vulnerability
3. **Development** of a fix
4. **Coordinated disclosure** once the fix is available
5. **Credit** in our security advisories (if desired)

### Security Best Practices for Users

- **Keep PHP CommitLint updated** to the latest version
- **Review commit hooks** periodically
- **Use strong validation rules** in your configuration
- **Monitor** for any unusual git hook behavior
- **Audit** your `.commitlintrc.json` configuration

### Scope

This security policy covers:

- The PHP CommitLint codebase
- Git hook installation and execution
- Configuration file processing
- Command execution vulnerabilities

### Out of Scope

- Issues in dependencies (report to the respective maintainers)
- Social engineering attacks
- Physical security
- Issues in hosting platforms

## Security Features

PHP CommitLint includes several security features:

- **Input validation** for all commit message processing
- **Safe command execution** using Symfony Process component
- **Configuration validation** to prevent malicious configs
- **Path traversal protection** in file operations
- **No eval() or dynamic code execution**

## Vulnerability Disclosure Policy

We believe in responsible disclosure and will:

1. **Work with you** to understand the issue
2. **Keep you informed** of our progress
3. **Credit you** publicly (if you wish)
4. **Notify users** of security updates appropriately

Thank you for helping keep PHP CommitLint secure!
