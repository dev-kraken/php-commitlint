# Contributing to PHP CommitLint

Thank you for considering contributing to PHP CommitLint! This document outlines the process for contributing to this project.

## 🚀 Development Setup

1. **Fork the repository** on GitHub

2. **Clone your fork**:

   ```bash
   git clone https://github.com/your-username/php-commitlint.git
   cd php-commitlint
   ```

3. **Install dependencies**:

   ```bash
   composer install
   ```

4. **Install the git hooks** (optional, for development):
   ```bash
   ./bin/php-commitlint install
   ```

## 🛠️ Development Workflow

### Code Standards

This project follows these coding standards:

- **PSR-12** coding standard
- **PHP 8.2+** features and strict typing
- **PHPStan level 8** static analysis
- **Pest** for testing
- **Conventional Commits** for commit messages

### Before Making Changes

1. **Create a new branch** for your feature or bugfix:

   ```bash
   git checkout -b feature/your-feature-name
   # or
   git checkout -b fix/your-bug-fix
   ```

2. **Make sure all tests pass**:

   ```bash
   composer test
   ```

3. **Run static analysis**:

   ```bash
   composer analyse
   ```

4. **Check code style**:
   ```bash
   composer cs:check
   ```

### Making Changes

1. **Write tests first** (TDD approach preferred)
2. **Implement your changes**
3. **Update documentation** if needed
4. **Ensure all checks pass**:
   ```bash
   # Run all quality checks
   composer check
   
   # Or run individual checks
   composer test
   composer analyse
   composer cs:check
   ```

5. **Fix code style issues**:
   ```bash
   composer cs
   ```

### Commit Message Format

This project uses conventional commit format enforced by its own linting rules. Your commit messages should follow this pattern:

```
type(scope): description

[optional body]

[optional footer]
```

**Types:**

- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation only changes
- `style`: Changes that do not affect the meaning of the code
- `refactor`: Code change that neither fixes a bug nor adds a feature
- `perf`: Code change that improves performance
- `test`: Adding missing tests or correcting existing tests
- `chore`: Changes to the build process or auxiliary tools
- `ci`: Changes to CI/CD configuration
- `build`: Changes to build scripts or dependencies

**Scopes (examples):**

- `validation`: Validation service changes
- `hooks`: Git hooks related changes
- `config`: Configuration handling
- `cli`: Command-line interface changes
- `docs`: Documentation changes

**Examples:**

```bash
feat(validation): add support for custom regex patterns
fix(hooks): resolve issue with Windows path handling
docs(readme): update installation instructions
test(validation): add tests for edge cases
refactor(config): simplify configuration loading logic
chore(deps): update dependencies to latest versions
```

### Pull Request Process

1. **Update your branch** with the latest changes from main:

   ```bash
   git fetch origin
   git rebase origin/main
   ```

2. **Push your changes**:

   ```bash
   git push origin your-branch-name
   ```

3. **Create a Pull Request** on GitHub with:

   - Clear title following conventional commit format
   - Detailed description of changes
   - Reference any related issues (e.g., "Fixes #123")
   - Screenshots or examples if applicable
   - List of breaking changes (if any)

4. **Ensure CI passes** - all GitHub Actions checks must be green

5. **Respond to feedback** and make necessary changes

## 🧪 Testing

### Running Tests

```bash
# Run all tests
composer test

# Run tests with coverage
composer test -- --coverage-html coverage

# Run specific test file
./vendor/bin/pest tests/Unit/ValidationServiceTest.php

# Run specific test by filter
./vendor/bin/pest --filter="validates commit message format"

# Run only unit tests
composer test:unit

# Run only integration tests
composer test:integration
```

### Writing Tests

- Use **Pest** framework with descriptive test names
- Place unit tests in `tests/Unit/`
- Place integration tests in `tests/Integration/`
- Follow the existing naming conventions
- Test both happy path and edge cases
- Mock external dependencies appropriately

**Test Structure Example:**

```php
<?php

use DevKraken\PhpCommitlint\Services\ValidationService;
use DevKraken\PhpCommitlint\Models\ValidationResult;

describe('ValidationService', function () {
    beforeEach(function () {
        $this->validator = new ValidationService();
    });

    it('validates valid commit message format', function () {
        $config = createConfig();
        $message = 'feat: add new feature';

        $result = $this->validator->validate($message, $config);

        expect($result)->toBeInstanceOf(ValidationResult::class)
            ->and($result->isValid())->toBeTrue()
            ->and($result->getType())->toBe('feat');
    });

    it('rejects invalid commit message format', function () {
        $config = createConfig();
        $message = 'invalid commit message';

        $result = $this->validator->validate($message, $config);

        expect($result->isValid())->toBeFalse()
            ->and($result->getErrors())->toHaveCount(1);
    });
});
```

## 🔧 Code Quality Tools

### Available Composer Scripts

```bash
# Testing
composer test              # Run all tests
composer test:unit         # Run unit tests only
composer test:integration  # Run integration tests only

# Code Quality
composer analyse           # Run PHPStan static analysis
composer cs               # Fix code style issues
composer cs:check         # Check code style (no changes)

# Combined Commands
composer check            # Run all quality checks (style, analysis, tests)
composer fix              # Fix code style and run tests
```

