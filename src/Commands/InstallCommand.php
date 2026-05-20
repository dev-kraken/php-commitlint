<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint\Commands;

use DevKraken\PhpCommitlint\Commands\Concerns\RequiresGitRepository;
use DevKraken\PhpCommitlint\Enums\ExitCode;
use DevKraken\PhpCommitlint\ServiceContainer;
use DevKraken\PhpCommitlint\Services\ConfigService;
use DevKraken\PhpCommitlint\Services\HookService;
use DevKraken\PhpCommitlint\Services\LoggerService;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'install',
    description: 'Install Git hooks for commit message validation'
)]
final class InstallCommand extends Command
{
    use RequiresGitRepository;

    private readonly ConfigService $configService;
    private readonly HookService $hookService;
    private readonly LoggerService $logger;

    public function __construct(ServiceContainer $container)
    {
        parent::__construct();
        $this->configService = $container->getConfigService();
        $this->hookService = $container->getHookService();
        $this->logger = $container->getLoggerService();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force installation even if hooks already exist')
            ->addOption('skip-config', null, InputOption::VALUE_NONE, 'Skip creating default configuration file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🎯 PHP CommitLint - Installing Git Hooks');

        try {
            $this->assertGitRepository(
                $this->hookService,
                'Not a Git repository! Please run this command in a Git repository.'
            );
            $this->confirmHookOverwriteIfNeeded($io, (bool) $input->getOption('force'));
            $this->installHooks($io);
            $this->createConfigurationIfNeeded($io, (bool) $input->getOption('skip-config'));
            $this->showSuccessMessage($io);

            $this->logger->info('Git hooks installed successfully');

            return ExitCode::SUCCESS->value;
        } catch (Throwable $e) {
            $this->logger->error('Installation failed', ['error' => $e->getMessage()]);
            $io->error('❌ Installation failed: ' . $e->getMessage());

            return ExitCode::RUNTIME_ERROR->value;
        }
    }

    private function confirmHookOverwriteIfNeeded(SymfonyStyle $io, bool $force): void
    {
        if ($force || !$this->hookService->hasExistingHooks()) {
            return;
        }

        $io->warning('⚠️  Git hooks already exist!');

        if (!$io->confirm('Do you want to overwrite existing hooks?', false)) {
            $io->note('Installation cancelled.');

            throw new RuntimeException('Installation cancelled by user.');
        }
    }

    private function installHooks(SymfonyStyle $io): void
    {
        $io->section('📦 Installing hooks...');
        $this->hookService->installHooks();
        $io->text('✅ Hooks installed successfully');
    }

    private function createConfigurationIfNeeded(SymfonyStyle $io, bool $skipConfig): void
    {
        if ($skipConfig) {
            return;
        }

        if ($this->configService->configExists()) {
            $io->text('📝 Using existing configuration file');

            return;
        }

        $io->section('⚙️  Creating configuration...');
        $this->configService->createDefaultConfig();
        $io->text('📝 Created default configuration file: .commitlintrc.json');
    }

    private function showSuccessMessage(SymfonyStyle $io): void
    {
        $io->success('✅ Git hooks installed successfully!');
        $io->note('💡 You can now customize your commit rules in .commitlintrc.json');

        $io->section('🚀 Quick Start');
        $io->definitionList(
            ['Invalid commit' => 'git commit -m "bad commit message"'],
            ['Valid commit' => 'git commit -m "feat: add new validation feature"'],
            ['With scope' => 'git commit -m "fix(auth): resolve login validation issue"'],
        );
    }
}
