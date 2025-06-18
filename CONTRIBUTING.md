# Contributing to PHP CommitLint

Thank you for considering contributing to PHP CommitLint! This document outlines the process for contributing to this project.

## Development Setup

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

## Development Workflow

### Code Standards

This project follows these coding standards:

- **PSR-12** coding standard
- **PHP 8.2+** features and strict typing
- **PHPStan level 8** static analysis
- **Pest** for testing

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
   composer phpstan
   ```

4. **Check code style**:
   ```bash
   composer cs-fix -- --dry-run --diff
   ```

### Making Changes

1. **Write tests first** (TDD approach preferred)
2. **Implement your changes**
3. **Update documentation** if needed
4. **Ensure all checks pass**:
   ```bash
   # Run all checks
   composer test
   composer phpstan
   composer cs-fix -- --dry-run --diff
   ```

### Commit Message Format

This project uses conventional commit format. Your commit messages should follow this pattern:

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

**Examples:**

```
feat(validation): add support for custom regex patterns
fix(hooks): resolve issue with Windows path handling
docs(readme): update installation instructions
test(validation): add tests for edge cases
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

   - Clear title and description
   - Reference any related issues
   - Screenshots or examples if applicable

4. **Ensure CI passes** - all GitHub Actions checks must be green

5. **Respond to feedback** and make necessary changes

## Testing

### Running Tests

```bash
# Run all tests
composer test

# Run tests with coverage
composer test -- --coverage-html coverage

# Run specific test file
./vendor/bin/pest tests/Unit/ValidationServiceTest.php

# Run tests with filter
./vendor/bin/pest --filter="validation"
```

### Writing Tests

- Use **Pest** framework
- Place unit tests in `tests/Unit/`
- Place feature tests in `tests/Feature/`
- Follow the existing naming conventions
- Test both happy path and edge cases

Example test structure:

```php
<?php

use DevKraken\PhpCommitlint\Services\ValidationService;

it('validates commit message format', function () {
    $service = new ValidationService();
    $config = createConfig();

    $result = $service->validate('feat: add new feature', $config);

    expect($result->isValid())->toBeTrue();
});
```

## Code Quality Tools

### PHP CS Fixer

Fix code style issues:

```bash
composer cs-fix
```

### PHPStan

Run static analysis:

```bash
composer phpstan
```

### Security Audit

Check for security vulnerabilities:

```bash
composer audit
```

## Documentation

When adding new features or making changes:

1. **Update the README.md** if the public API changes
2. **Add or update docblocks** for new/modified methods
3. **Update examples** in documentation
4. **Consider adding to the FAQ** section

## Reporting Issues

When reporting issues, please include:

1. **PHP version** (`php --version`)
2. **Composer version** (`composer --version`)
3. **Operating system**
4. **Steps to reproduce**
5. **Expected vs actual behavior**
6. **Error messages or logs**

## Getting Help

- Check the [README.md](README.md) for common use cases
- Look at existing [issues](https://github.com/dev-kraken/php-commitlint/issues)
- Review the [test files](tests/) for usage examples

## Code of Conduct

Please be respectful and constructive in all interactions. We're here to build something great together!

## License

By contributing to PHP CommitLint, you agree that your contributions will be licensed under the MIT License.
