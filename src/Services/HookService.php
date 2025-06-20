<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint\Services;

use RuntimeException;

class HookService
{
    private const string HOOK_MARKER = '# PHP CommitLint Hook';
    private const string HOOKS_DIR = '.git/hooks';

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
                if ($content !== false && str_contains($content, self::HOOK_MARKER)) {
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
                if ($content !== false && str_contains($content, self::HOOK_MARKER)) {
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
                $installed = $content !== false && str_contains($content, self::HOOK_MARKER);
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
        // Validate hook name to prevent path traversal
        if (!preg_match('/^[a-z-]+$/', $hookName)) {
            throw new \InvalidArgumentException('Invalid hook name. Only lowercase letters and hyphens allowed.');
        }

        // Validate command to prevent command injection
        if (strlen($command) > 1000) {
            throw new \InvalidArgumentException('Command too long. Maximum 1000 characters allowed.');
        }

        $sanitizedCommand = !empty($command) ? $command : null; // Ensure $sanitizedCommand is a string

        // Sanitize command for security by removing dangerous patterns entirely
        $sanitizedCommand = preg_replace('/rm\s+-rf\s*\/[^"\';\s]*/', '', $sanitizedCommand ?? '');
        $sanitizedCommand = preg_replace('/rm\s+-rf\s*\//', '', $sanitizedCommand ?? '');

        // Clean up any resulting extra spaces or semicolons
        $sanitizedCommand = preg_replace('/\s+/', ' ', $sanitizedCommand ?? '');
        $sanitizedCommand = preg_replace('/;\s*;/', ';', $sanitizedCommand ?? '');
        $sanitizedCommand = trim($sanitizedCommand ?? '', '; ');

        // Escape the sanitized command for shell safety
        $escapedCommand = escapeshellarg($sanitizedCommand);

        $hookPath = self::HOOKS_DIR . '/' . $hookName;

        if (file_exists($hookPath)) {
            // Read existing hook
            $content = file_get_contents($hookPath);
            if ($content === false) {
                throw new RuntimeException("Failed to read existing hook: {$hookPath}");
            }

            if (!str_contains($content, self::HOOK_MARKER)) {
                // Not our hook, create backup
                $backupPath = $hookPath . '.backup.' . time();
                if (!rename($hookPath, $backupPath)) {
                    throw new RuntimeException("Failed to create backup: {$backupPath}");
                }
                $content = $this->createHookTemplate($hookName);
            }
        } else {
            $content = $this->createHookTemplate($hookName);
        }

        // Insert custom command before any exit statements
        $customCommand = sprintf("\n# Custom command - Added %s\n%s\n", date('Y-m-d H:i:s'), $escapedCommand);

        // Find the last exit statement and insert before it
        if (preg_match('/(\n.*exit\s+\d+\s*(?:#.*)?)\s*$/s', $content, $matches)) {
            // Insert before the exit statement
            $content = str_replace($matches[1], $customCommand . $matches[1], $content);
        } else {
            // No exit statement found, append to end
            $content .= $customCommand;
        }

        if (file_put_contents($hookPath, $content) === false) {
            throw new RuntimeException("Failed to write hook file: {$hookPath}");
        }

        if (!chmod($hookPath, 0o755)) {
            throw new RuntimeException("Failed to make hook executable: {$hookPath}");
        }
    }

    public function removeCustomHook(string $hookName): void
    {
        $hookPath = self::HOOKS_DIR . '/' . $hookName;

        if (file_exists($hookPath)) {
            $content = file_get_contents($hookPath);
            if ($content !== false && str_contains($content, self::HOOK_MARKER)) {
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
        if (!is_dir(self::HOOKS_DIR) && !mkdir($concurrentDirectory = self::HOOKS_DIR, 0o755, true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException('Failed to create hooks directory: ' . self::HOOKS_DIR);
        }

        if (!is_writable(self::HOOKS_DIR)) {
            throw new RuntimeException('Hooks directory is not writable: ' . self::HOOKS_DIR);
        }
    }

    private function installCommitMsgHook(): void
    {
        $hookPath = self::HOOKS_DIR . '/commit-msg';
        $content = $this->createCommitMsgHookContent();

        if (file_put_contents($hookPath, $content) === false) {
            throw new RuntimeException("Failed to create commit-msg hook: {$hookPath}");
        }

        if (!chmod($hookPath, 0o755)) {
            throw new RuntimeException("Failed to make commit-msg hook executable: {$hookPath}");
        }
    }

    private function installPreCommitHook(): void
    {
        $hookPath = self::HOOKS_DIR . '/pre-commit';
        $content = $this->createPreCommitHookContent();

        if (file_put_contents($hookPath, $content) === false) {
            throw new RuntimeException("Failed to create pre-commit hook: {$hookPath}");
        }

        if (!chmod($hookPath, 0o755)) {
            throw new RuntimeException("Failed to make pre-commit hook executable: {$hookPath}");
        }
    }

    private function createCommitMsgHookContent(): string
    {
        $phpBinary = $this->normalizePath($this->findPhpBinary());
        $commitlintBinary = $this->normalizePath($this->findCommitlintBinary());
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
            "{$phpBinary}" "{$commitlintBinary}" validate --file="\$1"

            # Exit with the same code as the validation
            exit \$?
            HOOK;
    }

    private function createPreCommitHookContent(): string
    {
        $marker = self::HOOK_MARKER;

        // Load configuration to get pre-commit commands
        $configService = new ConfigService();
        $config = $configService->loadConfig();
        $preCommitCommands = $config['pre_commit_commands'] ?? [];

        $commandsSection = '';
        if (!empty($preCommitCommands)) {
            $commandsSection = "\n# Configured pre-commit commands\n";
            foreach ($preCommitCommands as $description => $command) {
                $escapedCommand = is_string($command) ? $command : '';
                $commandsSection .= "echo \"🔍 {$description}...\"\n";
                $commandsSection .= "{$escapedCommand}\n";
                $commandsSection .= "if [ \$? -ne 0 ]; then\n";
                $commandsSection .= "    echo \"❌ {$description} failed!\"\n";
                $commandsSection .= "    exit 1\n";
                $commandsSection .= "fi\n\n";
            }
        }

        return <<<HOOK
            #!/bin/sh
            # {$marker}
            #
            # Git pre-commit hook for PHP CommitLint
            # This hook runs configured pre-commit checks
            #
            {$commandsSection}
            # All checks passed
            echo "✅ All pre-commit checks passed!"
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
        // First try environment variable
        $phpFromEnv = $_SERVER['PHP_BINARY'] ?? null;
        if ($phpFromEnv && is_executable($phpFromEnv)) {
            return $phpFromEnv;
        }

        // Platform-specific candidates - prefer system-wide installations
        $isWindows = PHP_OS_FAMILY === 'Windows';

        if ($isWindows) {
            // Windows-specific candidates - prefer simple names that work in PATH
            $candidates = [
                'php.exe',                           // Most portable - works if PHP is in PATH
                'php',                              // Fallback without extension
                'C:\php\php.exe',
                'C:\xampp\php\php.exe',
                'C:\wamp\bin\php\php8.3\php.exe',
                'C:\wamp\bin\php\php8.2\php.exe',
                'C:\wamp\bin\php\php8.1\php.exe',
                'C:\laragon\bin\php\php8.3\php.exe',
                'C:\laragon\bin\php\php8.2\php.exe',
                'C:\laragon\bin\php\php8.1\php.exe',
            ];
        } else {
            // Unix-like systems - prefer simple name that works in PATH
            $candidates = [
                'php',                              // Most portable - works if PHP is in PATH
                '/usr/bin/php',
                '/usr/local/bin/php',
                '/opt/homebrew/bin/php',            // For macOS with Homebrew
                '/usr/bin/php8.3',
                '/usr/bin/php8.2',
                '/usr/bin/php8.1',
            ];
        }

        foreach ($candidates as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        // Try using which/where command based on platform
        if ($isWindows) {
            $whichResult = @exec('where php.exe 2>nul', $output, $exitCode);
            if ($exitCode === 0 && !empty($whichResult) && is_executable($whichResult)) {
                return $whichResult;
            }
            $whichResult = @exec('where php 2>nul', $output, $exitCode);
            if ($exitCode === 0 && !empty($whichResult) && is_executable($whichResult)) {
                return $whichResult;
            }
        } else {
            $whichResult = @exec('which php 2>/dev/null', $output, $exitCode);
            if ($exitCode === 0 && !empty($whichResult) && is_executable($whichResult)) {
                return $whichResult;
            }
        }

        // Platform-specific fallbacks
        return $isWindows ? 'php.exe' : 'php';
    }

    private function normalizePath(string $path): string
    {
        // Convert backslashes to forward slashes for consistent shell script paths
        $normalized = str_replace('\\', '/', $path);

        // Handle Windows drive letters (C: -> /c)
        if (PHP_OS_FAMILY === 'Windows' && preg_match('/^([A-Za-z]):/', $normalized, $matches)) {
            $driveLetter = strtolower($matches[1]);
            $normalized = '/' . $driveLetter . substr($normalized, 2);
        }

        return $normalized;
    }

    private function findCommitlintBinary(): string
    {
        $cwd = getcwd();
        $isWindows = PHP_OS_FAMILY === 'Windows';

        // Build binary name with platform-specific extension
        $binaryName = $isWindows ? 'php-commitlint.bat' : 'php-commitlint';

        // Prefer relative paths for better portability
        $candidates = [
            './bin/' . $binaryName,                // Development mode (relative)
            'vendor/bin/' . $binaryName,           // Dependency mode (relative)
            $cwd . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . $binaryName,          // Development mode (absolute fallback)
            $cwd . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . $binaryName,   // Dependency mode (absolute fallback)
        ];

        // Also try without extension on Windows (some installations may not have .bat)
        if ($isWindows) {
            $candidates[] = './bin/php-commitlint';
            $candidates[] = 'vendor/bin/php-commitlint';
            $candidates[] = $cwd . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'php-commitlint';
            $candidates[] = $cwd . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'php-commitlint';
        }

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                // On Windows, check if it's executable or if it's a .bat file
                if ($isWindows) {
                    if (is_readable($candidate) && (is_executable($candidate) || pathinfo($candidate, PATHINFO_EXTENSION) === 'bat')) {
                        // For relative paths, return as-is for better portability
                        if (str_starts_with($candidate, './') || str_starts_with($candidate, 'vendor/')) {
                            return $candidate;
                        }
                        // For absolute paths, normalize
                        $absolutePath = realpath($candidate) ?: $candidate;

                        return $absolutePath;
                    }
                } else {
                    if (is_executable($candidate)) {
                        // For relative paths, return as-is for better portability
                        if (str_starts_with($candidate, './') || str_starts_with($candidate, 'vendor/')) {
                            return $candidate;
                        }

                        // For absolute paths, normalize
                        return realpath($candidate) ?: $candidate;
                    }
                }
            }
        }

        // Return relative path as fallback for better portability
        return './bin/' . $binaryName;
    }
}
