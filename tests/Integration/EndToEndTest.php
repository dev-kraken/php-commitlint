<?php

declare(strict_types=1);

require_once __DIR__ . '/../Pest.php';

use DevKraken\PhpCommitlint\Application;
use Symfony\Component\Console\Tester\ApplicationTester;

beforeEach(function () {
    $this->tempDir = createTempGitRepo();
    $this->originalCwd = getcwd();
    chdir($this->tempDir);

    $this->application = new Application();
    $this->applicationTester = new ApplicationTester($this->application);
});

afterEach(function () {
    chdir($this->originalCwd);
    cleanupTempPath($this->tempDir);
});

/**
 * Helper method to run shell commands and capture output
 */
function runShellCommand(string $command, bool $expectFailure = false): string
{
    $output = [];
    $returnCode = 0;
    exec($command . ' 2>&1', $output, $returnCode);

    if (!$expectFailure && $returnCode !== 0) {
        throw new Exception("Command failed: $command\nOutput: " . implode("\n", $output));
    }

    return implode("\n", $output);
}

describe('End-to-End Integration Tests', function () {
    describe('Complete Workflow', function () {
        it('installs hooks, adds custom commands, and validates commits', function () {
            // 1. Install hooks (this will create default config if needed)
            $this->applicationTester->run(['command' => 'install']);
            expect(file_exists('.git/hooks/commit-msg'))->toBeTrue();

            // 2. Add custom pre-commit command
            $this->applicationTester->run([
                'command' => 'add',
                'hook' => 'pre-commit',
                'hook-command' => 'echo "Running tests..."',
                '--force' => true,
            ]);

            expect(file_exists('.git/hooks/pre-commit'))->toBeTrue();
            $hookContent = file_get_contents('.git/hooks/pre-commit');
            expect($hookContent)->toContain('echo "Running tests..."');

            // 3. Validate good commit message
            $this->applicationTester->run([
                'command' => 'validate',
                'message' => 'feat: add new user authentication system',
            ]);
            expect($this->applicationTester->getStatusCode())->toBe(0);

            // 4. Validate bad commit message
            $this->applicationTester->run([
                'command' => 'validate',
                'message' => 'bad commit message',
            ]);
            expect($this->applicationTester->getStatusCode())->toBe(1);

            // 5. List installed hooks
            $this->applicationTester->run(['command' => 'list']);
            expect($this->applicationTester->getDisplay())->toContain('commit-msg');

            // 6. Uninstall hooks
            $this->applicationTester->run(['command' => 'uninstall']);
            expect(file_exists('.git/hooks/commit-msg'))->toBeFalse();
            expect(file_exists('.git/hooks/pre-commit'))->toBeFalse();
        });

        it('handles custom configuration throughout workflow', function () {
            // Create custom config
            $customConfig = [
                'auto_install' => false,
                'rules' => [
                    'type' => [
                        'required' => true,
                        'allowed' => ['feature', 'bugfix', 'hotfix', 'docs'],
                    ],
                    'scope' => [
                        'required' => true,
                        'allowed' => ['api', 'ui', 'db', 'auth'],
                    ],
                    'subject' => [
                        'min_length' => 5,
                        'max_length' => 50,
                        'case' => 'lowercase',
                        'end_with_period' => false,
                    ],
                ],
            ];

            file_put_contents('.commitlintrc.json', json_encode($customConfig, JSON_PRETTY_PRINT));

            // Install hooks
            $this->applicationTester->run(['command' => 'install']);

            // Test valid custom format
            $this->applicationTester->run([
                'command' => 'validate',
                'message' => 'feature(api): add user endpoint',
            ]);
            expect($this->applicationTester->getStatusCode())->toBe(0);

            // Test invalid type
            $this->applicationTester->run([
                'command' => 'validate',
                'message' => 'feat(api): add user endpoint',
            ]);
            expect($this->applicationTester->getStatusCode())->toBe(1);

            // Test missing scope
            $this->applicationTester->run([
                'command' => 'validate',
                'message' => 'feature: add user endpoint',
            ]);
            expect($this->applicationTester->getStatusCode())->toBe(1);

            // Test invalid scope
            $this->applicationTester->run([
                'command' => 'validate',
                'message' => 'feature(invalid): add user endpoint',
            ]);
            expect($this->applicationTester->getStatusCode())->toBe(1);
        });

        it('installs hooks with custom pre-commit commands from config', function () {
            // Create config with pre-commit commands
            $config = [
                'hooks' => [
                    'commit-msg' => true,
                    'pre-commit' => true,
                ],
                'pre_commit_commands' => [
                    'Test Check' => 'echo "Running tests..." && echo "Tests passed!"',
                    'Style Check' => 'echo "Checking style..." && echo "Style OK!"',
                ],
            ];

            file_put_contents($this->tempDir . '/.commitlintrc.json', json_encode($config, JSON_PRETTY_PRINT));

            // Install hooks
            $this->applicationTester->run(['command' => 'install', '--force']);

            // Check that pre-commit hook was created
            expect($this->tempDir . '/.git/hooks/pre-commit')->toBeFile();

            // Check that the hook contains our custom commands
            $hookContent = file_get_contents($this->tempDir . '/.git/hooks/pre-commit');
            expect($hookContent)
                ->toContain('# Configured pre-commit commands')
                ->toContain('echo "🔍 Test Check..."')
                ->toContain('echo "Running tests..." && echo "Tests passed!"')
                ->toContain('echo "🔍 Style Check..."')
                ->toContain('echo "Checking style..." && echo "Style OK!"');

            // Test that the pre-commit hook can be executed
            $result = runShellCommand('cd ' . $this->tempDir . ' && ./.git/hooks/pre-commit');
            expect($result)->toContain('Running tests...')
                ->toContain('Tests passed!')
                ->toContain('Checking style...')
                ->toContain('Style OK!');
        });

        it('handles pre-commit commands that fail', function () {
            // Create config with a failing pre-commit command
            $config = [
                'hooks' => [
                    'pre-commit' => true,
                ],
                'pre_commit_commands' => [
                    'Passing Check' => 'echo "This passes"',
                    'Failing Check' => 'echo "This fails" && exit 1',
                    'Skipped Check' => 'echo "This should not run"',
                ],
            ];

            file_put_contents($this->tempDir . '/.commitlintrc.json', json_encode($config, JSON_PRETTY_PRINT));

            // Install hooks
            $this->applicationTester->run(['command' => 'install', '--force']);

            // Test that the pre-commit hook fails when one command fails
            $result = runShellCommand('cd ' . $this->tempDir . ' && ./.git/hooks/pre-commit', expectFailure: true);
            expect($result)
                ->toContain('This passes')
                ->toContain('This fails');
            expect($result)->not()->toContain('This should not run'); // Should stop after failure
        });

        it('installs hooks without pre-commit commands when disabled', function () {
            // Create config with pre-commit disabled
            $config = [
                'hooks' => [
                    'commit-msg' => true,
                    'pre-commit' => false,
                ],
                'pre_commit_commands' => [
                    'Test Check' => 'echo "This should not be installed"',
                ],
            ];

            file_put_contents($this->tempDir . '/.commitlintrc.json', json_encode($config, JSON_PRETTY_PRINT));

            // Install hooks
            $this->applicationTester->run(['command' => 'install', '--force']);

            // Check that pre-commit hook was NOT created
            expect($this->tempDir . '/.git/hooks/pre-commit')->not()->toBeFile();

            // Check that commit-msg hook was created
            expect($this->tempDir . '/.git/hooks/commit-msg')->toBeFile();
        });
    });

    describe('Error Scenarios', function () {
        it('handles non-git repository gracefully', function () {
            // Remove .git directory
            cleanupTempPath($this->tempDir . '/.git');

            $this->applicationTester->run(['command' => 'install']);
            expect($this->applicationTester->getStatusCode())->toBe(1);
            expect($this->applicationTester->getDisplay())->toContain('Not a git repository');
        });

        it('handles existing non-commitlint hooks', function () {
            // Create existing hook
            file_put_contents('.git/hooks/pre-commit', '#!/bin/sh\necho "existing hook"');
            chmod('.git/hooks/pre-commit', 0o755);

            $this->applicationTester->run(['command' => 'install']);
            expect($this->applicationTester->getStatusCode())->toBe(0);

            // Should create backup and install our hook
            $backupFiles = glob('.git/hooks/pre-commit.backup.*');
            expect($backupFiles)->not()->toBeEmpty();

            $hookContent = file_get_contents('.git/hooks/pre-commit');
            expect($hookContent)->toContain('PHP CommitLint');
        });

        it('handles corrupted config file', function () {
            file_put_contents('.commitlintrc.json', '{invalid json}');

            $this->applicationTester->run([
                'command' => 'validate',
                'message' => 'feat: test',
            ]);

            expect($this->applicationTester->getStatusCode())->toBe(1);
            expect($this->applicationTester->getDisplay())->toContain('Invalid JSON');
        });
    });

    describe('Git Hook Integration', function () {
        it('validates commits through actual git hooks', function () {
            // Install hooks
            $this->applicationTester->run(['command' => 'install']);

            // Test the actual hook script
            $hookScript = '.git/hooks/commit-msg';
            expect(file_exists($hookScript))->toBeTrue();

            // Create a test commit message file
            $commitMsgFile = createTempFile('feat: add new feature');

            // Execute the hook directly
            $output = [];
            $exitCode = 0;
            exec("$hookScript $commitMsgFile 2>&1", $output, $exitCode);

            expect($exitCode)->toBe(0); // Should pass

            // Test with invalid message
            file_put_contents($commitMsgFile, 'invalid commit message');

            exec("$hookScript $commitMsgFile 2>&1", $output, $exitCode);
            expect($exitCode)->toBe(1); // Should fail

            unlink($commitMsgFile);
        });

        it('runs custom commands in hooks', function () {
            // Install hooks
            $this->applicationTester->run(['command' => 'install']);

            // Add a custom command that creates a test file
            $this->applicationTester->run([
                'command' => 'add',
                'hook' => 'pre-commit',
                'hook-command' => 'touch custom_test_file.txt',
                '--force' => true,
            ]);

            // Execute the hook
            $hookScript = '.git/hooks/pre-commit';
            $output = [];
            $exitCode = 0;
            exec("$hookScript 2>&1", $output, $exitCode);

            expect($exitCode)->toBe(0);
            expect(file_exists('custom_test_file.txt'))->toBeTrue();

            unlink('custom_test_file.txt');
        });
    });
});

