<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint\Commands;

use DevKraken\PhpCommitlint\Commands\Concerns\RequiresGitRepository;
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
    name: 'status',
    description: 'List installed Git hooks and configuration'
)]
final class ListCommand extends Command
{
    use RequiresGitRepository;

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
        $this
            ->addOption('hooks-only', null, InputOption::VALUE_NONE, 'Show only hooks information')
            ->addOption('config-only', null, InputOption::VALUE_NONE, 'Show only configuration information');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $verbose = $output->isVerbose();
        $hooksOnly = (bool) $input->getOption('hooks-only');
        $configOnly = (bool) $input->getOption('config-only');

        $io->title('📋 PHP CommitLint Status');

        try {
            $this->assertGitRepository($this->hookService);

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

    private function displayHooksStatus(SymfonyStyle $io, bool $verbose): void
    {
        $io->section('🪝 Git Hooks Status');

        $hooks = $this->hookService->getInstalledHooks();

        if ($hooks === []) {
            $io->note('No hooks found.');

            return;
        }

        $headers = $verbose ? ['Hook', 'Status', 'Path'] : ['Hook', 'Status'];
        $rows = [];
        $installedCount = 0;

        foreach ($hooks as $hookName => $info) {
            $row = [$hookName, $info['installed'] ? '✅ Installed' : '❌ Not Installed'];
            if ($verbose) {
                $row[] = $info['installed'] ? $info['path'] : '';
            }
            $rows[] = $row;

            if ($info['installed']) {
                $installedCount++;
            }
        }

        $io->table($headers, $rows);
        $io->note(sprintf('Summary: %d of %d hooks installed', $installedCount, count($hooks)));
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
            $io->definitionList(
                ['Config file' => $this->configService->getConfigPath()],
                ['Auto install' => ($config['auto_install'] ?? false) ? 'Yes' : 'No'],
            );

            $verbose
                ? $this->displayDetailedConfiguration($io, $config)
                : $this->displayBasicConfiguration($io, $config);
        } catch (Throwable $e) {
            $io->error('Failed to load configuration: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function displayBasicConfiguration(SymfonyStyle $io, array $config): void
    {
        $rules = is_array($config['rules'] ?? null) ? $config['rules'] : [];

        $typeConfig = is_array($rules['type'] ?? null) ? $rules['type'] : [];
        $allowedTypes = $this->stringList($typeConfig['allowed'] ?? null);
        if ($allowedTypes !== []) {
            $io->text(sprintf('Allowed types: %s', implode(', ', $allowedTypes)));
        }

        $scopeConfig = is_array($rules['scope'] ?? null) ? $rules['scope'] : [];
        if (($scopeConfig['required'] ?? false) === true) {
            $io->text('Scope: Required');
            $allowedScopes = $this->stringList($scopeConfig['allowed'] ?? null);
            if ($allowedScopes !== []) {
                $io->text(sprintf('Allowed scopes: %s', implode(', ', $allowedScopes)));
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
        $this->displaySection($io, 'Rules', $config['rules'] ?? null, true);
        $this->displaySection($io, 'Patterns', $config['patterns'] ?? null, false);
        $this->displaySection($io, 'Hook Configuration', $this->mapHooksToStatus($config['hooks'] ?? null), false);
    }

    private function displaySection(SymfonyStyle $io, string $title, mixed $values, bool $jsonEncode): void
    {
        if (!is_array($values) || $values === []) {
            return;
        }

        $io->newLine();
        $io->text(sprintf('<info>%s:</info>', $title));

        foreach ($values as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $rendered = $jsonEncode && is_array($value)
                ? json_encode($value, JSON_UNESCAPED_SLASHES)
                : (is_scalar($value) ? (string) $value : null);

            if ($rendered !== null && $rendered !== false) {
                $io->text(sprintf('  %s: %s', $key, $rendered));
            }
        }
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }

    /**
     * @return array<string, string>|null
     */
    private function mapHooksToStatus(mixed $hooks): ?array
    {
        if (!is_array($hooks)) {
            return null;
        }

        $mapped = [];
        foreach ($hooks as $name => $enabled) {
            if (is_string($name)) {
                $mapped[$name] = $enabled ? 'Enabled' : 'Disabled';
            }
        }

        return $mapped;
    }
}
