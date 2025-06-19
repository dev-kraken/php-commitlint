<?php

declare(strict_types=1);

use DevKraken\PhpCommitlint\Commands\ValidateCommand;
use DevKraken\PhpCommitlint\Models\ValidationResult;
use DevKraken\PhpCommitlint\Services\ConfigService;
use DevKraken\PhpCommitlint\Services\ValidationService;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(/**
 * @throws \PHPUnit\Framework\MockObject\Exception
 */ function () {
    $this->tempDir = createTempDirectory();
    $this->originalCwd = getcwd();
    chdir($this->tempDir);

    // Create mock services
    $this->mockValidationService = $this->createMock(ValidationService::class);
    $this->mockConfigService = $this->createMock(ConfigService::class);

    // Setup default config
    $this->mockConfigService
        ->method('loadConfig')
        ->willReturn(createConfig());

    $this->command = new ValidateCommand($this->mockValidationService, $this->mockConfigService);

    $application = new Application();
    $application->add($this->command);

    $this->commandTester = new CommandTester($this->command);
});

afterEach(function () {
    chdir($this->originalCwd);
    cleanupTempPath($this->tempDir);
});

describe('ValidateCommand', function () {
    describe('Command Configuration', function () {
        it('has correct name and description', function () {
            expect($this->command->getName())->toBe('validate')
                ->and($this->command->getDescription())->toBe('Validate a commit message');
        });

        it('accepts message argument', function () {
            $definition = $this->command->getDefinition();
            expect($definition->hasArgument('message'))->toBeTrue()
                ->and($definition->getArgument('message')->isRequired())->toBeFalse();
        });

        it('accepts file option', function () {
            $definition = $this->command->getDefinition();
            expect($definition->hasOption('file'))->toBeTrue()
                ->and($definition->getOption('file')->getShortcut())->toBe('f');
        });

        it('accepts quiet option', function () {
            $definition = $this->command->getDefinition();
            expect($definition->hasOption('quiet'))->toBeTrue()
                ->and($definition->getOption('quiet')->getShortcut())->toBe('q');
        });
    });

    describe('Message Input Handling', function () {
        it('validates message from argument', function () {
            $this->mockValidationService
                ->expects($this->once())
                ->method('validate')
                ->with('feat: add new feature', $this->anything())
                ->willReturn(new ValidationResult(true, [], 'feat', null));

            $exitCode = $this->commandTester->execute([
                'message' => 'feat: add new feature',
            ]);

            expect($exitCode)->toBeExitCode(Command::SUCCESS);
        });

        it('reads message from file option', function () {
            $tempFile = createTempFile('fix: resolve bug');

            $this->mockValidationService
                ->expects($this->once())
                ->method('validate')
                ->with('fix: resolve bug', $this->anything())
                ->willReturn(new ValidationResult(true, [], 'fix', null));

            $exitCode = $this->commandTester->execute([
                '--file' => $tempFile,
            ]);

            expect($exitCode)->toBeExitCode(Command::SUCCESS);
            unlink($tempFile);
        });

        it('reads from .git/COMMIT_EDITMSG by default', function () {
            mkdir('.git', 0o755, true);
            file_put_contents('.git/COMMIT_EDITMSG', 'docs: update documentation');

            $this->mockValidationService
                ->expects($this->once())
                ->method('validate')
                ->with('docs: update documentation', $this->anything())
                ->willReturn(new ValidationResult(true, [], 'docs', null));

            $exitCode = $this->commandTester->execute([]);

            expect($exitCode)->toBeExitCode(Command::SUCCESS);
        });

        it('prioritizes argument over file option', function () {
            $tempFile = createTempFile('wrong: message');

            $this->mockValidationService
                ->expects($this->once())
                ->method('validate')
                ->with('feat: correct message', $this->anything())
                ->willReturn(new ValidationResult(true, [], 'feat', null));

            $exitCode = $this->commandTester->execute([
                'message' => 'feat: correct message',
                '--file' => $tempFile,
            ]);

            expect($exitCode)->toBeExitCode(Command::SUCCESS);
            unlink($tempFile);
        });

        it('throws exception for non-existent file', function () {
            $exitCode = $this->commandTester->execute([
                '--file' => '/non/existent/file.txt',
            ]);

            expect($exitCode)->toBeExitCode(Command::FAILURE)
                ->and($this->commandTester->getDisplay())->toContain('Validation error');
        });
    });

    describe('Validation Success', function () {
        it('shows success message for valid commit', function () {
            $this->mockValidationService
                ->method('validate')
                ->willReturn(new ValidationResult(true, [], 'feat', 'auth'));

            $exitCode = $this->commandTester->execute([
                'message' => 'feat(auth): add JWT validation',
            ]);

            expect($exitCode)->toBeExitCode(Command::SUCCESS)
                ->and($this->commandTester->getDisplay())->toContain('✅ Commit message is valid!')
                ->and($this->commandTester->getDisplay())->toContain('Type: feat')
                ->and($this->commandTester->getDisplay())->toContain('Scope: auth');
        });

        it('shows success without details in quiet mode', function () {
            $this->mockValidationService
                ->method('validate')
                ->willReturn(new ValidationResult(true, [], 'feat', null));

            $exitCode = $this->commandTester->execute([
                'message' => 'feat: add feature',
                '--quiet' => true,
            ]);

            expect($exitCode)->toBeExitCode(Command::SUCCESS)
                ->and($this->commandTester->getDisplay())->toBeEmpty();
        });
    });

    describe('Validation Failure', function () {
        it('shows error message for invalid commit', function () {
            $this->mockValidationService
                ->method('validate')
                ->willReturn(new ValidationResult(false, [
                    'Invalid commit type "invalid"',
                    'Subject must be at least 1 character',
                ], null, null));

            $exitCode = $this->commandTester->execute([
                'message' => 'invalid: ',
            ]);

            expect($exitCode)->toBeExitCode(Command::FAILURE)
                ->and($this->commandTester->getDisplay())->toContain('❌ Commit message validation failed!')
                ->and($this->commandTester->getDisplay())->toContain('🔍 Issues Found:')
                ->and($this->commandTester->getDisplay())->toContain('Invalid commit type "invalid"')
                ->and($this->commandTester->getDisplay())->toContain('Subject must be at least 1 character');
        });

        it('shows examples on validation failure', function () {
            $this->mockValidationService
                ->method('validate')
                ->willReturn(new ValidationResult(false, ['Some error'], null, null));

            $exitCode = $this->commandTester->execute([
                'message' => 'bad message',
            ]);

            expect($exitCode)->toBeExitCode(Command::FAILURE)
                ->and($this->commandTester->getDisplay())->toContain('💡 Examples of valid commit messages:')
                ->and($this->commandTester->getDisplay())->toContain('feat: add new user authentication')
                ->and($this->commandTester->getDisplay())->toContain('conventionalcommits.org');
        });

        it('suppresses output in quiet mode on failure', function () {
            $this->mockValidationService
                ->method('validate')
                ->willReturn(new ValidationResult(false, ['Some error'], null, null));

            $exitCode = $this->commandTester->execute([
                'message' => 'bad message',
                '--quiet' => true,
            ]);

            expect($exitCode)->toBeExitCode(Command::FAILURE)
                ->and($this->commandTester->getDisplay())->toBeEmpty();
        });
    });

    describe('Error Handling', function () {
        it('handles empty message gracefully', function () {
            $exitCode = $this->commandTester->execute([
                'message' => '',
            ]);

            expect($exitCode)->toBeExitCode(Command::FAILURE)
                ->and($this->commandTester->getDisplay())->toContain('❌ No commit message provided');
        });

        it('handles validation service exceptions', function () {
            $this->mockValidationService
                ->method('validate')
                ->willThrowException(new RuntimeException('Service error'));

            $exitCode = $this->commandTester->execute([
                'message' => 'feat: test',
            ]);

            expect($exitCode)->toBeExitCode(Command::FAILURE)
                ->and($this->commandTester->getDisplay())->toContain('❌ Validation error: Service error');
        });

        it('handles config service exceptions', function () {
            $this->mockConfigService
                ->method('loadConfig')
                ->willThrowException(new RuntimeException('Config error'));

            $exitCode = $this->commandTester->execute([
                'message' => 'feat: test',
            ]);

            expect($exitCode)->toBeExitCode(Command::FAILURE)
                ->and($this->commandTester->getDisplay())->toContain('❌ Validation error: Config error');
        });

        it('suppresses errors in quiet mode', function () {
            $this->mockValidationService
                ->method('validate')
                ->willThrowException(new RuntimeException('Service error'));

            $exitCode = $this->commandTester->execute([
                'message' => 'feat: test',
                '--quiet' => true,
            ]);

            expect($exitCode)->toBeExitCode(Command::FAILURE)
                ->and($this->commandTester->getDisplay())->toBeEmpty();
        });
    });

    describe('Custom Configuration', function () {
        it('uses custom allowed types in examples', function () {
            $customConfig = createConfig([
                'rules' => [
                    'type' => [
                        'allowed' => ['custom', 'special', 'unique'],
                    ],
                ],
            ]);

            // Reset the mock configuration to ensure the custom config is used
            $this->mockConfigService = $this->createMock(ConfigService::class);
            $this->mockConfigService
                ->expects($this->once())
                ->method('loadConfig')
                ->willReturn($customConfig);

            // Create a new command instance with the fresh mock
            $this->command = new ValidateCommand($this->mockValidationService, $this->mockConfigService);
            $application = new Application();
            $application->add($this->command);
            $this->commandTester = new CommandTester($this->command);

            $this->mockValidationService
                ->method('validate')
                ->willReturn(new ValidationResult(false, ['Some error'], null, null));

            $exitCode = $this->commandTester->execute([
                'message' => 'bad message',
            ]);

            expect($exitCode)->toBeExitCode(Command::FAILURE)
                ->and($this->commandTester->getDisplay())->toContain('custom: add new user authentication');
        });
    });
});
