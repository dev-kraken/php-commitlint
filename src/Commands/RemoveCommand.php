<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint\Commands;

use DevKraken\PhpCommitlint\Enums\ExitCode;
use DevKraken\PhpCommitlint\ServiceContainer;
use DevKraken\PhpCommitlint\Services\HookService;
use DevKraken\PhpCommitlint\Services\LoggerService;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'remove',
    description: 'Remove a custom Git hook'
)]
final class RemoveCommand extends Command
{
    private readonly HookService $hookService;
    private readonly LoggerService $logger;

    public function __construct(ServiceContainer $container)
    {
        parent::__construct();
        $this->hookService = $container->getHookService();
        $this->logger = $container->getLoggerService();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'hook',
            InputArgument::REQUIRED,
            'Git hook name to remove'
        );

        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Force removal without confirmation'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $hookName = $input->getArgument('hook');

        if (!is_string($hookName)) {
            throw new InvalidArgumentException('Hook name must be a string');
        }
        $force = (bool) $input->getOption('force');

        $io->title('➖ Removing Custom Git Hook');

        try {
            $this->validateGitRepository($io);
            $this->validateHookName($hookName);
            $this->confirmRemoval($io, $hookName, $force);
            $this->removeHook($io, $hookName);

            $this->logger->info('Custom hook removed successfully', ['hook' => $hookName]);

            return ExitCode::SUCCESS->value;
        } catch (Throwable $e) {
            $this->logger->error('Failed to remove custom hook', [
                'hook' => $hookName,
                'error' => $e->getMessage(),
            ]);

            $io->error('❌ Failed to remove hook: ' . $e->getMessage());

            return ExitCode::RUNTIME_ERROR->value;
        }
    }

    private function validateGitRepository(SymfonyStyle $io): void
    {
        if (!$this->hookService->isGitRepository()) {
            throw new \RuntimeException('Not a Git repository!');
        }
    }

    private function validateHookName(string $hookName): void
    {
        if (trim($hookName) === '') {
            throw new InvalidArgumentException('Hook name cannot be empty');
        }

        if (!preg_match('/^[a-z-]+$/', $hookName)) {
            throw new InvalidArgumentException('Hook name must contain only lowercase letters and hyphens');
        }
    }

    private function confirmRemoval(SymfonyStyle $io, string $hookName, bool $force): void
    {
        $hookPath = '.git/hooks/' . $hookName;

        if (!file_exists($hookPath)) {
            throw new \RuntimeException(sprintf('Hook "%s" does not exist', $hookName));
        }

        if (!$force && !$io->confirm(sprintf('Are you sure you want to remove hook "%s"?', $hookName), false)) {
            throw new \RuntimeException('Operation cancelled by user');
        }
    }

    private function removeHook(SymfonyStyle $io, string $hookName): void
    {
        $io->section('🗑️  Removing hook...');
        $this->hookService->removeCustomHook($hookName);

        $io->success(sprintf('✅ Custom hook "%s" removed successfully!', $hookName));
    }
}
