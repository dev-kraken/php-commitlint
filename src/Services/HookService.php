<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint\Services;

class HookService
{
    private const HOOK_MARKER = '# PHP CommitLint Hook';
    private const HOOKS_DIR = '.git/hooks';

    public function isGitRepository(): bool
    {
        return is_dir('.git') || (file_exists('.git') && is_file('.git'));
    }

    public function hasExistingHooks(): bool
    {
        $hookFiles = ['commit-msg', 'pre-commit', 'pre-push'];

        foreach ($hookFiles as $hook) {
            $hookPath = self::HOOKS_DIR . '/' . $hook;
            if (file_exists($hookPath)) {
                return true;
            }
        }

        return false;
    }

    public function hasInstalledHooks(): bool
    {
        $hookFiles = ['commit-msg', 'pre-commit', 'pre-push'];

        foreach ($hookFiles as $hook) {
            $hookPath = self::HOOKS_DIR . '/' . $hook;
            if (file_exists($hookPath)) {
                $content = file_get_contents($hookPath);
                if ($content !== false && strpos($content, self::HOOK_MARKER) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function installHooks(): void
    {
        $this->ensureHooksDirectory();

        // Install commit-msg hook (main functionality)
        $this->installCommitMsgHook();

        // Install pre-commit hook (optional, for additional checks)
        $this->installPreCommitHook();
    }

    public function uninstallHooks(): void
    {
        $hookFiles = ['commit-msg', 'pre-commit', 'pre-push'];

        foreach ($hookFiles as $hook) {
            $hookPath = self::HOOKS_DIR . '/' . $hook;
            if (file_exists($hookPath)) {
                $content = file_get_contents($hookPath);
                if ($content !== false && strpos($content, self::HOOK_MARKER) !== false) {
                    unlink($hookPath);
                }
            }
        }
    }

    public function getInstalledHooks(): array
    {
        $hooks = [];
        $hookFiles = ['commit-msg', 'pre-commit', 'pre-push'];

        foreach ($hookFiles as $hook) {
            $hookPath = self::HOOKS_DIR . '/' . $hook;
            $installed = false;

            if (file_exists($hookPath)) {
                $content = file_get_contents($hookPath);
                $installed = $content !== false && strpos($content, self::HOOK_MARKER) !== false;
            }

            $hooks[$hook] = [
                'installed' => $installed,
                'path' => $hookPath,
            ];
        }

        return $hooks;
    }

    public function addCustomHook(string $hookName, string $command): void
    {
        $hookPath = self::HOOKS_DIR . '/' . $hookName;

        if (file_exists($hookPath)) {
            // Append to existing hook
            $content = file_get_contents($hookPath);
            if ($content === false || strpos($content, self::HOOK_MARKER) === false) {
                // Not our hook or failed to read, create backup
                if ($content !== false) {
                    rename($hookPath, $hookPath . '.backup');
                }
                $content = $this->createHookTemplate($hookName);
            }
        } else {
            $content = $this->createHookTemplate($hookName);
        }

        // Add custom command
        $content .= "\n# Custom command\n";
        $content .= $command . "\n";

        file_put_contents($hookPath, $content);
        chmod($hookPath, 0o755);
    }

    public function removeCustomHook(string $hookName): void
    {
        $hookPath = self::HOOKS_DIR . '/' . $hookName;

        if (file_exists($hookPath)) {
            $content = file_get_contents($hookPath);
            if ($content !== false && strpos($content, self::HOOK_MARKER) !== false) {
                // Check if backup exists
                $backupPath = $hookPath . '.backup';
                if (file_exists($backupPath)) {
                    // Restore backup
                    rename($backupPath, $hookPath);
                } else {
                    // Remove our hook
                    unlink($hookPath);
                }
            }
        }
    }

    private function ensureHooksDirectory(): void
    {
        if (!is_dir(self::HOOKS_DIR)) {
            mkdir(self::HOOKS_DIR, 0o755, true);
        }
    }

    private function installCommitMsgHook(): void
    {
        $hookPath = self::HOOKS_DIR . '/commit-msg';
        $content = $this->createCommitMsgHookContent();

        file_put_contents($hookPath, $content);
        chmod($hookPath, 0o755);
    }

    private function installPreCommitHook(): void
    {
        $hookPath = self::HOOKS_DIR . '/pre-commit';
        $content = $this->createPreCommitHookContent();

        file_put_contents($hookPath, $content);
        chmod($hookPath, 0o755);
    }

    private function createCommitMsgHookContent(): string
    {
        $phpBinary = $this->findPhpBinary();
        $commitlintBinary = $this->findCommitlintBinary();
        $marker = self::HOOK_MARKER;

        return <<<HOOK
            #!/bin/sh
            # {$marker}
            #
            # Git commit-msg hook for PHP CommitLint
            # This hook validates commit messages according to configured rules
            #

            # Check if PHP CommitLint is available
            if [ ! -f "{$commitlintBinary}" ]; then
                echo "⚠️  PHP CommitLint not found. Skipping validation."
                exit 0
            fi

            # Check if we're in a rebase/merge/cherry-pick
            if [ -f ".git/MERGE_HEAD" ] || [ -f ".git/REBASE_HEAD" ] || [ -f ".git/CHERRY_PICK_HEAD" ]; then
                echo "🔄 In rebase/merge/cherry-pick mode. Skipping validation."
                exit 0
            fi

            # Validate commit message
            {$phpBinary} {$commitlintBinary} validate --file="\$1" --quiet

            # Exit with the same code as the validation
            exit \$?
            HOOK;
    }

    private function createPreCommitHookContent(): string
    {
        $marker = self::HOOK_MARKER;

        return <<<HOOK
            #!/bin/sh
            # {$marker}
            #
            # Git pre-commit hook for PHP CommitLint
            # Add your custom pre-commit checks here
            #

            # Example: Run PHP CS Fixer
            # vendor/bin/php-cs-fixer fix --dry-run --diff

            # Example: Run PHPStan
            # vendor/bin/phpstan analyse

            # Example: Run Pest tests
            # vendor/bin/pest

            exit 0
            HOOK;
    }

    private function createHookTemplate(string $hookName): string
    {
        $marker = self::HOOK_MARKER;

        return <<<HOOK
            #!/bin/sh
            # {$marker}
            #
            # Git {$hookName} hook for PHP CommitLint
            #

            HOOK;
    }

    private function findPhpBinary(): string
    {
        $candidates = [
            trim(`which php 2>/dev/null`),
            '/usr/bin/php',
            '/usr/local/bin/php',
            'php',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate && is_executable($candidate)) {
                return $candidate;
            }
        }

        return 'php'; // Fallback
    }

    private function findCommitlintBinary(): string
    {
        $candidates = [
            './bin/php-commitlint',        // Development mode
            'vendor/bin/php-commitlint',   // When installed as dependency
            './vendor/bin/php-commitlint', // Alternative vendor path
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return './bin/php-commitlint'; // Fallback for development
    }
}
