<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint\Commands;

use DevKraken\PhpCommitlint\Enums\ExitCode;
use DevKraken\PhpCommitlint\ServiceContainer;
use DevKraken\PhpCommitlint\Services\HookService;
use DevKraken\PhpCommitlint\Services\LoggerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'uninstall',
    description: 'Remove Git hooks installed by PHP CommitLint'
)]
final class UninstallCommand extends Command
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
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Force uninstall without confirmation'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');

        $io->title('🗑️  PHP CommitLint - Uninstalling Git Hooks');

        try {
            $this->validateGitRepository($io);
            $this->checkInstalledHooks($io);
            $this->confirmUninstall($io, $force);
            $this->performUninstall($io);

            $this->logger->info('PHP CommitLint hooks uninstalled successfully');

            return ExitCode::SUCCESS->value;
        } catch (Throwable $e) {
            $this->logger->error('Uninstall failed', ['error' => $e->getMessage()]);
            $io->error('❌ Uninstall failed: ' . $e->getMessage());

            return ExitCode::RUNTIME_ERROR->value;
        }
    }

    private function validateGitRepository(SymfonyStyle $io): void
    {
        if (!$this->hookService->isGitRepository()) {
            throw new \RuntimeException('Not a Git repository!');
        }
    }

    private function checkInstalledHooks(SymfonyStyle $io): void
    {
        if (!$this->hookService->hasInstalledHooks()) {
            $io->note('ℹ️  No PHP CommitLint hooks found to remove.');

            throw new \RuntimeException('No hooks to uninstall');
        }
    }

    private function confirmUninstall(SymfonyStyle $io, bool $force): void
    {
        if (!$force && !$io->confirm('Are you sure you want to remove PHP CommitLint hooks?', false)) {
            $io->note('Uninstall cancelled.');

            throw new \RuntimeException('Uninstall cancelled by user');
        }
    }

    private function performUninstall(SymfonyStyle $io): void
    {
        $io->section('🗑️  Removing hooks...');
        $this->hookService->uninstallHooks();

        $io->success('✅ PHP CommitLint hooks removed successfully!');
        $io->note('💡 Configuration file (.commitlintrc.json) was preserved.');
    }
}
