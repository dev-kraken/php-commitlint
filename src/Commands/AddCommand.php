<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint\Commands;

use DevKraken\PhpCommitlint\Commands\Concerns\RequiresGitRepository;
use DevKraken\PhpCommitlint\Enums\ExitCode;
use DevKraken\PhpCommitlint\ServiceContainer;
use DevKraken\PhpCommitlint\Services\HookService;
use DevKraken\PhpCommitlint\Services\LoggerService;
use InvalidArgumentException;
use RuntimeException;
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
    use RequiresGitRepository;

    private const int MAX_COMMAND_LENGTH = 1000;
    private const string HOOKS_DIR = '.git/hooks';

    /** @var list<string> */
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

    /** @var list<string> */
    private const array DANGEROUS_PATTERNS = [
        '#rm\s+-rf\s*/#',
        '#>\s*/dev/s[a-z]+#',
        '#curl.*\|\s*sh#',
        '#wget.*\|\s*sh#',
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
        $this
            ->addArgument(
                'hook',
                InputArgument::REQUIRED,
                sprintf('Git hook name (%s)', implode(', ', self::VALID_HOOKS))
            )
            ->addArgument('hook-command', InputArgument::REQUIRED, 'Command to execute in the hook')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing hook without confirmation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $hookName = $this->stringArgument($input, 'hook');
        $command = $this->stringArgument($input, 'hook-command');
        $force = (bool) $input->getOption('force');

        $io->title('➕ Adding Custom Git Hook');

        try {
            $this->assertGitRepository($this->hookService);
            $this->assertValidHookName($hookName);
            $this->assertValidCommand($command);
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

    private function stringArgument(InputInterface $input, string $name): string
    {
        $value = $input->getArgument($name);
        if (!is_string($value)) {
            throw new InvalidArgumentException(sprintf('Argument "%s" must be a string', $name));
        }

        return $value;
    }

    private function assertValidHookName(string $hookName): void
    {
        if (!in_array($hookName, self::VALID_HOOKS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid hook name "%s". Valid hooks: %s',
                $hookName,
                implode(', ', self::VALID_HOOKS)
            ));
        }
    }

    private function assertValidCommand(string $command): void
    {
        if (trim($command) === '') {
            throw new InvalidArgumentException('Command cannot be empty');
        }

        if (strlen($command) > self::MAX_COMMAND_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'Command too long (maximum %d characters)',
                self::MAX_COMMAND_LENGTH
            ));
        }

        foreach (self::DANGEROUS_PATTERNS as $pattern) {
            if (preg_match($pattern, $command)) {
                throw new InvalidArgumentException('Command contains potentially dangerous operations');
            }
        }
    }

    private function addHook(SymfonyStyle $io, string $hookName, string $command, bool $force): void
    {
        $hookPath = self::HOOKS_DIR . '/' . $hookName;

        if (!$force && file_exists($hookPath)) {
            if (!$io->confirm(sprintf('Hook "%s" already exists. Overwrite?', $hookName), false)) {
                throw new RuntimeException('Operation cancelled by user');
            }
        }

        $io->section('📦 Adding hook...');
        $this->hookService->addCustomHook($hookName, $command);

        $io->success(sprintf('✅ Custom hook "%s" added successfully!', $hookName));
        $io->definitionList(
            ['Hook name' => $hookName],
            ['Command' => $command],
            ['Hook file' => $hookPath],
        );
    }
}
