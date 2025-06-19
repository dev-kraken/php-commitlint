<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint\Commands;

use DevKraken\PhpCommitlint\Services\HookService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'add',
    description: 'Add a custom Git hook'
)]
class AddCommand extends Command
{
    private HookService $hookService;

    public function __construct()
    {
        parent::__construct();
        $this->hookService = new HookService();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'hook',
            InputArgument::REQUIRED,
            'Git hook name (e.g., pre-commit, commit-msg, pre-push)'
        );

        $this->addArgument(
            'hook-command',
            InputArgument::REQUIRED,
            'Command to execute'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $hook */
        $hook = $input->getArgument('hook');
        /** @var string $command */
        $command = $input->getArgument('hook-command');

        $io->title('➕ Adding Custom Git Hook');

        try {
            if (!$this->hookService->isGitRepository()) {
                $io->error('❌ Not a Git repository!');

                return Command::FAILURE;
            }

            $validHooks = ['pre-commit', 'commit-msg', 'pre-push', 'post-commit', 'pre-rebase'];
            if (!in_array($hook, $validHooks)) {
                $io->error(sprintf('❌ Invalid hook name. Valid hooks: %s', implode(', ', $validHooks)));

                return Command::FAILURE;
            }

            $this->hookService->addCustomHook($hook, $command);

            $io->success(sprintf('✅ Custom hook "%s" added successfully!', $hook));
            $io->note(sprintf('Command: %s', $command));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('❌ Failed to add hook: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
