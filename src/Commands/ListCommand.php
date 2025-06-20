<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint\Commands;

use DevKraken\PhpCommitlint\Enums\ExitCode;
use DevKraken\PhpCommitlint\ServiceContainer;
use DevKraken\PhpCommitlint\Services\ConfigService;
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
    name: 'list',
    description: 'List installed Git hooks and configuration'
)]
final class ListCommand extends Command
{
    private readonly HookService $hookService;
    private readonly ConfigService $configService;
    private readonly LoggerService $logger;

    public function __construct(ServiceContainer $container)
    {
        parent::__construct();
        $this->hookService = $container->getHookService();
        $this->configService = $container->getConfigService();
        $this->logger = $container->getLoggerService();
    }

    protected function configure(): void
    {
        $this->addOption(
            'verbose',
            'v',
            InputOption::VALUE_NONE,
            'Show detailed information'
        );

        $this->addOption(
            'hooks-only',
            null,
            InputOption::VALUE_NONE,
            'Show only hooks information'
        );

        $this->addOption(
            'config-only',
            null,
            InputOption::VALUE_NONE,
            'Show only configuration information'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $verbose = (bool) $input->getOption('verbose');
        $hooksOnly = (bool) $input->getOption('hooks-only');
        $configOnly = (bool) $input->getOption('config-only');

        $io->title('📋 PHP CommitLint Status');

        try {
            $this->validateGitRepository($io);

            if (!$configOnly) {
                $this->displayHooksStatus($io, $verbose);
            }

            if (!$hooksOnly) {
                $this->displayConfigurationStatus($io, $verbose);
            }

            $this->logger->debug('Status command executed successfully');

            return ExitCode::SUCCESS->value;
        } catch (Throwable $e) {
            $this->logger->error('Failed to retrieve status', ['error' => $e->getMessage()]);
            $io->error('❌ Error: ' . $e->getMessage());

            return ExitCode::RUNTIME_ERROR->value;
        }
    }

    private function validateGitRepository(SymfonyStyle $io): void
    {
        if (!$this->hookService->isGitRepository()) {
            throw new \RuntimeException('Not a Git repository!');
        }
    }

    private function displayHooksStatus(SymfonyStyle $io, bool $verbose): void
    {
        $io->section('🪝 Git Hooks Status');

        $hooks = $this->hookService->getInstalledHooks();

        if (empty($hooks)) {
            $io->note('No hooks found.');

            return;
        }

        $installedCount = 0;
        $tableData = [];

        foreach ($hooks as $hookName => $info) {
            $status = $info['installed'] ? '✅ Installed' : '❌ Not Installed';
            $tableData[] = [$hookName, $status];

            if ($info['installed']) {
                $installedCount++;
            }

            if ($verbose && $info['installed']) {
                $tableData[array_key_last($tableData)][] = $info['path'];
            }
        }

        $headers = ['Hook', 'Status'];
        if ($verbose) {
            $headers[] = 'Path';
        }

        $io->table($headers, $tableData);

        $totalHooks = count($hooks);
        $io->note(sprintf('Summary: %d of %d hooks installed', $installedCount, $totalHooks));
    }

    private function displayConfigurationStatus(SymfonyStyle $io, bool $verbose): void
    {
        $io->section('⚙️  Configuration');

        if (!$this->configService->configExists()) {
            $io->warning('No configuration file found (.commitlintrc.json)');
            $io->note('Run "php-commitlint install" to create a default configuration.');

            return;
        }

        try {
            $config = $this->configService->loadConfig();
            $configPath = $this->configService->getConfigPath();

            $io->definitionList(
                ['Config file' => $configPath],
                ['Auto install' => $config['auto_install'] ? 'Yes' : 'No']
            );

            if ($verbose) {
                $this->displayDetailedConfiguration($io, $config);
            } else {
                $this->displayBasicConfiguration($io, $config);
            }
        } catch (Throwable $e) {
            $io->error('Failed to load configuration: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function displayBasicConfiguration(SymfonyStyle $io, array $config): void
    {
        $rules = $config['rules'] ?? [];
        $typeConfig = is_array($rules) ? ($rules['type'] ?? []) : [];
        if (is_array($typeConfig) && isset($typeConfig['allowed']) && is_array($typeConfig['allowed'])) {
            $io->text(sprintf('Allowed types: %s', implode(', ', $typeConfig['allowed'])));
        }

        $scopeConfig = is_array($rules) ? ($rules['scope'] ?? []) : [];
        if (is_array($scopeConfig) && ($scopeConfig['required'] ?? false)) {
            $io->text('Scope: Required');
            if (isset($scopeConfig['allowed']) && is_array($scopeConfig['allowed']) && !empty($scopeConfig['allowed'])) {
                $io->text(sprintf('Allowed scopes: %s', implode(', ', $scopeConfig['allowed'])));
            }
        } else {
            $io->text('Scope: Optional');
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function displayDetailedConfiguration(SymfonyStyle $io, array $config): void
    {
        $rules = $config['rules'] ?? [];

        if (!empty($rules) && is_array($rules)) {
            $io->text('<info>Rules:</info>');

            foreach ($rules as $ruleName => $ruleConfig) {
                if (is_array($ruleConfig)) {
                    $io->text(sprintf('  %s: %s', $ruleName, json_encode($ruleConfig, JSON_UNESCAPED_SLASHES)));
                }
            }
        }

        $patterns = $config['patterns'] ?? [];
        if (is_array($patterns) && !empty($patterns)) {
            $io->newLine();
            $io->text('<info>Patterns:</info>');

            foreach ($patterns as $patternName => $pattern) {
                if (is_string($patternName) && (is_string($pattern) || is_numeric($pattern))) {
                    $io->text(sprintf('  %s: %s', $patternName, (string) $pattern));
                }
            }
        }

        $hooks = $config['hooks'] ?? [];
        if (is_array($hooks) && !empty($hooks)) {
            $io->newLine();
            $io->text('<info>Hook Configuration:</info>');

            foreach ($hooks as $hookName => $enabled) {
                if (is_string($hookName)) {
                    $status = $enabled ? 'Enabled' : 'Disabled';
                    $io->text(sprintf('  %s: %s', $hookName, $status));
                }
            }
        }
    }
}
