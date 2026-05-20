<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint\Services;

use DevKraken\PhpCommitlint\Contracts\HookServiceInterface;
use InvalidArgumentException;
use RuntimeException;

final class HookService implements HookServiceInterface
{
    private const string HOOK_MARKER = '# PHP CommitLint Hook';
    private const string HOOKS_DIR = '.git/hooks';
    private const string GIT_DIR = '.git';
    private const int FILE_MODE = 0o755;
    private const int MAX_COMMAND_LENGTH = 1000;
    private const string HOOK_NAME_PATTERN = '/^[a-z-]+$/';

    /** @var list<string> */
    private const array MANAGED_HOOKS = ['commit-msg', 'pre-commit', 'pre-push'];

    public function isGitRepository(): bool
    {
        return is_dir(self::GIT_DIR) || is_file(self::GIT_DIR);
    }

    public function hasExistingHooks(): bool
    {
        foreach (self::MANAGED_HOOKS as $hook) {
            if (file_exists($this->hookPath($hook))) {
                return true;
            }
        }

        return false;
    }

    public function hasInstalledHooks(): bool
    {
        foreach (self::MANAGED_HOOKS as $hook) {
            if ($this->isOwnedHook($this->hookPath($hook))) {
                return true;
            }
        }

        return false;
    }

    public function installHooks(): void
    {
        $this->ensureHooksDirectory();
        $this->writeHookFile($this->hookPath('commit-msg'), $this->createCommitMsgHookContent());
        $this->writeHookFile($this->hookPath('pre-commit'), $this->createPreCommitHookContent());
    }

