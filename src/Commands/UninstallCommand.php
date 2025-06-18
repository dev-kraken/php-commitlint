<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint\Commands;

use DevKraken\PhpCommitlint\Services\HookService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'uninstall',
    description: 'Remove Git hooks installed by PHP CommitLint'
)]
class UninstallCommand extends Command
{
    private HookService $hookService;

    public function __construct()
    {
        parent::__construct();
        $this->hookService = new HookService();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🗑️  PHP CommitLint - Uninstalling Git Hooks');

        try {
            if (!$this->hookService->isGitRepository()) {
                $io->error('❌ Not a Git repository!');

                return Command::FAILURE;
            }

            if (!$this->hookService->hasInstalledHooks()) {
                $io->note('ℹ️  No PHP CommitLint hooks found to remove.');

                return Command::SUCCESS;
            }

            if (!$io->confirm('Are you sure you want to remove PHP CommitLint hooks?', false)) {
                $io->note('Uninstall cancelled.');

                return Command::SUCCESS;
            }

            $this->hookService->uninstallHooks();

            $io->success('✅ PHP CommitLint hooks removed successfully!');
            $io->note('💡 Configuration file (.commitlintrc.json) was preserved.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('❌ Uninstall failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
