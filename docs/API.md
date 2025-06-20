# API Documentation

This document provides detailed information about the PHP CommitLint API for developers who want to integrate or extend the library.

## Table of Contents

- [Application](#application)
- [Commands](#commands)
- [Services](#services)
- [Models](#models)
- [Enums](#enums)
- [Contracts](#contracts)

## Application

### `DevKraken\PhpCommitlint\Application`

The main application class that registers and manages all CLI commands.

```php
class Application extends SymfonyApplication
{
    public function __construct(?ServiceContainer $container = null)
    public function getLongVersion(): string
    public function getContainer(): ServiceContainer
}
```

## Commands

All commands extend `Symfony\Component\Console\Command\Command` and follow consistent patterns.

### `InstallCommand`

Installs Git hooks for commit message validation.

```php
class InstallCommand extends Command
{
    public function __construct(ServiceContainer $container)
    
    // Options:
    // --force, -f: Force installation even if hooks exist
    // --skip-config: Skip creating default configuration
}
```

### `UninstallCommand`

Removes Git hooks installed by PHP CommitLint.

```php
class UninstallCommand extends Command
{
    public function __construct(ServiceContainer $container)
    
    // Options:
    // --force, -f: Force uninstall without confirmation
}
```

### `ValidateCommand`

Validates commit messages against configuration rules.

```php
class ValidateCommand extends Command
{
    public function __construct(
        ValidationService $validationService,
        ConfigService $configService,
        LoggerService $logger
    )
    
    // Arguments:
    // message (optional): Commit message to validate
    
    // Options:
    // --file, -f: Read commit message from file
    // --quiet, -q: Suppress output (exit code only)
    // --verbose-errors: Show detailed error information
}
```

### `AddCommand`

Adds custom Git hooks.

```php
class AddCommand extends Command
{
    public function __construct(ServiceContainer $container)
    
    // Arguments:
    // hook: Git hook name (pre-commit, commit-msg, etc.)
    // command: Command to execute in the hook
    
    // Options:
    // --force, -f: Overwrite existing hook without confirmation
}
```

### `RemoveCommand`

Removes custom Git hooks.

```php
class RemoveCommand extends Command
{
    public function __construct(ServiceContainer $container)
    
    // Arguments:
    // hook: Git hook name to remove
    
    // Options:
    // --force, -f: Force removal without confirmation
}
```

### `ListCommand`

Lists installed Git hooks and configuration.

```php
class ListCommand extends Command
{
    public function __construct(ServiceContainer $container)
    
    // Options:
    // --verbose, -v: Show detailed information
    // --hooks-only: Show only hooks information
    // --config-only: Show only configuration information
}
```

## Services

### `ValidationService`

Core service for validating commit messages.

```php
class ValidationService
{
    public function validate(string $message, array $config): ValidationResult
}
```

**Methods:**

- `validate(string $message, array $config): ValidationResult`
  - Validates a commit message against the provided configuration
  - Returns a `ValidationResult` object with validation status and errors

### `ConfigService`

Manages configuration loading and validation.

```php
class ConfigService
{
    public function loadConfig(): array
    public function configExists(): bool
    public function getConfigPath(): string
    public function createDefaultConfig(): void
    public function getDefaultConfig(): array
    public function saveConfig(array $config): void
}
```

**Methods:**

- `loadConfig(): array`
  - Loads configuration from `.commitlintrc.json` or `composer.json`
  - Merges with default configuration
  - Validates configuration structure

- `configExists(): bool`
  - Check if configuration file exists

- `getConfigPath(): string`
  - Returns the path to the configuration file

- `createDefaultConfig(): void`
  - Creates a default configuration file

- `getDefaultConfig(): array`
  - Returns the default configuration array

- `saveConfig(array $config): void`
  - Saves configuration to file

### `HookService`

Manages Git hooks installation and management.

```php
class HookService implements HookServiceInterface
{
    public function isGitRepository(): bool
    public function hasExistingHooks(): bool
    public function hasInstalledHooks(): bool
    public function installHooks(): void
    public function uninstallHooks(): void
    public function getInstalledHooks(): array
    public function addCustomHook(string $hookName, string $command): void
    public function removeCustomHook(string $hookName): void
}
```

**Methods:**

- `isGitRepository(): bool`
  - Check if current directory is a Git repository

- `hasExistingHooks(): bool`
  - Check if Git hooks already exist

- `hasInstalledHooks(): bool`
  - Check if PHP CommitLint hooks are installed

- `installHooks(): void`
  - Install Git hooks for commit message validation

- `uninstallHooks(): void`
  - Remove installed Git hooks

- `getInstalledHooks(): array`
  - Get information about installed hooks

- `addCustomHook(string $hookName, string $command): void`
  - Add a custom Git hook with specified command

- `removeCustomHook(string $hookName): void`
  - Remove a custom Git hook

### `LoggerService`

Provides logging functionality throughout the application.

```php
class LoggerService implements LoggerInterface
{
    public function debug(string $message, array $context = []): void
    public function info(string $message, array $context = []): void
    public function warning(string $message, array $context = []): void
    public function error(string $message, array $context = []): void
}
```

## Models

### `CommitMessage`

Represents a parsed commit message.

```php
class CommitMessage
{
    public static function fromString(string $message): self
    public function getType(): ?string
    public function getScope(): ?string
    public function getSubject(): string
    public function getBody(): string
    public function getFooter(): string
    public function getSubjectLength(): int
    public function hasValidFormat(): bool
    public function hasBlankLineAfterSubject(): bool
    public function hasBlankLineBeforeFooter(): bool
    public function shouldSkipValidation(): bool
}
```

**Methods:**

- `fromString(string $message): self`
  - Static factory method to create CommitMessage from string

- `getType(): ?string`
  - Returns the commit type (feat, fix, etc.)

- `getScope(): ?string`
  - Returns the commit scope (optional)

- `getSubject(): string`
  - Returns the commit subject/description

- `getBody(): string`
  - Returns the commit body

- `getFooter(): string`
  - Returns the commit footer

- `getSubjectLength(): int`
  - Returns the length of the subject

- `hasValidFormat(): bool`
  - Check if commit follows conventional format

- `hasBlankLineAfterSubject(): bool`
  - Check if there's a blank line after subject

- `hasBlankLineBeforeFooter(): bool`
  - Check if there's a blank line before footer

- `shouldSkipValidation(): bool`
  - Check if commit should skip validation (merge, revert, etc.)

### `ValidationResult`

Represents the result of commit message validation.

```php
class ValidationResult
{
    public static function valid(?string $type = null, ?string $scope = null): self
    public static function invalid(array $errors, ?string $type = null, ?string $scope = null): self
    public static function error(string $error): self
    public function isValid(): bool
    public function getErrors(): array
    public function getErrorCount(): int
    public function getType(): ?string
    public function getScope(): ?string
}
```

**Methods:**

- `valid(?string $type = null, ?string $scope = null): self`
  - Static factory for valid result

- `invalid(array $errors, ?string $type = null, ?string $scope = null): self`
  - Static factory for invalid result with errors

- `error(string $error): self`
  - Static factory for error result

- `isValid(): bool`
  - Check if validation passed

- `getErrors(): array`
  - Get array of validation errors

- `getErrorCount(): int`
  - Get count of validation errors

- `getType(): ?string`
  - Get detected commit type

- `getScope(): ?string`
  - Get detected commit scope

## Enums

### `ExitCode`

Defines exit codes used by the application.

```php
enum ExitCode: int
{
    case SUCCESS = 0;
    case VALIDATION_FAILED = 1;
    case CONFIGURATION_ERROR = 2;
    case FILE_SYSTEM_ERROR = 3;
    case INVALID_ARGUMENT = 4;
    case RUNTIME_ERROR = 5;
    case PERMISSION_DENIED = 6;
    case NOT_GIT_REPOSITORY = 7;
}
```

## Contracts

### `HookServiceInterface`

Interface for hook service implementations.

```php
interface HookServiceInterface
{
    public function isGitRepository(): bool;
    public function hasExistingHooks(): bool;
    public function hasInstalledHooks(): bool;
    public function installHooks(): void;
    public function uninstallHooks(): void;
    public function getInstalledHooks(): array;
    public function addCustomHook(string $hookName, string $command): void;
    public function removeCustomHook(string $hookName): void;
}
```

## Usage Examples

### Basic Validation

```php
use DevKraken\PhpCommitlint\Services\ValidationService;
use DevKraken\PhpCommitlint\Services\ConfigService;

$configService = new ConfigService();
$validationService = new ValidationService();

$config = $configService->loadConfig();
$result = $validationService->validate('feat: add new feature', $config);

if ($result->isValid()) {
    echo "Valid commit message\n";
} else {
    echo "Invalid commit message:\n";
    foreach ($result->getErrors() as $error) {
        echo "- $error\n";
    }
}
```

### Custom Configuration

```php
use DevKraken\PhpCommitlint\Services\ConfigService;

$configService = new ConfigService();

// Create custom configuration
$customConfig = [
    'rules' => [
        'type' => [
            'allowed' => ['feat', 'fix', 'docs'],
        ],
        'subject' => [
            'max_length' => 50,
        ],
    ],
];

$configService->saveConfig($customConfig);
```

### Hook Management

```php
use DevKraken\PhpCommitlint\Services\HookService;

$hookService = new HookService();

// Check if in Git repository
if ($hookService->isGitRepository()) {
    // Install hooks
    $hookService->installHooks();
    
    // Add custom hook
    $hookService->addCustomHook('pre-commit', 'vendor/bin/pest');
    
    // List installed hooks
    $hooks = $hookService->getInstalledHooks();
    foreach ($hooks as $name => $info) {
        echo "$name: " . ($info['installed'] ? 'installed' : 'not installed') . "\n";
    }
}
```

## Configuration Schema

The configuration follows this JSON schema:

```json
{
  "auto_install": "boolean",
  "rules": {
    "type": {
      "required": "boolean",
      "allowed": "array<string>"
    },
    "scope": {
      "required": "boolean",
      "allowed": "array<string>"
    },
    "subject": {
      "min_length": "integer",
      "max_length": "integer",
      "case": "string (lower|upper|any)",
      "end_with_period": "boolean"
    },
    "body": {
      "max_line_length": "integer",
      "leading_blank": "boolean"
    },
    "footer": {
      "leading_blank": "boolean"
    }
  },
  "patterns": {
    "[pattern_name]": "string (regex)"
  },
  "hooks": {
    "[hook_name]": "boolean"
  },
  "format": {
    "type": "boolean",
    "scope": "string (optional|required)",
    "description": "boolean",
    "body": "string (optional|required)",
    "footer": "string (optional|required)"
  }
}
```

## Error Handling

All services follow consistent error handling patterns:

- **Configuration errors**: Throw `RuntimeException` with descriptive messages
- **Validation errors**: Return `ValidationResult` with error details
- **File system errors**: Throw appropriate exceptions with context
- **Invalid arguments**: Throw `InvalidArgumentException`

## Extension Points

### Custom Validation Rules

You can extend validation by:

1. Implementing custom pattern validation in configuration
2. Extending the `ValidationService` class
3. Creating custom command validators

### Custom Hooks

Add custom Git hooks using the `HookService`:

```php
$hookService->addCustomHook('pre-push', 'composer test');
```

### Custom Commands

Create new CLI commands by:

1. Extending `Symfony\Component\Console\Command\Command`
2. Using the `AsCommand` attribute
3. Registering in `Application.php`

## Security Considerations

- All file operations include path traversal protection
- Configuration files are size-limited (100KB max)
- Command execution uses Symfony Process for safety
- Input validation prevents injection attacks
- No dynamic code execution (`eval()`) is used 