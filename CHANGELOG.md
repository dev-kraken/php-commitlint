# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Released]

### Added
- Future enhancements will be listed here

## [1.0.0] - 2025-06-20

### 🚀 Initial Release - PHP CommitLint

The first stable release of PHP CommitLint - a comprehensive Git hooks and commit message linting tool for PHP projects.

### Added

#### 📚 Comprehensive Documentation Suite
- **Complete README.md** - User guide with examples, installation, and troubleshooting
- **docs/API.md** - Full API reference with usage examples and configuration schema
- **docs/FAQ.md** - Comprehensive FAQ covering installation, configuration, and common issues
- **docs/README.md** - Central documentation hub with architecture overview
- **CONTRIBUTING.md** - Complete contributor guidelines with development workflow
- **Banner images** - Professional dark/light theme banners for GitHub
- **CI/CD integration examples** - GitHub Actions and GitLab CI configurations
- **Error codes documentation** - Complete reference for exit codes (0-7)
- **Performance optimization guides** - Best practices for large repositories

#### ⚙️ Core Features
- **Conventional Commit Validation** - Full support for conventional commit message format
- **Git Hooks Management** - Install, uninstall, and manage Git hooks seamlessly
- **Flexible Configuration** - JSON-based configuration via `.commitlintrc.json`
- **Custom Types & Scopes** - Support for project-specific commit types and scopes
- **CLI Commands** - Complete command suite: install, uninstall, validate, add, remove, list
- **Security Features** - Input validation, safe command execution, path traversal protection

#### 🔧 Advanced CI/CD Pipeline
- **Multi-job architecture** - Separate validation, testing, and integration jobs
- **Multi-OS testing** - Ubuntu, Windows, and macOS support
- **PHP version matrix** - Testing on PHP 8.3 and 8.4
- **Integration testing** - Real Git repository setup and hook validation
- **Dependency analysis** - Unused dependency detection for PRs
- **Scheduled health checks** - Weekly dependency and security monitoring
- **Manual workflow triggers** - On-demand CI execution
- **Build status reporting** - Comprehensive job status summaries

#### 🎯 Developer Experience
- **Enhanced .gitattributes** - Better language detection and export handling
- **CI status badge** - Real-time build status in README
- **Coverage reporting** - Local test coverage generation
- **Code quality tools** - Integrated PHP CS Fixer, PHPStan, and security audit
- **Cross-platform compatibility** - Full Windows, macOS, and Linux support
- **Professional package configuration** - Optimized composer.json with proper metadata

#### 🧪 Quality Assurance
- **Comprehensive test suite** - 112 tests with 273 assertions using Pest framework
- **Static analysis** - PHPStan level max integration
- **Code style enforcement** - PHP CS Fixer with PSR-12 + additional rules
- **Security scanning** - Automated vulnerability detection
- **100% type coverage** - Full PHP 8.3+ type declarations

### Features

#### 🔍 Validation Engine
- **Conventional Commits** - Full support for conventional commit message format
- **Configurable Rules** - Customizable validation rules via JSON configuration
- **Smart Detection** - Automatic detection of merge, revert, fixup, and initial commits
- **Multi-line Support** - Proper handling of commit body and footer validation
- **Breaking Changes** - Special handling for breaking change indicators
- **Custom Patterns** - Support for organization-specific commit patterns

#### 🪝 Git Hooks Integration
- **Seamless Installation** - One-command setup with `php-commitlint install`
- **Safe Management** - Backup and restore existing hooks
- **Custom Commands** - Add custom pre-commit, pre-push hooks
- **Cross-platform** - Works on Windows, macOS, and Linux
- **Git Worktree Support** - Full compatibility with Git worktrees
- **Hook Detection** - Smart detection of existing hooks and conflicts

#### ⚙️ Configuration System
- **JSON-based** - Easy-to-read `.commitlintrc.json` configuration
- **Sensible Defaults** - Works out of the box with minimal configuration
- **Extensible** - Support for custom types, scopes, and rules
- **Validation** - Configuration file validation with helpful error messages
- **Caching** - Intelligent configuration caching for performance

#### 🎯 Command Line Interface
- **Rich Commands** - Full-featured CLI with multiple commands
- **Interactive Help** - Comprehensive help system and examples
- **Colored Output** - Beautiful, readable terminal output
- **Quiet Mode** - Silent operation for CI environments
- **Verbose Errors** - Detailed error reporting for debugging
- **Progress Indicators** - Clear feedback during operations

### Technical Specifications

#### 📊 Statistics
- **Total code**: 2,300+ lines of documentation
- **Test coverage**: 112 tests with 273 assertions
- **CI jobs**: 6 comprehensive validation and testing jobs
- **Supported platforms**: 3 operating systems, 2 PHP versions
- **Dependencies**: Minimal, only essential Symfony components

#### 🔧 Tools Integration
- **PHPStan**: Level max static analysis
- **PHP CS Fixer**: PSR-12 + additional rules
- **Pest**: Modern PHP testing framework
- **Composer**: Scripts for all development tasks
- **GitHub Actions**: Enterprise-grade CI/CD pipeline
- **Symfony Console**: Professional CLI framework

#### 🌐 Compatibility
- **PHP versions**: 8.3, 8.4+
- **Operating systems**: Linux, Windows, macOS  
- **Git versions**: All modern Git versions (2.0+)
- **CI platforms**: GitHub Actions, GitLab CI, and others

#### 🔒 Security
- **Input validation** - All commit message processing validated
- **Safe execution** - Symfony Process component for command execution
- **Configuration security** - Validation to prevent malicious configs
- **Path protection** - Path traversal protection in file operations
- **No dynamic execution** - No eval() or dynamic code execution
- **Audit integration** - Automated security vulnerability scanning

### Installation

```bash
# Install via Composer
composer global require devkraken/php-commitlint

# Or add to your project
composer require --dev devkraken/php-commitlint

# Install hooks
vendor/bin/php-commitlint install
```

### Quick Start

```bash
# Validate a commit message
php-commitlint validate "feat: add new feature"

# Install git hooks
php-commitlint install

# Add custom hook
php-commitlint add pre-commit "composer test"

# List installed hooks
php-commitlint list
```

---

## Project Information

- **Repository**: [devkraken/php-commitlint](https://github.com/devkraken/php-commitlint)
- **License**: MIT
- **Documentation**: [docs/README.md](docs/README.md)
- **Contributing**: [CONTRIBUTING.md](CONTRIBUTING.md)
- **Security**: [SECURITY.md](SECURITY.md)
- **Support**: [GitHub Issues](https://github.com/devkraken/php-commitlint/issues)
