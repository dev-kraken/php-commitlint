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
use Symfony\Component\Console\Command\Command;

final class Application extends SymfonyApplication
{
    public const string APP_NAME = 'PHP CommitLint';
    public const string APP_VERSION = '1.1.0';

    private const string DEFAULT_COMMAND = 'validate';

    private readonly ServiceContainer $container;

    public function __construct(?ServiceContainer $container = null)
    {
        parent::__construct(self::APP_NAME, self::APP_VERSION);

        $this->container = $container ?? ServiceContainer::getInstance();
        $this->registerCommands();
        $this->setDefaultCommand(self::DEFAULT_COMMAND);
    }

    public function getLongVersion(): string
    {
        return sprintf(
            '<info>%s</info> version <comment>%s</comment> by <fg=cyan>DevKraken</fg=cyan>',
            $this->getName(),
            $this->getVersion()
        );
    }

    public function getContainer(): ServiceContainer
    {
        return $this->container;
    }

    private function registerCommands(): void
    {
        foreach ($this->createCommands() as $command) {
            $this->registerCommand($command);
        }
    }

    /**
     * Use Symfony 7.4+ addCommand() when available; fall back to add() for older versions.
     */
    private function registerCommand(Command $command): void
    {
        if (method_exists($this, 'addCommand')) {
            $this->addCommand($command);

            return;
        }

        $this->add($command);
    }

    /**
     * @return list<Command>
     */
    private function createCommands(): array
    {
        return [
            new InstallCommand($this->container),
            new UninstallCommand($this->container),
            new ValidateCommand(
                $this->container->getValidationService(),
                $this->container->getConfigService(),
                $this->container->getLoggerService(),
            ),
            new AddCommand($this->container),
            new RemoveCommand($this->container),
            new ListCommand($this->container),
        ];
    }
}
