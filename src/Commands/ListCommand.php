<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint\Commands;

use DevKraken\PhpCommitlint\Services\ConfigService;
use DevKraken\PhpCommitlint\Services\HookService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'list',
    description: 'List installed Git hooks and configuration'
)]
class ListCommand extends Command
{
    private HookService $hookService;
    private ConfigService $configService;

    public function __construct()
    {
        parent::__construct();
        $this->hookService = new HookService();
        $this->configService = new ConfigService();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('📋 PHP CommitLint Status');

        try {
            if (!$this->hookService->isGitRepository()) {
                $io->error('❌ Not a Git repository!');

                return Command::FAILURE;
            }

            // Show hook status
            $io->section('🪝 Git Hooks Status');
            $hooks = $this->hookService->getInstalledHooks();

            if (empty($hooks)) {
                $io->note('No PHP CommitLint hooks installed.');
            } else {
                foreach ($hooks as $hook => $info) {
                    $status = $info['installed'] ? '✅' : '❌';
                    $io->text(sprintf('%s %s', $status, $hook));
                }
            }

            // Show configuration
            $io->section('⚙️  Configuration');
            if (!$this->configService->configExists()) {
                $io->note('No configuration file found (.commitlintrc.json)');
            } else {
                $config = $this->configService->loadConfig();

                $io->text([
                    sprintf('Config file: <comment>%s</comment>', $this->configService->getConfigPath()),
                    sprintf('Auto install: <comment>%s</comment>', $config['auto_install'] ? 'Yes' : 'No'),
                ]);

                if (isset($config['rules']['type']['allowed'])) {
                    $io->text(sprintf(
                        'Allowed types: <comment>%s</comment>',
                        implode(', ', $config['rules']['type']['allowed'])
                    ));
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('❌ Error: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
