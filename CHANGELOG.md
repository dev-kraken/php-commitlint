# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Initial release of PHP CommitLint
- Conventional commit message validation
- Git hooks installation and management
- Configurable validation rules via `.commitlintrc.json`
- Support for custom commit types and scopes
- CLI commands for install, uninstall, validate, add, remove, and list
- Comprehensive test suite with Pest
- GitHub Actions CI/CD pipeline
- PHP CS Fixer for code style consistency
- PHPStan for static analysis
- Detailed documentation and contribution guidelines

### Features

- **Validation Engine**: Robust commit message validation following conventional commit standards
- **Git Hooks Integration**: Seamless installation and management of git hooks
- **Flexible Configuration**: JSON-based configuration with sensible defaults
- **Command Line Interface**: Full-featured CLI with multiple commands
- **Developer Experience**: Excellent error messages and helpful suggestions
- **Quality Assurance**: High test coverage and strict code quality standards

### Security

- Input validation for all commit message processing
- Safe command execution using Symfony Process component
- Configuration validation to prevent malicious configs
- Path traversal protection in file operations
- No eval() or dynamic code execution

## [1.0.0] - 2025-06-19

### Added

- Initial stable release
