<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint;

use Composer\Script\Event;
use Throwable;

final class Installer
{
    public static function postInstall(Event $event): void
    {
        self::setupHooks($event);
    }

    public static function postUpdate(Event $event): void
    {
        self::setupHooks($event);
    }

    private static function setupHooks(Event $event): void
    {
        $io = $event->getIO();
        $container = ServiceContainer::getInstance();

        try {
            $config = $container->getConfigService()->loadConfig();

            if (!($config['auto_install'] ?? false)) {
                $io->write(
                    '<comment>📋 PHP CommitLint installed! Run "vendor/bin/php-commitlint install" '
                    . 'to set up Git hooks.</comment>'
                );

                return;
            }

            $io->write('<info>🎯 PHP CommitLint: Setting up Git hooks...</info>');
            $container->getHookService()->installHooks();
            $io->write('<info>✅ Git hooks installed successfully!</info>');
            $io->write('<comment>💡 You can configure commit rules in .commitlintrc.json</comment>');
        } catch (Throwable $e) {
            $io->writeError('<error>❌ Failed to setup PHP CommitLint: ' . $e->getMessage() . '</error>');
        }
    }
}
