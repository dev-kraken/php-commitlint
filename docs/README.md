# PHP CommitLint Documentation

Welcome to the PHP CommitLint documentation! This directory contains comprehensive guides and references for using and extending PHP CommitLint.

## 📖 Getting Started

New to PHP CommitLint? Start here:

1. **[Installation & Quick Start](../README.md#-installation)** - Get up and running in minutes
2. **[Configuration Guide](../README.md#️-configuration)** - Set up your commit rules  
3. **[Commands Overview](../README.md#-commands)** - Learn about available CLI commands

## 📚 Documentation

### User Guides

- **[FAQ](FAQ.md)** - Frequently asked questions and common issues
- **[Configuration Examples](../examples/)** - Sample configuration files
  - [Minimal Configuration](../examples/commitlintrc.minimal.json)
  - [Strict Configuration](../examples/commitlintrc.strict.json)

### Developer Resources

- **[API Documentation](API.md)** - Complete API reference for developers
- **[Contributing Guide](../CONTRIBUTING.md)** - How to contribute to the project
- **[Architecture Overview](#architecture-overview)** - Understanding the codebase structure

### Project Information

- **[Changelog](../CHANGELOG.md)** - Release notes and version history
- **[Security Policy](../SECURITY.md)** - Security guidelines and vulnerability reporting
- **[License](../LICENSE)** - MIT License terms

## 🎯 Quick Reference

### Essential Commands

```bash
# Install hooks
vendor/bin/php-commitlint install

# Validate a commit message
vendor/bin/php-commitlint validate "feat: add new feature"

# List installed hooks
vendor/bin/php-commitlint list

# Add custom hook
vendor/bin/php-commitlint add pre-commit "vendor/bin/pest"

# Uninstall all hooks
vendor/bin/php-commitlint uninstall
```

### Common Configuration Patterns

```json
{
  "rules": {
    "type": {
      "allowed": ["feat", "fix", "docs", "test", "chore"]
    },
    "scope": {
      "required": true,
      "allowed": ["api", "ui", "auth"]
    },
    "subject": {
      "max_length": 50,
      "case": "lower"
    }
  }
}
```

### Exit Codes

- `0` - Success
- `1` - Validation failed
- `2` - Configuration error
- `3` - File system error
- `4` - Invalid argument
- `5` - Runtime error
- `6` - Permission denied
- `7` - Not a Git repository

## 🏗️ Architecture Overview

PHP CommitLint follows a clean architecture pattern with clear separation of concerns:

```
src/
├── Application.php           # Main CLI application
├── Commands/                 # CLI command implementations
│   ├── InstallCommand.php
│   ├── ValidateCommand.php
│   └── ...
├── Services/                 # Business logic
│   ├── ValidationService.php
│   ├── ConfigService.php
│   └── HookService.php
├── Models/                   # Domain models
│   ├── CommitMessage.php
│   └── ValidationResult.php
├── Contracts/               # Interfaces
└── Enums/                   # Enumerations
```

### Key Components

- **Application**: Main entry point, registers commands
- **Commands**: CLI interface using Symfony Console
- **Services**: Core business logic and functionality
- **Models**: Domain objects representing commit data
- **ServiceContainer**: Dependency injection container

## 🔧 Development Workflow

### Setting Up Development Environment

```bash
# Clone the repository
git clone https://github.com/dev-kraken/php-commitlint.git
cd php-commitlint

# Install dependencies
composer install

# Install hooks (optional)
./bin/php-commitlint install

# Run tests
composer test

# Check code style
composer cs:check

# Run static analysis
composer analyse
```

### Testing

PHP CommitLint uses Pest for testing with comprehensive coverage:

- **Unit Tests**: Located in `tests/Unit/`
- **Integration Tests**: Located in `tests/Integration/`
- **Test Helpers**: Common utilities in `tests/TestCase.php`

### Code Quality

The project maintains high code quality through:

- **PSR-12** coding standards
- **PHPStan level 8** static analysis
- **100% test coverage** target
- **Strict type declarations**

## 🤝 Community & Support

### Getting Help

1. **Check the FAQ**: [FAQ.md](FAQ.md) covers most common questions
2. **Search Issues**: Look through [existing issues](https://github.com/dev-kraken/php-commitlint/issues)
3. **Start a Discussion**: Use [GitHub Discussions](https://github.com/dev-kraken/php-commitlint/discussions)
4. **Contact Maintainer**: Email soman@devkraken.com

### Contributing

We welcome contributions! Please see our [Contributing Guide](../CONTRIBUTING.md) for:

- Development setup
- Coding standards
- Testing requirements
- Pull request process

### Issue Labels

- `bug` - Something isn't working
- `enhancement` - New feature or request
- `documentation` - Improvements to docs
- `good first issue` - Good for newcomers
- `help wanted` - Extra attention needed

## 📈 Performance & Scalability

### Performance Characteristics

- **Startup Time**: ~50ms
- **Memory Usage**: 2-5MB
- **Validation Time**: <10ms per commit
- **File I/O**: Minimal (only config loading)

### Optimization Tips

1. **Configuration**: Keep config files small and simple
2. **Patterns**: Use efficient regex patterns
3. **Hooks**: Only install necessary hooks
4. **CI/CD**: Use `--quiet` flag for automated validation

## 🔒 Security Considerations

PHP CommitLint is designed with security in mind:

- **Input Validation**: All inputs are validated and sanitized
- **Path Traversal Protection**: File operations are secured
- **Command Injection Prevention**: Safe command execution
- **Resource Limits**: File size and memory limits
- **No Dynamic Execution**: No eval() or dynamic code execution

See our [Security Policy](../SECURITY.md) for vulnerability reporting.

## 🌍 Internationalization

Currently, PHP CommitLint is English-only, but we're open to internationalization:

- Error messages use clear, simple English
- Configuration is language-agnostic (JSON)
- Future versions may support localized messages

## 📋 Changelog & Versioning

- **Versioning**: Follows [Semantic Versioning](https://semver.org/)
- **Changelog**: See [CHANGELOG.md](../CHANGELOG.md) for detailed release notes
- **Breaking Changes**: Clearly documented with migration guides

## 🎯 Integration Examples

### GitHub Actions

```yaml
- name: Validate Commits
  run: vendor/bin/php-commitlint validate "${{ github.event.head_commit.message }}"
```

### GitLab CI

```yaml
validate-commits:
  script: vendor/bin/php-commitlint validate "$CI_COMMIT_MESSAGE"
```

### Pre-commit Hooks

```yaml
# .pre-commit-config.yaml
repos:
  - repo: local
    hooks:
      - id: php-commitlint
        name: PHP CommitLint
        entry: vendor/bin/php-commitlint validate
        language: system
```

## 🎨 Customization & Extensions

### Custom Validation Patterns

```json
{
  "patterns": {
    "jira_ticket": "/PROJ-\\d+/",
    "breaking_change": "/^BREAKING CHANGE:/",
    "signed_off": "/Signed-off-by:/"
  }
}
```

### Organization-wide Configuration

Create a shared configuration package and distribute it across projects:

```bash
composer require --dev yourorg/commitlint-config
```

### IDE Integration

Most IDEs automatically respect Git hooks. For enhanced integration:

- **PhpStorm**: Enable "Run Git hooks" in VCS settings
- **VS Code**: Consider the GitLens extension
- **Vim/Neovim**: Use fugitive.vim for Git integration

---

**Last Updated**: 2025-06-20  
**Version**: 1.0.0

For the most up-to-date information, visit the [GitHub repository](https://github.com/dev-kraken/php-commitlint). 
