<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint\Services;

class LoggerService
{
    private const string LOG_FILE = '.git/php-commitlint.log';

    public static function debug(string $message, array $context = []): void
    {
        if (!self::isDebugEnabled()) {
            return;
        }

        self::log('DEBUG', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    /**
     * @throws \JsonException
     */
    private static function log(string $level, string $message, array $context): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context, JSON_THROW_ON_ERROR) : '';
        $logEntry = "[{$timestamp}] {$level}: {$message} {$contextStr}" . PHP_EOL;

        @file_put_contents(self::LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
    }

    private static function isDebugEnabled(): bool
    {
        return ($_SERVER['PHP_COMMITLINT_DEBUG'] ?? '') === '1';
    }
}
