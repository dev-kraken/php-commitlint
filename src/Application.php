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
    private const string APP_NAME = 'PHP CommitLint';
    private const string APP_VERSION = '1.0.0';
    private const string DEFAULT_COMMAND = 'validate';

    private ServiceContainer $container;

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
        $commands = [
            InstallCommand::class,
            UninstallCommand::class,
            ValidateCommand::class,
            AddCommand::class,
            RemoveCommand::class,
            ListCommand::class,
        ];

        foreach ($commands as $commandClass) {
            $this->addCommandInstance($commandClass);
        }
    }

    private function addCommandInstance(string $commandClass): void
    {
        /** @var Command $command */
        $command = match ($commandClass) {
            InstallCommand::class => new InstallCommand($this->container),
            UninstallCommand::class => new UninstallCommand($this->container),
            ValidateCommand::class => new ValidateCommand(
                $this->container->getValidationService(),
                $this->container->getConfigService(),
                $this->container->getLoggerService()
            ),
            AddCommand::class => new AddCommand($this->container),
            RemoveCommand::class => new RemoveCommand($this->container),
            ListCommand::class => new ListCommand($this->container),
            default => throw new \InvalidArgumentException("Unknown command class: {$commandClass}")
        };

        $this->add($command);
    }
}
