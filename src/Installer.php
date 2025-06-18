<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint;

use Composer\Script\Event;
use DevKraken\PhpCommitlint\Services\ConfigService;
use DevKraken\PhpCommitlint\Services\HookService;

class Installer
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

        try {
            $configService = new ConfigService();
            $hookService = new HookService();

            // Check if auto-install is enabled in config
            $config = $configService->loadConfig();

            if ($config['auto_install'] ?? false) {
                $io->write('<info>🎯 PHP CommitLint: Setting up Git hooks...</info>');

                $hookService->installHooks();

                $io->write('<info>✅ Git hooks installed successfully!</info>');
                $io->write('<comment>💡 You can configure commit rules in .commitlintrc.json</comment>');
            } else {
                $io->write('<comment>📋 PHP CommitLint installed! Run "vendor/bin/php-commitlint install" to set up Git hooks.</comment>');
            }
        } catch (\Exception $e) {
            $io->writeError('<error>❌ Failed to setup PHP CommitLint: ' . $e->getMessage() . '</error>');
        }
    }
}
