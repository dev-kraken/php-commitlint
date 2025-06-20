# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Released]

### Added
- Future enhancements will be listed here

## [Unreleased]

### Added
- Development in progress

## [1.1.0] - 2025-01-22

### 🚀 Major Cross-Platform Release & Critical CI Fixes

This release combines **Windows compatibility**, **team-wide automation**, **path portability**, and **critical CI test fixes** - making PHP CommitLint work seamlessly across all development environments.

#### 🪟 Windows Support & Compatibility
- **Enhanced PHP Binary Detection** - Automatic detection of Windows PHP installations
  - ✅ XAMPP: `C:\xampp\php\php.exe`
  - ✅ WAMP: `C:\wamp\bin\php\php8.x\php.exe`  
  - ✅ Laragon: `C:\laragon\bin\php\php8.x\php.exe`
  - ✅ Standalone: `C:\php\php.exe`
  - ✅ System PATH: `php.exe` and `php`
- **Windows Batch Wrapper** - Added `php-commitlint.bat` for native Windows CLI support
- **Git Bash Compatibility** - Smart path conversion (Windows drives: `C:` → `/c`)
- **Cross-Platform Path Handling** - Improved `normalizePath()` for shell script compatibility
- **PowerShell & Command Prompt Support** - Works in all Windows terminal environments

#### 🤝 Team-Wide Pre-Commit Commands
- **Configuration-Based Pre-Commit Hooks** - Share pre-commit commands via `.commitlintrc.json`
  ```json
  {
    "auto_install": true,
    "hooks": { "pre-commit": true },
    "pre_commit_commands": {
      "Code Style": "vendor/bin/php-cs-fixer fix --dry-run --diff",
      "Static Analysis": "vendor/bin/phpstan analyse --no-progress",
      "Tests": "vendor/bin/pest --filter=Unit"
    }
  }
  ```
- **Automatic Team Installation** - When developers run `composer install`, hooks install automatically
- **Version-Controlled Quality Checks** - Commit and share quality standards across the team
- **Failure Handling** - Pre-commit stops on first failing command for fast feedback

#### 🔧 Path Portability & Reliability  
- **Relative Path Support** - Hooks now use portable relative paths instead of absolute paths
  - **Before**: `/srv/http/project/vendor/bin/php-commitlint` (breaks when moved)
  - **After**: `vendor/bin/php-commitlint` (works anywhere)
- **Clone-Anywhere Compatibility** - Hooks work regardless of project location
- **Smart Binary Detection** - Prefer system-wide `php` over hardcoded paths
- **Shell Script Optimization** - Improved quoting and path handling for edge cases

#### 🐛 Critical CI/CD Test Fixes
- **Cross-Platform CI Test Failures** - Resolved RuntimeException type mismatches in ConfigService tests
  - Fixed `validateConfig()` method to throw `RuntimeException` instead of `InvalidArgumentException` for consistency
  - Standardized error messages for JSON object validation to match test expectations  
  - Corrected file read failure error messages to use consistent format across platforms
- **Exception Message Consistency** - Unified error message format for configuration validation
  - Subject length validation now properly throws expected RuntimeException
  - JSON object validation messages no longer include file path details for cleaner error handling
  - File read permissions errors now use consistent "Failed to read file" message format

#### 📚 Enhanced Documentation
- **Windows Installation Guide** - Complete setup instructions for Windows environments
- **Team Collaboration Workflows** - How to configure shared pre-commit commands
- **Troubleshooting Guide** - Windows-specific solutions and common issues
- **Cross-Platform Examples** - Configuration examples for different environments
- **FAQ Expansion** - Added Windows and team-specific frequently asked questions

#### 🧪 Testing & Quality Improvements
- **Integration Tests for New Features** - Comprehensive testing of pre-commit commands
- **Cross-Platform Test Coverage** - Enhanced Windows compatibility testing
- **Test Suite Reliability** - All 112 tests now pass consistently across CI environments
- **PHPStan Compliance** - Fixed static analysis issues with Pest expectations
- **Code Style Consistency** - Updated spacing and formatting standards