    public function uninstallHooks(): void
    {
        foreach (self::MANAGED_HOOKS as $hook) {
            $path = $this->hookPath($hook);
            if ($this->isOwnedHook($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * @return array<string, array{installed: bool, path: string}>
     */
    public function getInstalledHooks(): array
    {
        $hooks = [];
        foreach (self::MANAGED_HOOKS as $hook) {
            $path = $this->hookPath($hook);
            $hooks[$hook] = [
                'installed' => $this->isOwnedHook($path),
                'path' => $path,
            ];
        }

        return $hooks;
    }

    public function addCustomHook(string $hookName, string $command): void
    {
        $this->assertValidHookName($hookName);
        $this->assertValidCommand($command);

        $hookPath = $this->hookPath($hookName);
        $content = $this->prepareHookForCustomCommand($hookPath, $hookName);
        $escapedCommand = escapeshellarg($this->sanitizeCommand($command));
        $marker = sprintf("\n# Custom command - Added %s\n%s\n", date('Y-m-d H:i:s'), $escapedCommand);

        $content = $this->insertBeforeExit($content, $marker);
        $this->writeHookFile($hookPath, $content);
    }

    public function removeCustomHook(string $hookName): void
    {
        $hookPath = $this->hookPath($hookName);
        if (!$this->isOwnedHook($hookPath)) {
            return;
        }

        $backupPath = $hookPath . '.backup';
        if (file_exists($backupPath)) {
            rename($backupPath, $hookPath);

            return;
        }

        @unlink($hookPath);
    }

    private function hookPath(string $hookName): string
    {
        return self::HOOKS_DIR . '/' . $hookName;
    }

    private function isOwnedHook(string $path): bool
    {
        if (!file_exists($path)) {
            return false;
        }

        $content = @file_get_contents($path);

        return $content !== false && str_contains($content, self::HOOK_MARKER);
    }

    private function ensureHooksDirectory(): void
    {
        if (!is_dir(self::HOOKS_DIR) && !mkdir(self::HOOKS_DIR, self::FILE_MODE, true) && !is_dir(self::HOOKS_DIR)) {
            throw new RuntimeException('Failed to create hooks directory: ' . self::HOOKS_DIR);
        }

        if (!is_writable(self::HOOKS_DIR)) {
            throw new RuntimeException('Hooks directory is not writable: ' . self::HOOKS_DIR);
        }
    }

    private function writeHookFile(string $path, string $content): void
    {
        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException("Failed to write hook file: {$path}");
        }

        if (!chmod($path, self::FILE_MODE)) {
            throw new RuntimeException("Failed to make hook executable: {$path}");
        }
    }

    private function assertValidHookName(string $hookName): void
    {
        if (!preg_match(self::HOOK_NAME_PATTERN, $hookName)) {
            throw new InvalidArgumentException('Invalid hook name. Only lowercase letters and hyphens allowed.');
        }
    }

    private function assertValidCommand(string $command): void
    {
        if (strlen($command) > self::MAX_COMMAND_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'Command too long. Maximum %d characters allowed.',
                self::MAX_COMMAND_LENGTH
            ));
        }
    }

    private function prepareHookForCustomCommand(string $hookPath, string $hookName): string
    {
        if (!file_exists($hookPath)) {
            return $this->createHookTemplate($hookName);
        }

        $content = file_get_contents($hookPath);
        if ($content === false) {
            throw new RuntimeException("Failed to read existing hook: {$hookPath}");
        }

        if (str_contains($content, self::HOOK_MARKER)) {
            return $content;
        }

        $backupPath = $hookPath . '.backup.' . time();
        if (!rename($hookPath, $backupPath)) {
            throw new RuntimeException("Failed to create backup: {$backupPath}");
        }

        return $this->createHookTemplate($hookName);
    }

    /**
     * Strip known-dangerous patterns; final defence is escapeshellarg in the caller.
     */
    private function sanitizeCommand(string $command): string
    {
        $cleaned = preg_replace(
            ['#rm\s+-rf\s*/[^"\';\s]*#', '#rm\s+-rf\s*/#'],
            '',
            $command
        ) ?? '';

        $cleaned = preg_replace('/\s+/', ' ', $cleaned) ?? '';
        $cleaned = preg_replace('/;\s*;/', ';', $cleaned) ?? '';

        return trim($cleaned, '; ');
    }

    private function insertBeforeExit(string $content, string $insertion): string
    {
        if (preg_match('/(\n.*exit\s+\d+\s*(?:#.*)?)\s*$/s', $content, $matches)) {
            return str_replace($matches[1], $insertion . $matches[1], $content);
        }

        return $content . $insertion;
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

            if [ ! -f "{$commitlintBinary}" ]; then
                echo "⚠️  PHP CommitLint not found. Skipping validation."
                exit 0
            fi

            if [ -f ".git/MERGE_HEAD" ] || [ -f ".git/REBASE_HEAD" ] || [ -f ".git/CHERRY_PICK_HEAD" ]; then
                echo "🔄 In rebase/merge/cherry-pick mode. Skipping validation."
                exit 0
            fi

            "{$phpBinary}" "{$commitlintBinary}" validate --file="\$1"
            exit \$?
            HOOK;
    }

    private function createPreCommitHookContent(): string
    {
        $marker = self::HOOK_MARKER;
        $phpBinary = $this->findPhpBinary();

        return <<<HOOK
            #!/bin/sh
            # {$marker}
            #
            # Git pre-commit hook for PHP CommitLint
            # Reads pre_commit_commands from .commitlintrc.json and runs each in order.
            #

            run_pre_commit_commands() {
                "{$phpBinary}" -r '
                \$configFile = ".commitlintrc.json";
                if (!file_exists(\$configFile)) {
                    echo "⚠️  No .commitlintrc.json found, skipping pre-commit commands" . PHP_EOL;
                    exit(0);
                }

                \$config = json_decode(file_get_contents(\$configFile), true);
                if (!is_array(\$config)) {
                    echo "⚠️  Invalid .commitlintrc.json format, skipping pre-commit commands" . PHP_EOL;
                    exit(0);
                }

                \$commands = \$config["pre_commit_commands"] ?? [];
                if (empty(\$commands)) {
                    echo "✅ No pre-commit commands configured" . PHP_EOL;
                    exit(0);
                }

                if (is_object(\$commands)) {
                    \$commands = (array) \$commands;
                }

                foreach (\$commands as \$description => \$command) {
                    if (!is_string(\$command)) continue;

                    echo "🔍 " . \$description . "..." . PHP_EOL;
                    \$exitCode = 0;
                    system(\$command, \$exitCode);

                    if (\$exitCode !== 0) {
                        echo "❌ " . \$description . " failed!" . PHP_EOL;
                        exit(1);
                    }
                }

                echo "✅ All pre-commit checks passed!" . PHP_EOL;
                '
            }

            run_pre_commit_commands

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
        $fromEnv = $_SERVER['PHP_BINARY'] ?? null;
        if (is_string($fromEnv) && $fromEnv !== '' && is_executable($fromEnv)) {
            return $fromEnv;
        }

        foreach ($this->phpBinaryCandidates() as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        $resolved = $this->resolveBinaryFromShell();
        if ($resolved !== null) {
            return $resolved;
        }

        return PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php';
    }

    /**
     * @return list<string>
     */
    private function phpBinaryCandidates(): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return [
                'php.exe',
                'php',
                'C:\php\php.exe',
                'C:\xampp\php\php.exe',
                'C:\wamp\bin\php\php8.3\php.exe',
                'C:\wamp\bin\php\php8.2\php.exe',
                'C:\wamp\bin\php\php8.1\php.exe',
                'C:\laragon\bin\php\php8.3\php.exe',
                'C:\laragon\bin\php\php8.2\php.exe',
                'C:\laragon\bin\php\php8.1\php.exe',
            ];
        }

        return [
            'php',
            '/usr/bin/php',
            '/usr/local/bin/php',
            '/opt/homebrew/bin/php',
            '/usr/bin/php8.3',
            '/usr/bin/php8.2',
            '/usr/bin/php8.1',
        ];
    }

