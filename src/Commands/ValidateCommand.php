<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint\Commands;

use DevKraken\PhpCommitlint\Models\ValidationResult;
use DevKraken\PhpCommitlint\ServiceContainer;
use DevKraken\PhpCommitlint\Services\ConfigService;
use DevKraken\PhpCommitlint\Services\ValidationService;
use Exception;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'validate',
    description: 'Validate a commit message'
)]
class ValidateCommand extends Command
{
    private ValidationService $validationService;
    private ConfigService $configService;

    public function __construct(?ValidationService $validationService = null, ?ConfigService $configService = null)
    {
        parent::__construct();
        $container = ServiceContainer::getInstance();
        $this->validationService = $validationService ?? $container->getValidationService();
        $this->configService = $configService ?? $container->getConfigService();
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
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $quiet = $input->getOption('quiet');
        assert(is_bool($quiet));

        try {
            $message = $this->getCommitMessage($input);

            if (empty($message)) {
                return $this->handleEmptyMessage($io, $quiet);
            }

            $config = $this->configService->loadConfig();
            $result = $this->validationService->validate($message, $config);

            return $result->isValid()
                ? $this->handleValidationSuccess($io, $result, $quiet)
                : $this->handleValidationFailure($io, $result, $config, $quiet);
        } catch (Exception $e) {
            return $this->handleValidationError($io, $e, $quiet);
        }
    }

    private function handleEmptyMessage(SymfonyStyle $io, bool $quiet): int
    {
        if (!$quiet) {
            $io->error('❌ No commit message provided');
        }

        return Command::FAILURE;
    }

    private function handleValidationSuccess(SymfonyStyle $io, ValidationResult $result, bool $quiet): int
    {
        if (!$quiet) {
            $io->success('✅ Commit message is valid!');

            if ($result->getType()) {
                $io->note(sprintf('Type: %s', $result->getType()));
            }

            if ($result->getScope()) {
                $io->note(sprintf('Scope: %s', $result->getScope()));
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function handleValidationFailure(SymfonyStyle $io, ValidationResult $result, array $config, bool $quiet): int
    {
        if (!$quiet) {
            $io->error('❌ Commit message validation failed!');
            $io->section('🔍 Issues Found:');

            foreach ($result->getErrors() as $error) {
                $io->text('  • ' . $error);
            }

            $this->showExamples($io, $config);
        }

        return Command::FAILURE;
    }

    private function handleValidationError(SymfonyStyle $io, Exception $e, bool $quiet): int
    {
        if (!$quiet) {
            $io->error('❌ Validation error: ' . $e->getMessage());
        }

        return Command::FAILURE;
    }

    private function getCommitMessage(InputInterface $input): string
    {
        // Priority: argument > file option > .git/COMMIT_EDITMSG
        $message = $input->getArgument('message');
        if (is_string($message) && trim($message) !== '') {
            return trim($message);
        }

        $file = $input->getOption('file');
        if (is_string($file)) {
            if (!file_exists($file)) {
                throw new InvalidArgumentException("File not found: {$file}");
            }
            $content = file_get_contents($file);
            if ($content !== false) {
                return trim($content);
            }

            throw new RuntimeException("Failed to read file: {$file}");
        }

        // Default to .git/COMMIT_EDITMSG (for Git hooks)
        $commitMsgFile = '.git/COMMIT_EDITMSG';
        if (file_exists($commitMsgFile)) {
            $content = file_get_contents($commitMsgFile);
            if ($content !== false) {
                return trim($content);
            }

            throw new RuntimeException("Failed to read commit message file: {$commitMsgFile}");
        }

        return '';
    }

    /**
     * @param array<string, mixed> $config
     */
    private function showExamples(SymfonyStyle $io, array $config): void
    {
        $io->section('💡 Examples of valid commit messages:');

        // Add assertions to help PHPStan understand the nested array structure
        assert(isset($config['rules']) && is_array($config['rules']));
        assert(isset($config['rules']['type']) && is_array($config['rules']['type']));

        $types = $config['rules']['type']['allowed'] ?? ['feat', 'fix', 'docs', 'style', 'refactor', 'test', 'chore'];
        assert(is_array($types));

        // Ensure we have at least 2 types for examples
        $firstType = $types[0] ?? 'feat';
        $secondType = $types[1] ?? ($types[0] ?? 'fix');
        assert(is_string($firstType));
        assert(is_string($secondType));

        $examples = [
            "{$firstType}: add new user authentication",
            "{$secondType}: resolve login validation issue",
            "{$firstType}(auth): implement JWT token validation",
        ];

        foreach ($examples as $example) {
            $io->text("  <comment>{$example}</comment>");
        }

        $io->note('📖 Learn more about conventional commits: https://conventionalcommits.org');
    }
}
