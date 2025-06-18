<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint\Commands;

use DevKraken\PhpCommitlint\Services\ConfigService;
use DevKraken\PhpCommitlint\Services\HookService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'install',
    description: 'Install Git hooks for commit message validation'
)]
class InstallCommand extends Command
{
    private ConfigService $configService;
    private HookService $hookService;

    public function __construct()
    {
        parent::__construct();
        $this->configService = new ConfigService();
        $this->hookService = new HookService();
    }

    protected function configure(): void
    {
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Force installation even if hooks already exist'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🎯 PHP CommitLint - Installing Git Hooks');

        try {
            $force = $input->getOption('force');

            if (!$this->hookService->isGitRepository()) {
                $io->error('❌ Not a Git repository! Please run this command in a Git repository.');

                return Command::FAILURE;
            }

            if (!$force && $this->hookService->hasExistingHooks()) {
                $io->warning('⚠️  Git hooks already exist!');

                if (!$io->confirm('Do you want to overwrite existing hooks?', false)) {
                    $io->note('Installation cancelled.');

                    return Command::SUCCESS;
                }
            }

            // Install hooks
            $this->hookService->installHooks();

            // Create default config if it doesn't exist
            if (!$this->configService->configExists()) {
                $this->configService->createDefaultConfig();
                $io->note('📝 Created default configuration file: .commitlintrc.json');
            }

            $io->success('✅ Git hooks installed successfully!');
            $io->note('💡 You can now customize your commit rules in .commitlintrc.json');

            // Show some example usage
            $io->section('🚀 Quick Start');
            $io->text([
                'Try making a commit with an invalid message:',
                '  <comment>git commit -m "bad commit message"</comment>',
                '',
                'Or a valid conventional commit:',
                '  <comment>git commit -m "feat: add new validation feature"</comment>',
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('❌ Installation failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
