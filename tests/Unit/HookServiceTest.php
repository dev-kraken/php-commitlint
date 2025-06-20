<?php

declare(strict_types=1);

use DevKraken\PhpCommitlint\Services\HookService;

beforeEach(function () {
    $this->tempDir = createTempGitRepo();
    $this->originalCwd = getcwd();
    chdir($this->tempDir);
    $this->hookService = new HookService();
});

afterEach(function () {
    chdir($this->originalCwd);
    cleanupTempPath($this->tempDir);
});

describe('HookService', function () {
    describe('Git Repository Detection', function () {
        it('detects git repository correctly', function () {
            expect($this->hookService->isGitRepository())->toBeTrue();
        });

        it('returns false for non-git directory', function () {
            // Remove .git directory
            cleanupTempPath($this->tempDir . '/.git');

            expect($this->hookService->isGitRepository())->toBeFalse();
        });

        it('detects git worktree', function () {
            // Simulate git worktree (where .git is a file, not directory)
            rmdir($this->tempDir . '/.git/hooks');
            rmdir($this->tempDir . '/.git');
            file_put_contents($this->tempDir . '/.git', 'gitdir: /some/path/.git/worktrees/test');

            expect($this->hookService->isGitRepository())->toBeTrue();
        });
    });

    describe('Hook Detection', function () {
        it('detects no existing hooks in new repo', function () {
            expect($this->hookService->hasExistingHooks())->toBeFalse();
        });

        it('detects existing hooks', function () {
            file_put_contents($this->tempDir . '/.git/hooks/pre-commit', '#!/bin/sh\necho "test"');

            expect($this->hookService->hasExistingHooks())->toBeTrue();
        });

        it('detects installed commitlint hooks', function () {
            $this->hookService->installHooks();

            expect($this->hookService->hasInstalledHooks())->toBeTrue();
        });

        it('returns false for non-commitlint hooks', function () {
            file_put_contents($this->tempDir . '/.git/hooks/pre-commit', '#!/bin/sh\necho "not our hook"');

            expect($this->hookService->hasInstalledHooks())->toBeFalse();
        });
    });

    describe('Hook Installation', function () {
        it('installs hooks successfully', function () {
            $this->hookService->installHooks();

            expect(file_exists($this->tempDir . '/.git/hooks/commit-msg'))->toBeTrue()
                ->and(file_exists($this->tempDir . '/.git/hooks/pre-commit'))->toBeTrue();

            // On Windows, executable permission check works differently
            if (PHP_OS_FAMILY !== 'Windows') {
                expect(is_executable($this->tempDir . '/.git/hooks/commit-msg'))->toBeTrue();
            } else {
                // On Windows, just check the file has proper content
                expect(file_get_contents($this->tempDir . '/.git/hooks/commit-msg'))->toContain('#!/bin/sh');
            }
        });

        it('creates hooks directory if it does not exist', function () {
            rmdir($this->tempDir . '/.git/hooks');

            $this->hookService->installHooks();

            expect(is_dir($this->tempDir . '/.git/hooks'))->toBeTrue();
        });

        it('throws exception if hooks directory is not writable', function () {
            chmod($this->tempDir . '/.git/hooks', 0o444); // Read-only

            expect(fn () => $this->hookService->installHooks())
                ->toThrow(RuntimeException::class, 'Hooks directory is not writable');

            chmod($this->tempDir . '/.git/hooks', 0o755); // Restore permissions
        });

        it('generates hook with portable paths', function () {
            $this->hookService->installHooks();

            $hookContent = file_get_contents($this->tempDir . '/.git/hooks/commit-msg');

            // Get the actual PHP binary path that the service detects
            $reflection = new ReflectionClass($this->hookService);
            $method = $reflection->getMethod('findPhpBinary');
            $method->setAccessible(true);
            $expectedPhpPath = $method->invoke($this->hookService);
            assert(is_string($expectedPhpPath));

            // Normalize the expected path for cross-platform comparison (HookService normalizes paths)
            $normalizedPhpPath = str_replace('\\', '/', $expectedPhpPath);
            expect($hookContent)->toContain($normalizedPhpPath); // Actual PHP binary path (normalized)

            // Now we expect relative paths for better portability
            expect($hookContent)->toContain('./bin/php-commitlint'); // Relative path for development mode
        });
    });

    describe('Hook Uninstallation', function () {
        it('uninstalls only commitlint hooks', function () {
            // Install our hooks
            $this->hookService->installHooks();

            // Add a non-commitlint hook
            file_put_contents($this->tempDir . '/.git/hooks/pre-push', '#!/bin/sh\necho "other hook"');

            $this->hookService->uninstallHooks();

            expect(file_exists($this->tempDir . '/.git/hooks/commit-msg'))->toBeFalse();
            expect(file_exists($this->tempDir . '/.git/hooks/pre-commit'))->toBeFalse();
            expect(file_exists($this->tempDir . '/.git/hooks/pre-push'))->toBeTrue(); // Other hook preserved
        });
    });

    describe('Custom Hook Management', function () {
        beforeEach(function () {
            $this->hookService->installHooks();
        });

        it('adds custom command to existing hook', function () {
            $this->hookService->addCustomHook('pre-commit', 'vendor/bin/pest');

            $hookContent = file_get_contents($this->tempDir . '/.git/hooks/pre-commit');

            expect($hookContent)->toContain('vendor/bin/pest');
            expect($hookContent)->toContain('# Custom command');
        });

        it('validates hook name to prevent path traversal', function () {
            expect(fn () => $this->hookService->addCustomHook('../../../etc/passwd', 'evil command'))
                ->toThrow(InvalidArgumentException::class, 'Invalid hook name');
        });

        it('validates command length', function () {
            $longCommand = str_repeat('a', 1001);

            expect(fn () => $this->hookService->addCustomHook('pre-commit', $longCommand))
                ->toThrow(InvalidArgumentException::class, 'Command too long');
        });

        it('escapes shell commands for security', function () {
            $this->hookService->addCustomHook('pre-commit', 'echo "test; rm -rf /"');

            $hookContent = file_get_contents($this->tempDir . '/.git/hooks/pre-commit');

            // Command should be escaped
            expect($hookContent)->not->toContain('rm -rf /')
                ->and($hookContent)->toContain('echo');
        });

        it('inserts commands before exit statement', function () {
            $this->hookService->addCustomHook('pre-commit', 'vendor/bin/pest');

            $hookContent = file_get_contents($this->tempDir . '/.git/hooks/pre-commit');
            assert($hookContent !== false);
            $lines = explode("\n", $hookContent);

            $pestLineIndex = null;
            $exitLineIndex = null;

            foreach ($lines as $index => $line) {
                if (str_contains($line, 'vendor/bin/pest')) {
                    $pestLineIndex = $index;
                }
                if (str_contains($line, 'exit 0')) {
                    $exitLineIndex = $index;
                }
            }

            expect($pestLineIndex)->not->toBeNull()
                ->and($exitLineIndex)->not->toBeNull();
            assert($pestLineIndex !== null);
            assert($exitLineIndex !== null);
            expect($pestLineIndex)->toBeLessThan($exitLineIndex);
        });

        it('creates backup of existing non-commitlint hooks', function () {
            // Create a custom hook first
            file_put_contents($this->tempDir . '/.git/hooks/pre-push', '#!/bin/sh\necho "custom"');

            $this->hookService->addCustomHook('pre-push', 'vendor/bin/pest');

            // Should create a backup
            $backupFiles = glob($this->tempDir . '/.git/hooks/pre-push.backup.*');
            expect($backupFiles)->not->toBeEmpty();
        });

        it('removes custom hooks correctly', function () {
            $this->hookService->addCustomHook('pre-commit', 'vendor/bin/pest');

            expect(file_exists($this->tempDir . '/.git/hooks/pre-commit'))->toBeTrue();

            $this->hookService->removeCustomHook('pre-commit');

            expect(file_exists($this->tempDir . '/.git/hooks/pre-commit'))->toBeFalse();
        });
    });

    describe('Hook Information', function () {
        it('returns correct hook status information', function () {
            $this->hookService->installHooks();

            $hooks = $this->hookService->getInstalledHooks();

            expect($hooks)->toHaveKey('commit-msg')
                ->and($hooks)->toHaveKey('pre-commit')
                ->and($hooks['commit-msg']['installed'])->toBeTrue()
                ->and($hooks['pre-commit']['installed'])->toBeTrue();
        });

        it('returns correct paths for hooks', function () {
            $hooks = $this->hookService->getInstalledHooks();

            expect($hooks['commit-msg']['path'])->toContain('.git/hooks/commit-msg');
        });
    });
});

describe('Binary Detection', function () {
    it('finds PHP binary correctly', function () {
        $hookService = new HookService();
        $reflection = new ReflectionClass($hookService);
        $method = $reflection->getMethod('findPhpBinary');
        $method->setAccessible(true);

        $phpBinary = $method->invoke($hookService);

        expect($phpBinary)->not->toBeEmpty()
            ->and($phpBinary)->toContain('php');
    });

    it('finds commitlint binary correctly', function () {
        $hookService = new HookService();
        $reflection = new ReflectionClass($hookService);
        $method = $reflection->getMethod('findCommitlintBinary');
        $method->setAccessible(true);

        $commitlintBinary = $method->invoke($hookService);

        expect($commitlintBinary)->not->toBeEmpty()
            ->and($commitlintBinary)->toContain('php-commitlint');
    });
});
