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
    name: 'add',
    description: 'Add a custom Git hook'
)]
final class AddCommand extends Command
{
    private const array VALID_HOOKS = [
        'pre-commit',
        'commit-msg',
        'pre-push',
        'post-commit',
        'pre-rebase',
        'post-checkout',
        'post-merge',
        'pre-receive',
        'post-receive',
        'update',
    ];

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
            sprintf('Git hook name (%s)', implode(', ', self::VALID_HOOKS))
        );

        $this->addArgument(
            'command',
            InputArgument::REQUIRED,
            'Command to execute in the hook'
        );

        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Overwrite existing hook without confirmation'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $hookName = $input->getArgument('hook');
        $command = $input->getArgument('command');

        if (!is_string($hookName)) {
            throw new InvalidArgumentException('Hook name must be a string');
        }

        if (!is_string($command)) {
            throw new InvalidArgumentException('Command must be a string');
        }
        $force = (bool) $input->getOption('force');

        $io->title('➕ Adding Custom Git Hook');

        try {
            $this->validateGitRepository($io);
            $this->validateHookName($hookName);
            $this->validateCommand($command);
            $this->addHook($io, $hookName, $command, $force);

            $this->logger->info('Custom hook added successfully', [
                'hook' => $hookName,
                'command' => $command,
            ]);

            return ExitCode::SUCCESS->value;
        } catch (Throwable $e) {
            $this->logger->error('Failed to add custom hook', [
                'hook' => $hookName,
                'command' => $command,
                'error' => $e->getMessage(),
            ]);

            $io->error('❌ Failed to add hook: ' . $e->getMessage());

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
        if (!in_array($hookName, self::VALID_HOOKS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid hook name "%s". Valid hooks: %s',
                $hookName,
                implode(', ', self::VALID_HOOKS)
            ));
        }
    }

    private function validateCommand(string $command): void
    {
        if (trim($command) === '') {
            throw new InvalidArgumentException('Command cannot be empty');
        }

        if (strlen($command) > 1000) {
            throw new InvalidArgumentException('Command too long (maximum 1000 characters)');
        }

        // Basic security check for dangerous commands
        $dangerousPatterns = [
            '/rm\s+-rf\s*\//',
            '/>\s*\/dev\/s[a-z]+/',
            '/curl.*\|\s*sh/',
            '/wget.*\|\s*sh/',
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $command)) {
                throw new InvalidArgumentException('Command contains potentially dangerous operations');
            }
        }
    }

    private function addHook(SymfonyStyle $io, string $hookName, string $command, bool $force): void
    {
        $hookPath = '.git/hooks/' . $hookName;

        if (!$force && file_exists($hookPath)) {
            if (!$io->confirm(sprintf('Hook "%s" already exists. Overwrite?', $hookName), false)) {
                throw new \RuntimeException('Operation cancelled by user');
            }
        }

        $io->section('📦 Adding hook...');
        $this->hookService->addCustomHook($hookName, $command);

        $io->success(sprintf('✅ Custom hook "%s" added successfully!', $hookName));

        $io->definitionList(
            ['Hook name' => $hookName],
            ['Command' => $command],
            ['Hook file' => $hookPath]
        );
    }
}