### Fixed
- **Windows "PHP CommitLint not found" Error** - Enhanced binary detection resolves path issues
- **Absolute Path Brittleness** - Hooks now work when projects are moved or cloned elsewhere
- **Team Setup Friction** - Auto-install eliminates manual hook setup for team members
- **Cross-Platform Inconsistencies** - Unified behavior across Windows, macOS, and Linux
- **PHPStan Errors** - Fixed `->not->` syntax to proper `->not()` method calls in tests
- **CI Test Failures** - Resolved all cross-platform test failures across macOS, Windows, and Linux

#### CI/CD Impact
- ✅ **macOS CI**: Now passes all tests without RuntimeException errors
- ✅ **Windows CI**: Resolved exception type conflicts and file permission test issues  
- ✅ **Linux CI**: Fixed JSON object validation message matching

### Added Files
- `bin/php-commitlint.bat` - Windows batch wrapper for native CLI support
- `examples/commitlintrc.team.json` - Complete team collaboration configuration example

### Enhanced Files
- `src/Services/HookService.php` - Windows binary detection, pre-commit commands, portable paths
- `src/Services/ConfigService.php` - Added `pre_commit_commands` configuration support and standardized exception handling
- `README.md` - Windows support section, team workflow documentation
- `docs/FAQ.md` - Windows troubleshooting, team installation guides
- `composer.json` - Added Windows batch file to binary list
- `.gitattributes` - Proper line ending handling for batch files

### Technical Improvements
- **Binary Detection Algorithm** - Platform-aware PHP executable discovery
- **Hook Content Generation** - Dynamic pre-commit command injection
- **Path Normalization** - Cross-platform shell script path handling
- **Error Handling** - Better failure messages for debugging and consistent exception types
- **Security Validation** - Command escaping and validation for pre-commit commands
- **Error Handling Standardization** - Consistent exception types and messages throughout ConfigService
- **Cross-Platform Compatibility** - Enhanced test compatibility for Windows, macOS, and Linux CI runners

### Migration Guide
This release is **fully backward compatible**. Existing installations will continue to work without changes.

**To enable new features:**
1. **Windows users**: Hooks will automatically use improved detection
2. **Teams**: Add `pre_commit_commands` to your `.commitlintrc.json` to share quality checks
3. **Path issues**: Re-run `php-commitlint install` to get portable paths

### Performance Impact
- **Startup time**: No significant change (~50ms)
- **Memory usage**: Minimal increase (< 1MB)
- **Hook execution**: Faster relative path resolution
- **Configuration loading**: Efficient caching maintained

### Breaking Changes
None. This release maintains full backward compatibility.

## [1.0.1] - 2025-01-16

### 🐛 Bug Fixes & CI Improvements

This patch release resolves critical CI/CD issues and improves cross-platform compatibility.

#### Fixed
- **Windows CI Compatibility** - Fixed path normalization issues on Windows CI runners
  - Enhanced `ConfigService::normalizePath()` to handle Windows short path names (8.3 format)
  - Resolved conflicts between `realpath()` and `getcwd()` returning different path formats
- **PHPStan Type Errors** - Added proper type assertions for reflection method results
- **Cross-Platform Testing** - Improved test compatibility across operating systems
  - Skip Windows-incompatible permission tests
  - Enhanced cleanup functions with error suppression for non-critical warnings
- **CLI Command Conflicts** - Resolved Symfony Console command conflicts
  - Renamed `list` command to `status` to avoid conflict with built-in list command
  - Removed custom `--verbose` option to use Symfony Console's built-in verbose handling
- **CI Interactive Prompts** - Fixed automated CI execution issues
  - Added `--force` flag support to prevent interactive confirmations
  - Updated integration tests and CI workflow to use non-interactive mode

#### Changed
- **Command Name**: `list` command renamed to `status` (maintains same functionality)
- **Verbose Flag**: Now uses standard Symfony Console `--verbose` instead of custom implementation

#### Technical Improvements
- Enhanced Windows path handling for CI environments
- Improved error handling and cleanup processes
- Better cross-platform test coverage
- Streamlined CI workflow execution

### Migration Notes
- Replace `php-commitlint list` with `php-commitlint status` in scripts and documentation
- The `--verbose` flag continues to work as before with standard Symfony Console behavior

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
