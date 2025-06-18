<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint;

use DevKraken\PhpCommitlint\Commands\AddCommand;
use DevKraken\PhpCommitlint\Commands\InstallCommand;
use DevKraken\PhpCommitlint\Commands\ListCommand;
use DevKraken\PhpCommitlint\Commands\RemoveCommand;
use DevKraken\PhpCommitlint\Commands\UninstallCommand;
use DevKraken\PhpCommitlint\Commands\ValidateCommand;
use Symfony\Component\Console\Application as SymfonyApplication;

class Application extends SymfonyApplication
{
    public function __construct()
    {
        parent::__construct('PHP CommitLint', '1.0.0');

        $this->add(new InstallCommand());
        $this->add(new UninstallCommand());
        $this->add(new ValidateCommand());
        $this->add(new AddCommand());
        $this->add(new RemoveCommand());
        $this->add(new ListCommand());

        $this->setDefaultCommand('validate');
    }

    public function getLongVersion(): string
    {
        return sprintf(
            '<info>%s</info> version <comment>%s</comment> by <fg=cyan>DevKraken</fg=cyan>',
            $this->getName(),
            $this->getVersion()
        );
    }
}
