<?php

declare(strict_types=1);

use DevKraken\PhpCommitlint\Commands\ValidateCommand;
use DevKraken\PhpCommitlint\Enums\ExitCode;
use DevKraken\PhpCommitlint\Models\ValidationResult;
use DevKraken\PhpCommitlint\Services\ConfigService;
use DevKraken\PhpCommitlint\Services\LoggerService;
use DevKraken\PhpCommitlint\Services\ValidationService;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(
    /**
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    function () {
        $this->tempDir = createTempDirectory();
        $this->originalCwd = getcwd();
        chdir($this->tempDir);

        // Create mock services
        $this->mockValidationService = $this->createMock(ValidationService::class);
        $this->mockConfigService = $this->createMock(ConfigService::class);
        $this->mockLoggerService = $this->createMock(LoggerService::class);

        // Setup default config - will be overridden by specific tests if needed
        $this->mockConfigService
            ->method('loadConfig')
            ->willReturn(createConfig());

        $this->command = new ValidateCommand(
            $this->mockValidationService,
            $this->mockConfigService,
            $this->mockLoggerService
        );

        $application = new Application();
        $application->add($this->command);

        $this->commandTester = new CommandTester($this->command);
    }
);

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

        it('accepts verbose-errors option', function () {
            $definition = $this->command->getDefinition();
            expect($definition->hasOption('verbose-errors'))->toBeTrue();
        });
    });

    describe('Message Input Handling', function () {
        it('validates message from argument', function () {
            $this->mockValidationService
                ->expects($this->once())
                ->method('validate')
                ->with('feat: add new feature', $this->anything())
                ->willReturn(ValidationResult::valid('feat', null));

            $exitCode = $this->commandTester->execute([
                'message' => 'feat: add new feature',
            ]);

            expect($exitCode)->toBeExitCode(ExitCode::SUCCESS->value);
        });

        it('reads message from file option', function () {
            $tempFile = createTempFile('fix: resolve bug');

            $this->mockValidationService
                ->expects($this->once())
                ->method('validate')
                ->with('fix: resolve bug', $this->anything())
                ->willReturn(ValidationResult::valid('fix', null));

            $exitCode = $this->commandTester->execute([
                '--file' => $tempFile,
            ]);

            expect($exitCode)->toBeExitCode(ExitCode::SUCCESS->value);
            unlink($tempFile);
        });

        it('reads from .git/COMMIT_EDITMSG by default', function () {
            mkdir('.git', 0o755, true);
            file_put_contents('.git/COMMIT_EDITMSG', 'docs: update documentation');

            $this->mockValidationService
                ->expects($this->once())
                ->method('validate')
                ->with('docs: update documentation', $this->anything())
                ->willReturn(ValidationResult::valid('docs', null));

            $exitCode = $this->commandTester->execute([]);

            expect($exitCode)->toBeExitCode(ExitCode::SUCCESS->value);
        });

        it('prioritizes argument over file option', function () {
            $tempFile = createTempFile('wrong: message');

            $this->mockValidationService
                ->expects($this->once())
                ->method('validate')
                ->with('feat: correct message', $this->anything())
                ->willReturn(ValidationResult::valid('feat', null));

            $exitCode = $this->commandTester->execute([
                'message' => 'feat: correct message',
                '--file' => $tempFile,
            ]);

            expect($exitCode)->toBeExitCode(ExitCode::SUCCESS->value);
            unlink($tempFile);
        });

        it('throws exception for non-existent file', function () {
            $exitCode = $this->commandTester->execute([
                '--file' => '/non/existent/file.txt',
            ]);

            expect($exitCode)->toBeExitCode(ExitCode::RUNTIME_ERROR->value)
                ->and($this->commandTester->getDisplay())->toContain('Validation error');
        });
    });

    describe('Empty Message Handling', function () {
        it('handles empty message argument', function () {
            $exitCode = $this->commandTester->execute([
                'message' => '',
            ]);

            expect($exitCode)->toBeExitCode(ExitCode::VALIDATION_FAILED->value)
                ->and($this->commandTester->getDisplay())->toContain('❌ No commit message provided');
        });

        it('handles missing commit message file', function () {
            $exitCode = $this->commandTester->execute([]);

            expect($exitCode)->toBeExitCode(ExitCode::RUNTIME_ERROR->value);
        });
    });

    describe('Validation Success', function () {
        it('shows success message for valid commit', function () {
            $this->mockValidationService
                ->method('validate')
                ->willReturn(ValidationResult::valid('feat', 'auth'));

            $exitCode = $this->commandTester->execute([
                'message' => 'feat(auth): add JWT validation',
            ]);

            expect($exitCode)->toBeExitCode(ExitCode::SUCCESS->value)
                ->and($this->commandTester->getDisplay())->toContain('✅ Commit message is valid!')
                ->and($this->commandTester->getDisplay())->toContain('Type: feat')
                ->and($this->commandTester->getDisplay())->toContain('Scope: auth');
        });

        it('shows success without details in quiet mode', function () {
            $this->mockValidationService
                ->method('validate')
                ->willReturn(ValidationResult::valid('feat', null));

            $exitCode = $this->commandTester->execute([
                'message' => 'feat: add feature',
                '--quiet' => true,
            ]);

            expect($exitCode)->toBeExitCode(ExitCode::SUCCESS->value)
                ->and($this->commandTester->getDisplay())->toBeEmpty();
        });
    });

    describe('Validation Failure', function () {
        it('shows error message for invalid commit', function () {
            $this->mockValidationService
                ->method('validate')
                ->willReturn(ValidationResult::invalid([
                    'Invalid commit type "invalid"',
                    'Subject must be at least 1 character',
                ], null, null));

            $exitCode = $this->commandTester->execute([
                'message' => 'invalid: ',
            ]);

            expect($exitCode)->toBeExitCode(ExitCode::VALIDATION_FAILED->value)
                ->and($this->commandTester->getDisplay())->toContain('❌ Commit message validation failed!')
                ->and($this->commandTester->getDisplay())->toContain('🔍 Issues Found:')
                ->and($this->commandTester->getDisplay())->toContain('Invalid commit type "invalid"')
                ->and($this->commandTester->getDisplay())->toContain('Subject must be at least 1 character');
        });

        it('shows examples on validation failure', function () {
            $this->mockValidationService
                ->method('validate')
                ->willReturn(ValidationResult::invalid(['Some error'], null, null));

            $exitCode = $this->commandTester->execute([
                'message' => 'bad message',
            ]);

            expect($exitCode)->toBeExitCode(ExitCode::VALIDATION_FAILED->value)
                ->and($this->commandTester->getDisplay())->toContain('💡 Examples of valid commit messages:')
                ->and($this->commandTester->getDisplay())->toContain('feat: add new user authentication');
        });

        it('shows failure without details in quiet mode', function () {
            $this->mockValidationService
                ->method('validate')
                ->willReturn(ValidationResult::invalid(['Some error'], null, null));

            $exitCode = $this->commandTester->execute([
                'message' => 'bad message',
                '--quiet' => true,
            ]);

            expect($exitCode)->toBeExitCode(ExitCode::VALIDATION_FAILED->value)
                ->and($this->commandTester->getDisplay())->toBeEmpty();
        });

        it('shows detailed error information with verbose option', function () {
            $this->mockValidationService
                ->method('validate')
                ->willReturn(ValidationResult::invalid([
                    'Error 1',
                    'Error 2',
                    'Error 3',
                ], null, null));

            $exitCode = $this->commandTester->execute([
                'message' => 'bad message',
                '--verbose-errors' => true,
            ]);

            expect($exitCode)->toBeExitCode(ExitCode::VALIDATION_FAILED->value)
                ->and($this->commandTester->getDisplay())->toContain('Total errors found: 3');
        });
    });

    describe('Configuration Loading', function () {
        it('loads configuration from ConfigService', function () {
            $customConfig = createConfig([
                'rules' => [
                    'type' => [
                        'allowed' => ['custom', 'types'],
                    ],
                ],
            ]);

            // Create a fresh mock ConfigService for this test to avoid conflicts
            $mockConfigService = $this->createMock(ConfigService::class);
            $mockConfigService
                ->expects($this->once())
                ->method('loadConfig')
                ->willReturn($customConfig);

            // Create a new command instance with the fresh mock
            $command = new ValidateCommand(
                $this->mockValidationService,
                $mockConfigService,
                $this->mockLoggerService
            );

            $application = new Application();
            $application->add($command);
            $commandTester = new CommandTester($command);

            $this->mockValidationService
                ->expects($this->once())
                ->method('validate')
                ->with('custom: message', $customConfig)
                ->willReturn(ValidationResult::valid('custom', null));

            $exitCode = $commandTester->execute([
                'message' => 'custom: message',
            ]);

            expect($exitCode)->toBeExitCode(ExitCode::SUCCESS->value);
        });
    });

    describe('Error Handling', function () {
        it('handles validation service exceptions', function () {
            $this->mockValidationService
                ->method('validate')
                ->willThrowException(new Exception('Service error'));

            $exitCode = $this->commandTester->execute([
                'message' => 'feat: test message',
            ]);

            expect($exitCode)->toBeExitCode(ExitCode::RUNTIME_ERROR->value)
                ->and($this->commandTester->getDisplay())->toContain('❌ Validation error: Service error');
        });

        it('handles config service exceptions', function () {
            $this->mockConfigService
                ->method('loadConfig')
                ->willThrowException(new Exception('Config error'));

            $exitCode = $this->commandTester->execute([
                'message' => 'feat: test message',
            ]);

            expect($exitCode)->toBeExitCode(ExitCode::RUNTIME_ERROR->value)
                ->and($this->commandTester->getDisplay())->toContain('❌ Validation error: Config error');
        });
    });

    describe('Logging Integration', function () {
        it('logs validation results', function () {
            $this->mockLoggerService
                ->expects($this->once())
                ->method('debug')
                ->with('Validation completed', $this->anything());

            $this->mockLoggerService
                ->expects($this->once())
                ->method('info')
                ->with('Commit message validation successful', $this->anything());

            $this->mockValidationService
                ->method('validate')
                ->willReturn(ValidationResult::valid('feat', null));

            $this->commandTester->execute([
                'message' => 'feat: test message',
            ]);
        });

        it('logs validation failures', function () {
            $this->mockLoggerService
                ->expects($this->once())
                ->method('warning')
                ->with('Commit message validation failed', $this->anything());

            $this->mockValidationService
                ->method('validate')
                ->willReturn(ValidationResult::invalid(['Error'], null, null));

            $this->commandTester->execute([
                'message' => 'invalid message',
            ]);
        });
    });
});
