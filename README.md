# PHP CommitLint 🎯

A powerful Git hooks and commit message linting tool for PHP projects - combining the best of husky and commitlint in a native PHP implementation.

[![PHP Version](https://img.shields.io/badge/php-%5E8.2-blue)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-pest-green)](https://pestphp.com/)

## 🚀 Features

- **🎯 Commit Message Validation** - Enforce conventional commit format with customizable rules
- **🪝 Git Hooks Management** - Easy installation and management of Git hooks
- **⚙️ Flexible Configuration** - Configure via `.commitlintrc.json` or `composer.json`
- **🔧 Developer Friendly** - Beautiful CLI output with helpful error messages
- **📦 Zero Dependencies** - Pure PHP implementation using only Symfony Console
- **🧪 Fully Tested** - Comprehensive test suite with Pest
- **🎨 Modern PHP** - PHP 8.2+ with strict types and modern syntax

## 📦 Installation

Install via Composer:

```bash
composer require --dev dev-kraken/php-commitlint
```

## 🔧 Quick Start

### 1. Install Git Hooks

```bash
vendor/bin/php-commitlint install
```

### 2. Start Making Commits!

```bash
# ✅ Valid commits
git commit -m "feat: add user authentication"
git commit -m "fix(auth): resolve login validation issue"
git commit -m "docs: update README with examples"

# ❌ Invalid commits (will be rejected)
git commit -m "added new feature"  # Missing type
git commit -m "FIX: something"     # Invalid type case
```

## 📋 Commands

### Install Hooks

```bash
vendor/bin/php-commitlint install [--force]
```

### Validate Commit Message

```bash
# Validate current commit message
vendor/bin/php-commitlint validate

# Validate specific message
vendor/bin/php-commitlint validate "feat: add new feature"

# Validate from file
vendor/bin/php-commitlint validate --file=commit.txt
```

### Manage Custom Hooks

```bash
# Add custom hook
vendor/bin/php-commitlint add pre-commit "vendor/bin/pest"
vendor/bin/php-commitlint add pre-push "vendor/bin/phpstan analyse"

# Remove hook
vendor/bin/php-commitlint remove pre-commit

# List all hooks
vendor/bin/php-commitlint list
```

### Uninstall

```bash
vendor/bin/php-commitlint uninstall
```

## ⚙️ Configuration

### Basic Configuration (`.commitlintrc.json`)

```json
{
  "auto_install": false,
  "rules": {
    "type": {
      "required": true,
      "allowed": ["feat", "fix", "docs", "style", "refactor", "perf", "test", "chore", "ci", "build", "revert"]
    },
    "scope": {
      "required": false,
      "allowed": ["auth", "ui", "api", "db"]
    },
    "subject": {
      "min_length": 1,
      "max_length": 100,
      "case": "any",
      "end_with_period": false
    },
    "body": {
      "max_line_length": 100,
      "leading_blank": true
    }
  }
}
```

### Advanced Configuration

```json
{
  "auto_install": true,
  "rules": {
    "type": {
      "required": true,
      "allowed": ["feat", "fix", "docs", "refactor", "test", "chore"]
    },
    "scope": {
      "required": true,
      "allowed": ["auth", "api", "ui", "db", "config"]
    },
    "subject": {
      "min_length": 5,
      "max_length": 80,
      "case": "lower",
      "end_with_period": false
    },
    "body": {
      "max_line_length": 72,
      "leading_blank": true
    },
    "footer": {
      "leading_blank": true
    }
  },
  "patterns": {
    "breaking_change": "/^BREAKING CHANGE:/",
    "issue_reference": "/(?:close[sd]?|fix(?:e[sd])?|resolve[sd]?)\\s+#\\d+/i"
  },
  "hooks": {
    "commit-msg": true,
    "pre-commit": false,
    "pre-push": false
  }
}
```

### Configuration in `composer.json`

```json
{
  "extra": {
    "php-commitlint": {
      "auto_install": true,
      "rules": {
        "type": {
          "allowed": ["feat", "fix", "docs", "test", "chore"]
        }
      }
    }
  }
}
```

## 📝 Commit Message Format

This package enforces the [Conventional Commits](https://conventionalcommits.org/) specification:

```
<type>[optional scope]: <description>

[optional body]

[optional footer(s)]
```

### Examples

```bash
# Simple commit
feat: add user registration

# With scope
feat(auth): add JWT token validation

# With body and footer
feat(api): add user endpoints

Add comprehensive user management endpoints including:
- GET /api/users
- POST /api/users
- PUT /api/users/{id}
- DELETE /api/users/{id}

Closes #123
```

### Default Types

- `feat` - New features
- `fix` - Bug fixes
- `docs` - Documentation changes
- `style` - Code style changes (formatting, etc)
- `refactor` - Code refactoring
- `perf` - Performance improvements
- `test` - Adding or updating tests
- `chore` - Maintenance tasks
- `ci` - CI/CD changes
- `build` - Build system changes
- `revert` - Reverting previous commits

## 🧪 Testing

Run the test suite with Pest:

```bash
# Run all tests
composer test

# Run with coverage
vendor/bin/pest --coverage

# Run specific test
vendor/bin/pest tests/Unit/ValidationServiceTest.php
```

## 🔄 Integration with CI/CD

### GitHub Actions

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2
      - name: Install dependencies
        run: composer install
      - name: Run tests
        run: composer test
      - name: Validate commit messages
        run: vendor/bin/php-commitlint validate "${{ github.event.head_commit.message }}"
```

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Run tests (`composer test`)
5. Commit your changes (`git commit -m "feat: add amazing feature"`)
6. Push to the branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Inspired by [husky](https://github.com/typicode/husky) and [commitlint](https://github.com/conventional-changelog/commitlint)
- Built with [Symfony Console](https://symfony.com/doc/current/components/console.html)
- Tested with [Pest](https://pestphp.com/)

## 📞 Support

- 📧 Email: soman@devkraken.com
- 🐛 Issues: [GitHub Issues](https://github.com/dev-kraken/php-commitlint/issues)
- 💬 Discussions: [GitHub Discussions](https://github.com/dev-kraken/php-commitlint/discussions)

---

Made with ❤️ by [DevKraken](https://github.com/dev-kraken)
