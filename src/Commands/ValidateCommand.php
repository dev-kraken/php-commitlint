<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint\Commands;

use DevKraken\PhpCommitlint\Services\ConfigService;
use DevKraken\PhpCommitlint\Services\ValidationService;
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

    public function __construct()
    {
        parent::__construct();
        $this->validationService = new ValidationService();
        $this->configService = new ConfigService();
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

        try {
            $message = $this->getCommitMessage($input);

            if (empty($message)) {
                if (!$quiet) {
                    $io->error('❌ No commit message provided');
                }

                return Command::FAILURE;
            }

            $config = $this->configService->loadConfig();
            $result = $this->validationService->validate($message, $config);

            if ($result->isValid()) {
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
            } else {
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
        } catch (\Exception $e) {
            if (!$quiet) {
                $io->error('❌ Validation error: ' . $e->getMessage());
            }

            return Command::FAILURE;
        }
    }

    private function getCommitMessage(InputInterface $input): string
    {
        // Priority: argument > file option > .git/COMMIT_EDITMSG
        if ($message = $input->getArgument('message')) {
            return trim($message);
        }

        if ($file = $input->getOption('file')) {
            if (!file_exists($file)) {
                throw new \InvalidArgumentException("File not found: {$file}");
            }
            $content = file_get_contents($file);
            if ($content === false) {
                throw new \RuntimeException("Failed to read file: {$file}");
            }

            return trim($content);
        }

        // Default to .git/COMMIT_EDITMSG (for Git hooks)
        $commitMsgFile = '.git/COMMIT_EDITMSG';
        if (file_exists($commitMsgFile)) {
            $content = file_get_contents($commitMsgFile);
            if ($content === false) {
                throw new \RuntimeException("Failed to read commit message file: {$commitMsgFile}");
            }

            return trim($content);
        }

        return '';
    }

    private function showExamples(SymfonyStyle $io, array $config): void
    {
        $io->section('💡 Examples of valid commit messages:');

        $types = $config['rules']['type']['allowed'] ?? ['feat', 'fix', 'docs', 'style', 'refactor', 'test', 'chore'];

        $examples = [
            "{$types[0]}: add new user authentication",
            "{$types[1]}: resolve login validation issue",
            "{$types[0]}(auth): implement JWT token validation",
        ];

        foreach ($examples as $example) {
            $io->text("  <comment>{$example}</comment>");
        }

        $io->note('📖 Learn more about conventional commits: https://conventionalcommits.org');
    }
}
