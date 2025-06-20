<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint\Commands;

use DevKraken\PhpCommitlint\Enums\ExitCode;
use DevKraken\PhpCommitlint\Models\ValidationResult;
use DevKraken\PhpCommitlint\Services\ConfigService;
use DevKraken\PhpCommitlint\Services\LoggerService;
use DevKraken\PhpCommitlint\Services\ValidationService;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'validate',
    description: 'Validate a commit message'
)]
final class ValidateCommand extends Command
{
    private const string DEFAULT_COMMIT_MSG_FILE = '.git/COMMIT_EDITMSG';

    public function __construct(
        private readonly ValidationService $validationService,
        private readonly ConfigService $configService,
        private readonly LoggerService $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'message',
            InputArgument::OPTIONAL,
            'Commit message to validate (if not provided, will read from .git/COMMIT_EDITMSG)'
        );

        $this->addOption(
            'file',
            'f',
            InputOption::VALUE_REQUIRED,
            'Read commit message from file'
        );

        $this->addOption(
            'quiet',
            'q',
            InputOption::VALUE_NONE,
            'Suppress output (exit code only)'
        );

        $this->addOption(
            'verbose-errors',
            null,
            InputOption::VALUE_NONE,
            'Show detailed error information'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $quiet = (bool) $input->getOption('quiet');
        $verboseErrors = (bool) $input->getOption('verbose-errors');

        try {
            $message = $this->getCommitMessage($input);

            if (empty(trim($message))) {
                return $this->handleEmptyMessage($io, $quiet);
            }

            $config = $this->configService->loadConfig();
            $result = $this->validationService->validate($message, $config);

            $this->logger->debug('Validation completed', [
                'valid' => $result->isValid(),
                'errors' => $result->getErrors(),
                'type' => $result->getType(),
                'scope' => $result->getScope(),
            ]);

            return $result->isValid()
                ? $this->handleValidationSuccess($io, $result, $quiet)
                : $this->handleValidationFailure($io, $result, $config, $quiet, $verboseErrors);
        } catch (Throwable $e) {
            return $this->handleValidationError($io, $e, $quiet);
        }
    }

    private function handleEmptyMessage(SymfonyStyle $io, bool $quiet): int
    {
        $this->logger->warning('Empty commit message provided');

        if (!$quiet) {
            $io->error('❌ No commit message provided');
        }

        return ExitCode::VALIDATION_FAILED->value;
    }

    private function handleValidationSuccess(SymfonyStyle $io, ValidationResult $result, bool $quiet): int
    {
        $this->logger->info('Commit message validation successful', [
            'type' => $result->getType(),
            'scope' => $result->getScope(),
        ]);

        if (!$quiet) {
            $io->success('✅ Commit message is valid!');

            if ($result->getType()) {
                $io->note(sprintf('Type: %s', $result->getType()));
            }

            if ($result->getScope()) {
                $io->note(sprintf('Scope: %s', $result->getScope()));
            }
        }

        return ExitCode::SUCCESS->value;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function handleValidationFailure(
        SymfonyStyle $io,
        ValidationResult $result,
        array $config,
        bool $quiet,
        bool $verboseErrors
    ): int {
        $this->logger->warning('Commit message validation failed', [
            'errors' => $result->getErrors(),
            'type' => $result->getType(),
            'scope' => $result->getScope(),
        ]);

        if (!$quiet) {
            $io->error('❌ Commit message validation failed!');
            $this->displayErrors($io, $result, $verboseErrors);
            $this->showExamples($io, $config);
        }

        return ExitCode::VALIDATION_FAILED->value;
    }

    private function handleValidationError(SymfonyStyle $io, Throwable $e, bool $quiet): int
    {
        $this->logger->error('Validation error occurred', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        if (!$quiet) {
            $io->error('❌ Validation error: ' . $e->getMessage());
        }

        return ExitCode::RUNTIME_ERROR->value;
    }

    private function displayErrors(SymfonyStyle $io, ValidationResult $result, bool $verbose): void
    {
        $io->section('🔍 Issues Found:');

        foreach ($result->getErrors() as $index => $error) {
            $io->text(sprintf('  %d. %s', $index + 1, $error));
        }

        if ($verbose && $result->getErrorCount() > 0) {
            $io->newLine();
            $io->note(sprintf('Total errors found: %d', $result->getErrorCount()));
        }
    }

    private function getCommitMessage(InputInterface $input): string
    {
        $message = $input->getArgument('message');
        if (is_string($message)) {
            return trim($message);
        }

        $file = $input->getOption('file');
        if (is_string($file)) {
            return $this->readMessageFromFile($file);
        }

        return $this->readMessageFromFile(self::DEFAULT_COMMIT_MSG_FILE);
    }

    private function readMessageFromFile(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException("Failed to read file: {$filePath}");
        }

        return trim($content);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function showExamples(SymfonyStyle $io, array $config): void
    {
        $io->section('💡 Examples of valid commit messages:');

        $rules = $config['rules'] ?? [];
        $typeConfig = is_array($rules) ? ($rules['type'] ?? []) : [];
        $allowedTypes = [];

        if (is_array($typeConfig) && isset($typeConfig['allowed']) && is_array($typeConfig['allowed'])) {
            $allowedTypes = $typeConfig['allowed'];
        } else {
            $allowedTypes = ['feat', 'fix', 'docs', 'style', 'refactor', 'test', 'chore'];
        }

        if (!is_array($allowedTypes) || empty($allowedTypes)) {
            $allowedTypes = ['feat', 'fix'];
        }

        $firstType = $allowedTypes[0] ?? 'feat';
        $secondType = $allowedTypes[1] ?? 'fix';

        $examples = [
            sprintf('%s: add new user authentication', $firstType),
            sprintf('%s: resolve login validation issue', $secondType),
            sprintf('%s(auth): implement JWT token validation', $firstType),
        ];

        foreach ($examples as $example) {
            $io->text('  • ' . $example);
        }

        $io->newLine();
        $io->text('For more information, check your .commitlintrc.json configuration file.');
    }
}