### PHP CS Fixer

Fix code style issues automatically:

```bash
composer cs
```

Check code style without making changes:

```bash
composer cs:check
```

### PHPStan Static Analysis

Run static analysis:

```bash
composer analyse
```

The project uses **PHPStan level 8** (strict mode) to ensure high code quality.

### Security Audit

Check for security vulnerabilities:

```bash
composer audit
```

### Make Commands

You can also use Make commands for common tasks:

```bash
make help              # Show available commands
make install           # Install dependencies
make test              # Run tests
make test-coverage     # Run tests with coverage
make lint              # Check code style
make fix               # Fix code style issues
make phpstan           # Run static analysis
make all               # Run all checks
make clean             # Clean generated files
make ci                # Run CI pipeline locally
```

## 📚 Documentation

When adding new features or making changes:

1. **Update the README.md** if the public API changes
2. **Add or update docblocks** for new/modified methods
3. **Update configuration examples** if new options are added
4. **Add examples** to demonstrate new functionality
5. **Update the CHANGELOG.md** following Keep a Changelog format

### Documentation Standards

- Use clear, concise language
- Include code examples for complex features
- Document all public methods and classes
- Keep examples up-to-date with the codebase
- Use proper markdown formatting

## 🐛 Reporting Issues

When reporting issues, please include:

1. **PHP version** (`php --version`)
2. **Composer version** (`composer --version`)
3. **Operating system** and version
4. **PHP CommitLint version**
5. **Steps to reproduce** the issue
6. **Expected vs actual behavior**
7. **Error messages or logs**
8. **Configuration file** (if relevant)
9. **Sample commit message** (if validation-related)

### Issue Labels

We use these labels to categorize issues:

- `bug` - Something isn't working
- `enhancement` - New feature or request
- `documentation` - Improvements or additions to documentation
- `good first issue` - Good for newcomers
- `help wanted` - Extra attention is needed
- `security` - Security-related issues

## 💡 Feature Requests

When proposing new features:

1. **Check existing issues** to avoid duplicates
2. **Describe the problem** you're trying to solve
3. **Propose a solution** with examples
4. **Consider backward compatibility**
5. **Think about configuration options** needed

## 🏗️ Architecture Guidelines

### Project Structure

```
src/
├── Application.php           # Main application class
├── Commands/                 # CLI commands
├── Contracts/               # Interfaces
├── Enums/                   # Enumerations
├── Models/                  # Domain models
├── ServiceContainer.php     # Dependency injection
└── Services/                # Business logic services

tests/
├── Integration/             # End-to-end tests
├── Unit/                   # Unit tests
├── Pest.php                # Pest configuration
└── TestCase.php            # Base test class
```

### Design Principles

- **Single Responsibility Principle** - Each class has one reason to change
- **Dependency Injection** - Use constructor injection for dependencies
- **Immutability** - Prefer immutable objects where possible
- **Type Safety** - Use strict types and proper type hints
- **Error Handling** - Use exceptions for error conditions
- **Testing** - Write tests for all public methods

### Adding New Commands

1. Create command class in `src/Commands/`
2. Extend `Symfony\Component\Console\Command\Command`
3. Use `AsCommand` attribute for metadata
4. Register in `Application.php`
5. Add comprehensive tests
6. Update documentation

### Adding New Services

1. Create service class in `src/Services/`
2. Define interface in `src/Contracts/` (if needed)
3. Register in `ServiceContainer.php`
4. Follow dependency injection patterns
5. Add unit tests with mocking
6. Document public methods

## 🔒 Security Guidelines

- **Validate all input** from users and configuration
- **Use Symfony Process** for command execution
- **Prevent path traversal** in file operations
- **Limit file sizes** to prevent DoS attacks
- **Avoid eval()** or dynamic code execution
- **Sanitize output** to prevent injection attacks

## 🌍 Internationalization

Currently, PHP CommitLint is English-only, but we're open to internationalization contributions:

- Use clear, simple English
- Avoid colloquialisms or cultural references
- Consider error message clarity for non-native speakers

## 🤝 Getting Help

- **Documentation**: Check the [README.md](README.md) first
- **Examples**: Look at the [examples/](examples/) directory
- **Tests**: Review test files for usage patterns
- **Issues**: Search [existing issues](https://github.com/dev-kraken/php-commitlint/issues)
- **Discussions**: Use [GitHub Discussions](https://github.com/dev-kraken/php-commitlint/discussions) for questions

## 📜 Code of Conduct

Please be respectful and constructive in all interactions. We're committed to providing a welcoming environment for all contributors, regardless of:

- Experience level
- Gender identity and expression
- Sexual orientation
- Disability
- Personal appearance
- Body size
- Race
- Ethnicity
- Age
- Religion
- Nationality

## 📄 License

By contributing to PHP CommitLint, you agree that your contributions will be licensed under the [MIT License](LICENSE).

## 🙏 Recognition

Contributors will be recognized in:

- **CHANGELOG.md** for their contributions
- **GitHub contributors page**
- **Release notes** for significant features

Thank you for contributing to PHP CommitLint! 🎉
