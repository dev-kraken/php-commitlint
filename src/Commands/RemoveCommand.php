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
    name: 'remove',
    description: 'Remove a custom Git hook'
)]
class RemoveCommand extends Command
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
            'Git hook name to remove'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $hook = $input->getArgument('hook');

        $io->title('➖ Removing Custom Git Hook');

        try {
            if (!$this->hookService->isGitRepository()) {
                $io->error('❌ Not a Git repository!');

                return Command::FAILURE;
            }

            $this->hookService->removeCustomHook($hook);

            $io->success(sprintf('✅ Custom hook "%s" removed successfully!', $hook));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('❌ Failed to remove hook: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