    private function resolveBinaryFromShell(): ?string
    {
        $commands = PHP_OS_FAMILY === 'Windows'
            ? ['where php.exe 2>nul', 'where php 2>nul']
            : ['which php 2>/dev/null'];

        foreach ($commands as $cmd) {
            $exitCode = 0;
            $output = [];
            $result = @exec($cmd, $output, $exitCode);
            if ($exitCode === 0 && is_string($result) && $result !== '' && is_executable($result)) {
                return $result;
            }
        }

        return null;
    }

    private function findCommitlintBinary(): string
    {
        $cwd = getcwd() ?: '.';
        $isWindows = PHP_OS_FAMILY === 'Windows';
        $binaryName = $isWindows ? 'php-commitlint.bat' : 'php-commitlint';

        $candidates = [
            './bin/' . $binaryName,
            'vendor/bin/' . $binaryName,
            $cwd . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . $binaryName,
            $cwd . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . $binaryName,
        ];

        if ($isWindows) {
            $candidates[] = './bin/php-commitlint';
            $candidates[] = 'vendor/bin/php-commitlint';
            $candidates[] = $cwd . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'php-commitlint';
            $candidates[] = $cwd . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'php-commitlint';
        }

        foreach ($candidates as $candidate) {
            $resolved = $this->resolveCommitlintCandidate($candidate, $isWindows);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return './bin/' . $binaryName;
    }

    private function resolveCommitlintCandidate(string $candidate, bool $isWindows): ?string
    {
        if (!file_exists($candidate)) {
            return null;
        }

        if ($isWindows) {
            $isUsable = is_readable($candidate)
                && (is_executable($candidate) || pathinfo($candidate, PATHINFO_EXTENSION) === 'bat');
        } else {
            $isUsable = is_executable($candidate);
        }

        if (!$isUsable) {
            return null;
        }

        if (str_starts_with($candidate, './') || str_starts_with($candidate, 'vendor/')) {
            return $candidate;
        }

        return realpath($candidate) ?: $candidate;
    }

    private function normalizePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);

        if (PHP_OS_FAMILY === 'Windows' && preg_match('/^([A-Za-z]):/', $normalized, $matches)) {
            $driveLetter = strtolower($matches[1]);
            $normalized = '/' . $driveLetter . substr($normalized, 2);
        }

        return $normalized;
    }
}