describe('Performance Tests', function () {
    beforeEach(function () {
        $this->tempDir = createTempGitRepo();
        $this->originalCwd = getcwd();
        chdir($this->tempDir);

        $this->application = new Application();
        $this->applicationTester = new ApplicationTester($this->application);
    });

    afterEach(function () {
        chdir($this->originalCwd);
        cleanupTempPath($this->tempDir);
    });

    it('validates commit messages efficiently', function () {
        $startTime = microtime(true);

        // Validate 100 commit messages
        for ($i = 0; $i < 100; $i++) {
            $this->applicationTester->run([
                'command' => 'validate',
                'message' => "feat: add feature number $i",
            ]);
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // Should complete 100 validations in under 1 second
        expect($duration)->toBeLessThan(1.0);
    });

    it('handles large commit messages efficiently', function () {
        // Create a large but valid commit message
        $largeMessage = "feat: add comprehensive feature\n\n" .
            str_repeat("This is a detailed description line.\n", 50) .
            "\nCloses #123";

        $startTime = microtime(true);

        $this->applicationTester->run([
            'command' => 'validate',
            'message' => $largeMessage,
        ]);

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // Should validate large message in under 100ms
        expect($duration)->toBeLessThan(0.1);
        expect($this->applicationTester->getStatusCode())->toBe(0);
    });
});
