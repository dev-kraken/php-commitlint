# Frequently Asked Questions (FAQ)

This document answers common questions about PHP CommitLint installation, configuration, and usage.

## Table of Contents

- [Installation & Setup](#installation--setup)
- [Configuration](#configuration)
- [Usage](#usage)
- [Git Hooks](#git-hooks)
- [Validation Rules](#validation-rules)
- [Troubleshooting](#troubleshooting)
- [Integration](#integration)
- [Performance](#performance)
- [Security](#security)

## Installation & Setup

### Q: How do I install PHP CommitLint?

**A:** Install via Composer as a development dependency:

```bash
composer require --dev dev-kraken/php-commitlint
```

Then install the Git hooks:

```bash
vendor/bin/php-commitlint install
```

### Q: What PHP version is required?

**A:** PHP CommitLint requires PHP 8.3 or higher. It uses modern PHP features like enums, readonly properties, typed class constants, and union types.

### Q: Can I use this in a non-Composer project?

**A:** PHP CommitLint is designed for Composer-based projects. The autoloader and dependency management rely on Composer. For non-Composer projects, consider using the original Node.js-based commitlint.

### Q: Why do I get "Unable to find composer autoloader" error?

**A:** This error occurs when:
1. Composer dependencies aren't installed (`composer install`)
2. The vendor directory is missing
3. You're running the command from the wrong directory

Solution: Run `composer install` in your project root.

## Configuration

### Q: Where should I put my configuration?

**A:** You have two options:

1. **`.commitlintrc.json`** in your project root (recommended)
2. **`composer.json`** under the `extra.php-commitlint` key

The `.commitlintrc.json` file takes precedence if both exist.

### Q: How do I customize allowed commit types?

**A:** Edit your configuration file:

```json
{
  "rules": {
    "type": {
      "allowed": ["feat", "fix", "docs", "test", "custom"]
    }
  }
}
```

### Q: Can I require scopes for certain types only?

**A:** Currently, scope requirements are global, not per-type. You can either require scopes for all commits or make them optional. This is a potential future enhancement.

### Q: How do I disable certain validation rules?

**A:** Set rules to less restrictive values:

```json
{
  "rules": {
    "subject": {
      "min_length": 1,
      "max_length": 1000,
      "case": "any",
      "end_with_period": false
    }
  }
}
```

### Q: Can I use regex patterns for custom validation?

**A:** Yes! Use the `patterns` configuration:

```json
{
  "patterns": {
    "ticket_reference": "/JIRA-\\d+/",
    "breaking_change": "/^BREAKING CHANGE:/"
  }
}
```

## Usage

### Q: How do I validate a specific commit message?

**A:** Use the validate command:

```bash
# Validate a specific message
vendor/bin/php-commitlint validate "feat: add new feature"

# Validate from file
vendor/bin/php-commitlint validate --file=commit.txt

# Get only exit code (for scripts)
vendor/bin/php-commitlint validate --quiet
```

### Q: What commit messages are automatically skipped?

**A:** These special commit types skip validation:
- Merge commits: `Merge branch "feature" into main`
- Revert commits: `Revert "feat: add feature"`
- Initial commits: `Initial commit`
- Fixup commits: `fixup! feat: add feature`

### Q: How do I see detailed error information?

**A:** Use the `--verbose-errors` flag:

```bash
vendor/bin/php-commitlint validate --verbose-errors
```

### Q: Can I use this with interactive commits?

**A:** Yes! Once hooks are installed, they automatically validate during:
- `git commit`
- `git commit -m "message"`
- IDE commit interfaces
- Any tool that triggers Git hooks

## Git Hooks

### Q: Which Git hooks does PHP CommitLint install?

**A:** By default, it installs:
- `commit-msg`: Validates commit messages
- Optionally `pre-commit` and `pre-push` if configured

### Q: How do I add custom hooks?

**A:** Use the add command:

```bash
# Add a pre-commit hook to run tests
vendor/bin/php-commitlint add pre-commit "vendor/bin/pest"

# Add a pre-push hook to run static analysis
vendor/bin/php-commitlint add pre-push "vendor/bin/phpstan analyse"
```

### Q: What happens if I already have Git hooks?

**A:** PHP CommitLint will ask for confirmation before overwriting existing hooks. Use `--force` to overwrite without prompting.

### Q: How do I remove specific hooks?

**A:** Use the remove command:

```bash
vendor/bin/php-commitlint remove pre-commit
```

### Q: Can I see what hooks are installed?

**A:** Yes, use the list command:

```bash
vendor/bin/php-commitlint list
vendor/bin/php-commitlint list --verbose  # More details
```

## Validation Rules

### Q: What is the conventional commit format?

**A:** The format is:

```
<type>[optional scope]: <description>

[optional body]

[optional footer(s)]
```

Examples:
- `feat: add user authentication`
- `fix(auth): resolve login issue`
- `docs(readme): update installation guide`

### Q: How strict is the subject case validation?

**A:** You can configure it in three ways:
- `"case": "lower"` - Must start with lowercase
- `"case": "upper"` - Must start with uppercase  
- `"case": "any"` - No case restriction (default)

### Q: Can I allow empty scopes?

**A:** Yes, set scope as not required:

```json
{
  "rules": {
    "scope": {
      "required": false
    }
  }
}
```

### Q: How do I handle breaking changes?

**A:** Use the conventional format:

```
feat!: redesign user API

BREAKING CHANGE: The user API has been completely redesigned.
Existing tokens will be invalidated.
```

Or configure a pattern to detect breaking changes:

```json
{
  "patterns": {
    "breaking_change": "/^BREAKING CHANGE:/"
  }
}
```

## Troubleshooting

### Q: Why is my commit being rejected when it looks correct?

**A:** Common issues:
1. **Hidden characters**: Copy-paste can introduce invisible characters
2. **Case sensitivity**: Check if your configuration requires specific cases
3. **Length limits**: Subject might exceed maximum length
4. **Missing blank lines**: Body/footer need blank line separation

Use `--verbose-errors` to see specific validation failures.

### Q: Hooks aren't running - what's wrong?

**A:** Check these:
1. Are you in a Git repository? (`git status`)
2. Are hooks installed? (`vendor/bin/php-commitlint list`)
3. Are hook files executable? (`ls -la .git/hooks/`)
4. Is the PHP CommitLint binary accessible?

### Q: How do I debug hook execution?

**A:** Check the Git hook files in `.git/hooks/`. They should contain references to your PHP CommitLint installation. You can also run hooks manually:

```bash
# Test commit-msg hook
echo "feat: test message" | .git/hooks/commit-msg
```

### Q: Performance is slow - how to optimize?

**A:** Performance tips:
1. Keep configuration files small
2. Limit regex patterns complexity
3. Use `--quiet` for script usage
4. Consider disabling verbose logging in production

### Q: How do I temporarily bypass validation?

**A:** For emergency commits, you can:

1. Use `git commit --no-verify` (bypasses all hooks)
2. Temporarily uninstall hooks: `vendor/bin/php-commitlint uninstall`
3. Use allowed bypass patterns if configured

## Integration

### Q: How do I integrate with GitHub Actions?

**A:** Add this to your workflow:

```yaml
name: Validate Commits
on: [push, pull_request]
jobs:
  validate:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
             - name: Setup PHP
         uses: shivammathur/setup-php@v2
         with:
           php-version: 8.3
      - name: Install dependencies
        run: composer install
      - name: Validate commit message
        run: vendor/bin/php-commitlint validate "${{ github.event.head_commit.message }}"
```

### Q: Can I use this with pre-commit.com?

**A:** While PHP CommitLint is designed to work independently, you could create a pre-commit.com hook. However, the built-in Git hooks are more efficient.

### Q: How do I integrate with IDEs?

**A:** Most IDEs respect Git hooks automatically. For JetBrains IDEs (PhpStorm, WebStorm), enable "Run Git hooks" in VCS settings.

### Q: Can I use this in monorepos?

**A:** Yes! Install PHP CommitLint in the root of your monorepo. You can configure scopes to match your packages:

```json
{
  "rules": {
    "scope": {
      "required": true,
      "allowed": ["api", "frontend", "admin", "shared"]
    }
  }
}
```

## Performance

### Q: Does PHP CommitLint slow down commits?

**A:** The performance impact is minimal (typically <100ms). The tool is optimized for speed with:
- Lazy loading of services
- Efficient regex patterns
- Minimal file I/O

### Q: How much memory does it use?

**A:** PHP CommitLint typically uses 2-5MB of memory, which is very lightweight compared to Node.js-based alternatives.

### Q: Can I run validation in parallel?

**A:** For single commits, parallelization isn't beneficial. For batch validation (CI/CD), you can validate multiple commits in parallel using shell scripting.

## Security

### Q: Is it safe to add custom hooks?

**A:** PHP CommitLint includes security validations for custom hooks:
- Commands are validated for dangerous patterns
- Length limits prevent buffer overflow attacks
- Path traversal protection for file operations

However, only add hooks from trusted sources.

### Q: How does PHP CommitLint protect against malicious configs?

**A:** Security measures include:
- File size limits (100KB max)
- Path traversal prevention
- JSON validation
- No dynamic code execution
- Input sanitization

### Q: Can I use this in environments with restricted permissions?

**A:** PHP CommitLint needs:
- Read/write access to `.git/hooks/`
- Read access to configuration files
- Execute permissions for PHP

It doesn't require network access or system-level permissions.

## Advanced Usage

### Q: Can I extend PHP CommitLint with custom validation?

**A:** Yes, several ways:
1. **Custom patterns**: Use regex patterns in configuration
2. **Extend classes**: Create custom validation services
3. **Custom commands**: Add new CLI commands
4. **Hooks**: Use pre-commit hooks for additional validation

### Q: How do I create organization-wide configurations?

**A:** Create a shared configuration package:

1. Create a Composer package with your configuration
2. Install it in projects: `composer require --dev yourorg/commitlint-config`
3. Reference it in projects or copy the configuration

### Q: Can I validate commit messages from CI without Git hooks?

**A:** Yes! Use the validate command directly:

```bash
# Validate latest commit
vendor/bin/php-commitlint validate "$(git log -1 --pretty=%B)"

# Validate PR commits
git log --pretty=%B origin/main..HEAD | while read -r message; do
  vendor/bin/php-commitlint validate "$message"
done
```

### Q: How do I migrate from other commit linting tools?

**A:** Migration steps:
1. Remove existing tools (commitlint, husky, etc.)
2. Install PHP CommitLint
3. Convert configuration to PHP CommitLint format
4. Test with existing commit messages
5. Update CI/CD pipelines

Most conventional commit configurations are compatible with minimal changes.

## Still Have Questions?

- 📖 Check the [README](../README.md) for detailed documentation
- 🔍 Search [existing issues](https://github.com/dev-kraken/php-commitlint/issues)
- 💬 Start a [discussion](https://github.com/dev-kraken/php-commitlint/discussions)
- 📧 Email: soman@devkraken.com 